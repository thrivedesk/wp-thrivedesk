<?php
/**
 * The settings AJAX handlers make outbound calls with a supplied API key and
 * write options/transients, so they must be admin-only. The 'thrivedesk-nonce'
 * is also created on the frontend portal for every logged-in user, so a nonce
 * alone is not authorization: the capability check is what actually gates them.
 *
 * @package ThriveDesk\Tests
 */

class AjaxSettingsAuthTest extends TD_Ajax_TestCase {

	private function handlers(): array {
		return [
			'thrivedesk_system_info'         => static function () { \ThriveDesk\Conversations\Conversation::instance()->thrivedesk_system_info(); },
			'thrivedesk_load_inboxes'        => static function () { \ThriveDesk\Inboxes\Inbox::instance()->thrivedesk_load_inboxes(); },
			'thrivedesk_load_assistants'     => static function () { \ThriveDesk\Assistants\Assistant::instance()->thrivedesk_load_assistants(); },
			'thrivedesk_check_portal_access' => static function () { \ThriveDesk\Services\PortalService::instance()->check_portal_access(); },
		];
	}

	private function call( callable $handler, array $post ): array {
		$_POST    = $post;
		$_REQUEST = $post;

		return $this->capture_json( $handler );
	}

	public function test_subscriber_is_rejected_even_with_a_valid_nonce() {
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );
		// A subscriber can obtain this exact nonce from the frontend portal.
		$nonce = wp_create_nonce( 'thrivedesk-nonce' );

		foreach ( $this->handlers() as $name => $handler ) {
			$body = $this->call(
				$handler,
				[
					'nonce' => $nonce,
					'data'  => [ 'td_helpdesk_api_key' => 'k' ],
				]
			);
			$this->assertSame( false, $body['success'] ?? null, "$name must reject a subscriber" );
		}
	}

	public function test_admin_without_a_nonce_is_rejected() {
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );

		foreach ( $this->handlers() as $name => $handler ) {
			$body = $this->call(
				$handler,
				[
					'data' => [ 'td_helpdesk_api_key' => 'k' ],
				]
			);
			$this->assertSame( false, $body['success'] ?? null, "$name must reject a missing nonce" );
		}
	}

	public function test_credential_mutators_reject_subscriber() {
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );

		$connect = $this->capture_json(
			function () {
				$_POST = $_REQUEST = [ 'data' => [ 'plugin' => 'edd', 'nonce' => wp_create_nonce( 'thrivedesk-plugin-action' ) ] ];
				\ThriveDesk\Admin::instance()->ajax_connect_plugin();
			}
		);
		$this->assertSame( false, $connect['success'] ?? null, 'connect must reject a subscriber' );

		$disconnect = $this->capture_json(
			function () {
				$_POST = $_REQUEST = [ 'data' => [ 'plugin' => 'edd', 'nonce' => wp_create_nonce( 'thrivedesk-plugin-action' ) ] ];
				\ThriveDesk\Admin::instance()->ajax_disconnect_plugin();
			}
		);
		$this->assertSame( false, $disconnect['success'] ?? null, 'disconnect must reject a subscriber' );

		$verify = $this->capture_json(
			function () {
				$_POST = $_REQUEST = [ 'nonce' => wp_create_nonce( 'thrivedesk-nonce' ), 'data' => [ 'td_helpdesk_api_key' => 'k' ] ];
				\ThriveDesk\Conversations\Conversation::instance()->td_verify_helpdesk_api_key();
			}
		);
		$this->assertSame( false, $verify['success'] ?? null, 'api_key_verify must reject a subscriber' );
	}
}
