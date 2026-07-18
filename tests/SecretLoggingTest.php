<?php
/**
 * Standing rule: never log secrets/credentials. The connect handler used to
 * dump the entire connect POST (which carries the CSRF nonce) to the error log
 * before doing anything else, so even a rejected request leaked it.
 *
 * @package ThriveDesk\Tests
 */

class SecretLoggingTest extends TD_Ajax_TestCase {

	public function test_connect_does_not_log_the_request_payload() {
		$log      = tempnam( sys_get_temp_dir(), 'td-errorlog-' );
		$previous = ini_set( 'error_log', $log );

		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );

		$this->capture_json(
			function () {
				$_POST    = [ 'data' => [ 'plugin' => 'edd', 'nonce' => 'super-secret-nonce' ] ];
				$_REQUEST = $_POST;
				\ThriveDesk\Admin::instance()->ajax_connect_plugin();
			}
		);

		$logged = (string) file_get_contents( $log );
		ini_set( 'error_log', $previous );
		@unlink( $log );

		$this->assertStringNotContainsString( 'super-secret-nonce', $logged, 'connect must not log the request payload' );
	}
}
