<?php
/**
 * The integrations list bootstrapped into the React settings screen.
 *
 * This used to be markup built inline in a view, so nothing could assert it.
 * It is data now, and the admin app renders straight from it - which means a
 * wrong shape here is a blank or broken tab rather than a bad-looking one.
 *
 * @package ThriveDesk\Tests
 */

class IntegrationsPayloadTest extends WP_UnitTestCase {

	public function test_returns_every_integration_the_screen_offers() {
		$slugs = wp_list_pluck( thrivedesk_integrations(), 'slug' );

		$this->assertSame(
			[ 'woocommerce', 'edd', 'fluentcrm', 'wppostsync', 'autonami', 'surecart', 'freemius' ],
			$slugs
		);
	}

	public function test_every_entry_has_the_shape_the_app_renders_from() {
		foreach ( thrivedesk_integrations() as $integration ) {
			$this->assertSame(
				[ 'slug', 'name', 'category', 'description', 'image', 'installed', 'connected', 'external' ],
				array_keys( $integration ),
				'the React component destructures these by name'
			);

			$this->assertNotSame( '', $integration['name'] );
			$this->assertIsBool( $integration['installed'] );
			$this->assertIsBool( $integration['connected'] );
		}
	}

	public function test_logos_resolve_to_urls_under_the_plugin_assets() {
		foreach ( thrivedesk_integrations() as $integration ) {
			$this->assertStringStartsWith(
				THRIVEDESK_PLUGIN_ASSETS . '/images/',
				$integration['image'],
				sprintf( '%s has a logo the browser cannot load', $integration['slug'] )
			);
		}
	}

	/**
	 * SureCart and Freemius authorize on the ThriveDesk side, so they must be
	 * handed off rather than connected through the local AJAX handler. The
	 * component branches on `external` to decide that.
	 */
	public function test_only_the_hand_off_partners_carry_an_external_url() {
		$external = [];

		foreach ( thrivedesk_integrations() as $integration ) {
			if ( null !== $integration['external'] ) {
				$external[ $integration['slug'] ] = $integration['external'];
			}
		}

		$this->assertSame(
			[
				'surecart' => THRIVEDESK_APP_URL . '/apps/surecart',
				'freemius' => THRIVEDESK_APP_URL . '/apps/freemius',
			],
			$external
		);
	}

	/**
	 * The card gives the description a line of its own, so an empty one leaves
	 * a visible hole rather than degrading to a shorter card.
	 */
	public function test_every_entry_describes_itself() {
		foreach ( thrivedesk_integrations() as $integration ) {
			$this->assertNotSame(
				'',
				trim( (string) $integration['description'] ),
				sprintf( '%s renders a card with an empty description', $integration['slug'] )
			);
		}
	}

	public function test_connected_reflects_stored_integration_state() {
		update_option( 'thrivedesk_options', [ 'woocommerce' => [ 'connected' => true ] ] );

		$byslug = array_column( thrivedesk_integrations(), null, 'slug' );

		$this->assertTrue( $byslug['woocommerce']['connected'] );
		$this->assertFalse( $byslug['edd']['connected'] );

		delete_option( 'thrivedesk_options' );
	}
}
