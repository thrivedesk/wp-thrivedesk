<?php
/**
 * Disconnecting the site from its ThriveDesk account.
 *
 * The half that is easy to get wrong is the integrations. Each connected
 * integration holds its own `api_token`, issued to the org id that was on file
 * at the time, and Api::api_listener() honours that token without ever
 * consulting the helpdesk API key. So forgetting the key alone would leave the
 * old workspace able to keep reading orders, subscriptions, contacts and posts
 * from a site whose owner believes they have disconnected.
 *
 * The other half is restraint: what describes this site rather than the
 * workspace has to survive, or every disconnect costs the owner their portal
 * page, their post type selection and their hidden routes.
 *
 * @package ThriveDesk\Tests
 */

use ThriveDesk\Services\WorkspaceService;

class DisconnectAccountTest extends TD_Ajax_TestCase {

	public function set_up() {
		parent::set_up();

		// Rendering a connected screen fetches assistants, inboxes and the
		// knowledge base. Left alone these tests would be timing the network.
		add_filter( 'pre_http_request', [ $this, 'block_http' ], 10, 3 );

		update_option(
			'td_helpdesk_settings',
			[
				// Named in the workspace being left.
				'td_helpdesk_api_key'       => 'REAL-KEY-1234567890',
				'td_helpdesk_assistant_id'  => 'assistant-abc',
				'td_helpdesk_inbox_id'      => 'inbox-def',
				'td_knowledgebase_slug'     => 'help',
				// True of this site whoever it is connected to.
				'td_helpdesk_page_id'       => 42,
				'td_helpdesk_post_types'    => [ 'post', 'page' ],
				'td_assistant_route_list'   => [ '/cart/' ],
				'td_user_account_pages'     => [ 'woocommerce' ],
			]
		);
		update_option( 'td_helpdesk_verified', true );
		update_option( 'td_helpdesk_system_info', [ 'id' => 'org-1', 'company' => 'Woo Demo' ] );
		update_option(
			'thrivedesk_options',
			[
				'edd'         => [ 'api_token' => 'EDD-TOKEN', 'connected' => true ],
				'woocommerce' => [ 'api_token' => 'WOO-TOKEN', 'connected' => true ],
			]
		);
	}

	public function block_http() {
		return [
			'headers'  => [],
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => wp_json_encode( [] ),
		];
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'block_http' ], 10 );
		WorkspaceService::flush();
		wp_clear_scheduled_hook( WorkspaceService::REFRESH_HOOK );

		foreach ( [ 'td_helpdesk_settings', 'td_helpdesk_verified', 'td_helpdesk_system_info', 'thrivedesk_options' ] as $option ) {
			delete_option( $option );
		}

		parent::tear_down();
	}

	public function test_the_site_is_no_longer_connected() {
		$this->assertTrue( thrivedesk_is_connected(), 'precondition' );

		\ThriveDesk\Admin::forget_account();

		$this->assertFalse( thrivedesk_is_connected() );
		$this->assertFalse( \ThriveDesk\Admin::get_api_verification_status() );
	}

	public function test_everything_naming_the_old_workspace_is_cleared() {
		\ThriveDesk\Admin::forget_account();

		$settings = get_option( 'td_helpdesk_settings' );

		foreach ( [ 'td_helpdesk_api_key', 'td_helpdesk_assistant_id', 'td_helpdesk_inbox_id', 'td_knowledgebase_slug' ] as $key ) {
			$this->assertArrayNotHasKey( $key, $settings, "$key names something in the workspace being left" );
		}

		$this->assertFalse( get_option( 'td_helpdesk_system_info' ) );
	}

	public function test_settings_that_describe_this_site_survive() {
		\ThriveDesk\Admin::forget_account();

		$settings = get_option( 'td_helpdesk_settings' );

		$this->assertSame( 42, $settings['td_helpdesk_page_id'] ?? null );
		$this->assertSame( [ 'post', 'page' ], $settings['td_helpdesk_post_types'] ?? null );
		$this->assertSame( [ '/cart/' ], $settings['td_assistant_route_list'] ?? null );
		$this->assertSame( [ 'woocommerce' ], $settings['td_user_account_pages'] ?? null );
	}

	/**
	 * The reason this is a disconnect and not just a forgotten key. An empty
	 * api_token is rejected by Api::verify_token() before anything is
	 * dispatched - see HmacSignatureTest.
	 */
	public function test_every_integration_token_is_revoked() {
		\ThriveDesk\Admin::forget_account();

		foreach ( get_option( 'thrivedesk_options' ) as $slug => $integration ) {
			$this->assertSame( '', $integration['api_token'], "$slug must not keep a usable token" );
			$this->assertFalse( $integration['connected'], "$slug must not read as connected" );
		}
	}

	public function test_the_cached_workspace_summary_does_not_outlive_the_connection() {
		update_option( WorkspaceService::SUMMARY_OPTION, [ 'connected' => true, 'checked_at' => time() ] );

		\ThriveDesk\Admin::forget_account();

		$this->assertFalse( get_option( WorkspaceService::SUMMARY_OPTION ) );
	}

	public function test_cached_remote_lists_are_dropped() {
		set_transient( 'thrivedesk_assistants_' . md5( 'REAL-KEY-1234567890' ), [ 'assistants' => [ 1 ] ], HOUR_IN_SECONDS );
		set_transient( 'thrivedesk_knowledgebase', [ 1 ], HOUR_IN_SECONDS );

		\ThriveDesk\Admin::forget_account();

		$this->assertFalse( get_transient( 'thrivedesk_assistants_' . md5( 'REAL-KEY-1234567890' ) ) );
		$this->assertFalse( get_transient( 'thrivedesk_knowledgebase' ) );
	}

	/** The screen has to follow, or the card would still offer to disconnect. */
	public function test_the_screen_goes_back_to_asking_for_a_key() {
		\ThriveDesk\Admin::forget_account();

		ob_start();
		\ThriveDesk\Admin::instance()->load_pages();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="td-setup-split"', $html );
		$this->assertStringNotContainsString( 'id="td-disconnect-account"', $html );
	}

	public function test_the_disconnect_control_is_on_a_connected_screen() {
		ob_start();
		\ThriveDesk\Admin::instance()->load_pages();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="td-disconnect-account"', $html );
	}

	private function call_handler(): array {
		return $this->capture_json(
			static function () {
				\ThriveDesk\Admin::instance()->ajax_disconnect_account();
			}
		);
	}

	public function test_a_subscriber_cannot_disconnect_the_site() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$_POST = $_REQUEST = [ 'data' => [ 'nonce' => wp_create_nonce( 'thrivedesk-plugin-action' ) ] ];

		$this->assertSame( false, $this->call_handler()['success'] ?? null );
		$this->assertTrue( thrivedesk_is_connected(), 'the connection must survive a rejected request' );
	}

	public function test_an_admin_without_a_nonce_cannot_disconnect_the_site() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$_POST = $_REQUEST = [ 'data' => [] ];

		$this->assertSame( false, $this->call_handler()['success'] ?? null );
		$this->assertTrue( thrivedesk_is_connected() );
	}

	public function test_an_admin_with_a_nonce_disconnects_the_site() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$_POST = $_REQUEST = [ 'data' => [ 'nonce' => wp_create_nonce( 'thrivedesk-plugin-action' ) ] ];

		$this->assertTrue( $this->call_handler()['success'] ?? null );
		$this->assertFalse( thrivedesk_is_connected() );
	}
}
