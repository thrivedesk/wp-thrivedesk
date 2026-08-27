<?php
/**
 * Characterization tests for includes/helper.php, including the migrate-on-read
 * settings behavior that Phase 2 will move to a run-once routine.
 *
 * @package ThriveDesk\Tests
 */

class HelperFunctionsTest extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( 'thrivedesk_options' );
		delete_option( 'td_helpdesk_options' );
		delete_option( 'td_helpdesk_settings' );
		parent::tear_down();
	}

	/**
	 * thrivedesk_view() used require_once, so a template rendered a second time
	 * in the same request produced nothing at all. That is not a hypothetical:
	 * the settings view renders the overview view, and any second render - a
	 * loop, or two tests in one process - came back empty with no error.
	 */
	public function test_a_view_can_be_rendered_more_than_once_per_request() {
		$render = static function () {
			ob_start();
			thrivedesk_view( 'icons/close' );

			return trim( (string) ob_get_clean() );
		};

		$first  = $render();
		$second = $render();

		$this->assertNotSame( '', $first, 'the view rendered nothing at all' );
		$this->assertSame( $first, $second, 'a second render must produce the same markup, not nothing' );
	}

	public function test_thrivedesk_options_returns_array_or_empty() {
		delete_option( 'thrivedesk_options' );
		$this->assertSame( [], thrivedesk_options() );

		update_option( 'thrivedesk_options', [ 'edd' => [ 'connected' => true ] ] );
		$this->assertSame( [ 'edd' => [ 'connected' => true ] ], thrivedesk_options() );

		update_option( 'thrivedesk_options', 'not-an-array' );
		$this->assertSame( [], thrivedesk_options() );
	}

	public function test_get_td_helpdesk_options_guards_non_array() {
		delete_option( 'td_helpdesk_options' );
		$this->assertSame( [], get_td_helpdesk_options() );

		update_option( 'td_helpdesk_options', 'nope' );
		$this->assertSame( [], get_td_helpdesk_options() );
	}

	public function test_settings_returned_as_is_when_new_format_present() {
		$settings = [
			'td_helpdesk_api_key' => 'newkey',
			'foo'                 => 'bar',
		];
		update_option( 'td_helpdesk_settings', $settings );
		// Old options present, but must be ignored once new settings exist.
		update_option( 'td_helpdesk_options', [ 'td_helpdesk_api_key' => 'oldkey' ] );

		$this->assertSame( $settings, get_td_helpdesk_settings() );
	}

	public function test_settings_migrated_from_legacy_options_on_read() {
		delete_option( 'td_helpdesk_settings' );
		update_option(
			'td_helpdesk_options',
			[
				'td_helpdesk_api_key'  => 'oldkey',
				'td_helpdesk_inbox_id' => '42',
			]
		);

		$settings = get_td_helpdesk_settings();

		$this->assertSame( 'oldkey', $settings['td_helpdesk_api_key'] );
		$this->assertSame( '42', $settings['td_helpdesk_inbox_id'] );
		// Missing required keys are backfilled with empty strings.
		$this->assertArrayHasKey( 'td_helpdesk_assistant_id', $settings );
		$this->assertSame( '', $settings['td_helpdesk_assistant_id'] );

		// The read has the side effect of persisting the merged settings.
		$this->assertSame( $settings, get_option( 'td_helpdesk_settings' ) );
	}

	public function test_settings_empty_when_nothing_stored() {
		delete_option( 'td_helpdesk_settings' );
		delete_option( 'td_helpdesk_options' );
		$this->assertSame( [], get_td_helpdesk_settings() );
	}

	public function test_get_gravatar_url_normalizes_email() {
		$expected_hash = md5( 'ada@example.com' );
		$this->assertSame(
			"https://www.gravatar.com/avatar/{$expected_hash}?s=80&d=404",
			get_gravatar_url( '  Ada@Example.com ' )
		);
		$this->assertStringContainsString( '?s=200', get_gravatar_url( 'x@y.com', 200 ) );
	}

	public function test_diff_for_humans_empty_and_past() {
		$this->assertSame( 'Unknown time', diff_for_humans( '' ) );

		$past  = gmdate( 'Y-m-d H:i:s', time() - 5400 ); // 1h30m ago.
		$human = diff_for_humans( $past );
		$this->assertStringEndsWith( 'ago', $human );
		$this->assertStringContainsString( 'hour', $human );
	}

	public function test_asset_version_falls_back_to_plugin_version() {
		// A path that is not in the mix manifest always yields the plugin version.
		$this->assertSame(
			THRIVEDESK_VERSION,
			thrivedesk_get_asset_version( '/js/__not_in_manifest__.js' )
		);
	}
}
