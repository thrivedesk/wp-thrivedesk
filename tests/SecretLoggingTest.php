<?php
/**
 * Standing rule: never log secrets/credentials. The connect handler used to
 * dump the entire connect POST (which carries the CSRF nonce) to the error log
 * before doing anything else, so even a rejected request leaked it.
 *
 * @package ThriveDesk\Tests
 */

class SecretLoggingTest extends TD_Ajax_TestCase {

	/**
	 * Drive connect and hand back whatever landed in the PHP error log.
	 */
	private function connect_and_capture_log( array $post ): string {
		$log      = tempnam( sys_get_temp_dir(), 'td-errorlog-' );
		$previous = ini_set( 'error_log', $log );

		$this->capture_json(
			function () use ( $post ) {
				$_POST    = $post;
				$_REQUEST = $post;
				\ThriveDesk\Admin::instance()->ajax_connect_plugin();
			}
		);

		$logged = (string) file_get_contents( $log );
		ini_set( 'error_log', $previous );
		@unlink( $log );

		return $logged;
	}

	public function test_a_rejected_connect_does_not_log_the_request_payload() {
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );

		$logged = $this->connect_and_capture_log(
			[ 'data' => [ 'plugin' => 'edd', 'nonce' => 'super-secret-nonce' ] ]
		);

		$this->assertStringNotContainsString( 'super-secret-nonce', $logged, 'a rejected connect must not log the request payload' );
	}

	/**
	 * The rejected case alone stops at the capability check, so it can't see a
	 * log statement reintroduced further down. Run the authorized path too: it
	 * is the one that has the nonce, the generated api_token, and the connect
	 * payload in hand.
	 */
	public function test_an_authorized_connect_logs_neither_the_nonce_nor_the_api_token() {
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );

		$nonce  = wp_create_nonce( 'thrivedesk-plugin-action' );
		$logged = $this->connect_and_capture_log(
			[ 'data' => [ 'plugin' => 'edd', 'nonce' => $nonce ] ]
		);

		$api_token = get_option( 'thrivedesk_options' )['edd']['api_token'] ?? '';
		$this->assertNotEmpty( $api_token, 'connect should have stored a token, i.e. the handler body ran' );

		$this->assertStringNotContainsString( $nonce, $logged, 'connect must not log the nonce' );
		$this->assertStringNotContainsString( $api_token, $logged, 'connect must not log the api_token' );
	}
}
