<?php
/**
 * Making a search compulsory before a ticket can be opened.
 *
 * The setting is a checkbox on the Portal tab; the behaviour is in the portal's
 * search modal. What is asserted here is the part that spans both: the flag
 * survives a save, and the modal reads it out onto the container where
 * conversation.js can find it.
 *
 * The stored 0/1 matters. An unticked checkbox posts nothing, so "absent" would
 * have meant both "turned off" and "this install predates the setting" - and
 * the second must not read as the first once someone has turned it on.
 *
 * @package ThriveDesk\Tests
 */

class PortalSearchRequiredTest extends TD_Ajax_TestCase {

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	public function tear_down() {
		delete_option( 'td_helpdesk_settings' );

		parent::tear_down();
	}

	/**
	 * Through capture_json rather than by calling the handler directly: it
	 * emits JSON and then wp_die()s, and TD_Ajax_TestCase is what turns that
	 * into a return value instead of an aborted test.
	 */
	private function save( array $data ): array {
		$_POST = $_REQUEST = [
			'nonce' => wp_create_nonce( 'thrivedesk-nonce' ),
			'data'  => array_merge( [ 'td_helpdesk_api_key' => 'REAL-KEY-1234567890' ], $data ),
		];

		$body = $this->capture_json(
			static function () {
				\ThriveDesk\Conversations\Conversation::instance()->td_save_helpdesk_form();
			}
		);

		$this->assertSame( 'success', $body['status'] ?? null, 'the save has to have landed for the rest to mean anything' );

		return (array) get_option( 'td_helpdesk_settings' );
	}

	public function test_ticking_it_is_remembered() {
		$this->assertSame( 1, $this->save( [ 'td_helpdesk_search_required' => '1' ] )['td_helpdesk_search_required'] );
	}

	/**
	 * The half a checkbox always gets wrong: an unticked box posts nothing, so
	 * the absence has to be written down as a decision.
	 */
	public function test_unticking_it_is_remembered_too() {
		$this->save( [ 'td_helpdesk_search_required' => '1' ] );

		$saved = $this->save( [] );

		$this->assertArrayHasKey( 'td_helpdesk_search_required', $saved );
		$this->assertSame( 0, $saved['td_helpdesk_search_required'] );
	}

	private function modal(): string {
		ob_start();
		thrivedesk_view( 'shortcode/modal' );

		return (string) ob_get_clean();
	}

	public function test_the_modal_tells_the_script_which_rule_applies() {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_search_required' => 1 ] );
		$this->assertStringContainsString( 'data-td-search-required="1"', $this->modal() );

		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_search_required' => 0 ] );
		$this->assertStringContainsString( 'data-td-search-required="0"', $this->modal() );

		// An install that predates the setting is not opted in.
		update_option( 'td_helpdesk_settings', [] );
		$this->assertStringContainsString( 'data-td-search-required="0"', $this->modal() );
	}

	/**
	 * The button is moved between the footer and the empty state rather than
	 * rendered twice - two would be two elements with the same id, and every
	 * other handler refers to that one.
	 */
	public function test_there_is_one_ticket_button_and_one_place_for_it_to_move_to() {
		$html = $this->modal();

		$this->assertSame( 1, substr_count( $html, 'id="td-new-ticket-url"' ) );
		$this->assertSame( 1, substr_count( $html, 'id="td-search-empty-cta"' ) );
		$this->assertSame( 1, substr_count( $html, 'id="td-modal-footer-note"' ) );

		// The nudge waits for results; there is nothing to be unhappy with
		// before a search has run.
		$this->assertStringContainsString( 'id="td-modal-footer-note" hidden', $html );
	}

	/**
	 * The Support tab control belongs to a connected WooCommerce, not merely an
	 * installed one. The tab shows a customer their tickets, so before the
	 * integration is connected there is nothing behind it - a switch that turns
	 * on a blank tab is worse than no switch.
	 *
	 * It still has to render whenever it can, because the save handler reads it
	 * by class: a field that stops rendering saves empty and wipes what was
	 * chosen.
	 */
	private function portal_tab(): string {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'k' ] );
		update_option( 'td_helpdesk_verified', true );

		add_filter( 'pre_http_request', static fn() => [
			'headers'  => [],
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => wp_json_encode( [] ),
		], 10, 3 );

		ob_start();
		include THRIVEDESK_DIR . '/includes/views/partials/settings.php';
		$html = (string) ob_get_clean();

		remove_all_filters( 'pre_http_request' );
		delete_option( 'td_helpdesk_verified' );

		return $html;
	}

	public function test_an_unconnected_woocommerce_is_offered_nothing() {
		update_option( 'thrivedesk_options', [ 'woocommerce' => [ 'api_token' => '', 'connected' => false ] ] );

		$html = $this->portal_tab();

		delete_option( 'thrivedesk_options' );

		$this->assertStringNotContainsString( 'class="td-woo"', $html );
		$this->assertStringNotContainsString( 'class="td_user_account_pages"', $html );
	}

	public function test_a_connected_woocommerce_gets_the_support_tab() {
		if ( ! defined( 'WC_VERSION' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active in this environment' );
		}

		update_option( 'thrivedesk_options', [ 'woocommerce' => [ 'api_token' => 'tok', 'connected' => true ] ] );

		$html = $this->portal_tab();

		delete_option( 'thrivedesk_options' );

		$this->assertStringContainsString( 'class="td-woo"', $html );
		$this->assertStringContainsString( 'class="td_user_account_pages"', $html );

		// The part people miss: the tab replaces the shortcode.
		$this->assertStringContainsString( '<strong>shortcode</strong>', $html );
	}

	public function test_the_setting_is_on_the_portal_tab() {
		update_option(
			'td_helpdesk_settings',
			[ 'td_helpdesk_api_key' => 'k', 'td_helpdesk_page_id' => 12, 'td_helpdesk_search_required' => 1 ]
		);
		update_option( 'td_helpdesk_verified', true );

		add_filter( 'pre_http_request', static fn() => [
			'headers'  => [],
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => wp_json_encode( [] ),
		], 10, 3 );

		ob_start();
		include THRIVEDESK_DIR . '/includes/views/partials/settings.php';
		$html = (string) ob_get_clean();

		remove_all_filters( 'pre_http_request' );
		delete_option( 'td_helpdesk_verified' );

		$this->assertStringContainsString( 'id="td_helpdesk_search_required"', $html );
		$this->assertMatchesRegularExpression(
			'/id="td_helpdesk_search_required"[^>]*checked/',
			$html,
			'a stored 1 has to come back ticked'
		);
	}
}
