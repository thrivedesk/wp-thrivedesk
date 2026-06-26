<?php
/**
 * Golden-file characterization of the ?listener=thrivedesk dispatcher in
 * ThriveDesk\Api. Captures the JSON body for representative routes so the
 * SaaS-facing contract can't change silently. (The HTTP status code is not
 * observable under the PHPUnit CLI — see TD_Ajax_TestCase — but each outcome's
 * message is distinct, so the body alone pins the behavior.)
 *
 * Connect/disconnect activate EDD via the stubbed EDD() function (its
 * is_plugin_active() only checks function_exists('EDD')) and sign the request
 * with the production algorithm (see tests/includes/listener-helpers.php).
 *
 * NOTE: some captured behaviors are the *current* ones. Phase 1 moves token
 * verification ahead of plugin introspection, so the "inactive plugin" body
 * changes from the "isn't installed or active" leak to the generic
 * "Request unauthorized"; the affected golden is then regenerated on purpose
 * (TD_UPDATE_GOLDEN=1).
 *
 * @package ThriveDesk\Tests
 */

class ListenerGoldenTest extends TD_Ajax_TestCase {

	const TOKEN = 'test-secret-token';

	private function golden( string $name, array $get, array $server = [] ): void {
		$_GET     = $get;
		$_POST    = [];
		$_REQUEST = $get;
		foreach ( $server as $k => $v ) {
			$_SERVER[ $k ] = $v;
		}

		$actual = $this->capture_json(
			function () {
				\ThriveDesk\Api::instance()->api_listener();
			}
		);

		$dir  = __DIR__ . '/golden/listener';
		$file = $dir . '/' . $name . '.json';

		if ( getenv( 'TD_UPDATE_GOLDEN' ) || ! file_exists( $file ) ) {
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0777, true );
			}
			file_put_contents( $file, wp_json_encode( $actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
		}

		$expected = json_decode( (string) file_get_contents( $file ), true );
		$this->assertSame( $expected, $actual, "Listener golden '{$name}' changed." );
	}

	public function test_invalid_plugin() {
		$this->golden(
			'invalid-plugin',
			[
				'listener' => 'thrivedesk',
				'plugin'   => 'doesnotexist',
			]
		);
	}

	public function test_inactive_plugin_currently_leaks_before_auth() {
		// WooCommerce is autoloaded but WC() is undefined, so it reads inactive.
		$this->golden(
			'inactive-plugin',
			[
				'listener' => 'thrivedesk',
				'plugin'   => 'woocommerce',
				'action'   => 'connect',
			]
		);
	}

	public function test_missing_signature_is_unauthorized() {
		$this->golden(
			'missing-signature',
			[
				'listener' => 'thrivedesk',
				'plugin'   => 'edd',
				'action'   => 'connect',
			]
		);
	}

	public function test_connect_with_valid_signature() {
		update_option( 'thrivedesk_options', [ 'edd' => [ 'api_token' => self::TOKEN ] ] );
		$payload = [
			'listener' => 'thrivedesk',
			'plugin'   => 'edd',
			'action'   => 'connect',
		];
		$sig     = td_test_sign_payload( $payload, self::TOKEN );

		$this->golden( 'connect-success', $payload, [ 'HTTP_X_TD_SIGNATURE' => $sig ] );

		$opts = get_option( 'thrivedesk_options' );
		$this->assertTrue( $opts['edd']['connected'] );
	}

	public function test_disconnect_with_valid_signature() {
		update_option(
			'thrivedesk_options',
			[
				'edd' => [
					'api_token' => self::TOKEN,
					'connected' => true,
				],
			]
		);
		$payload = [
			'listener' => 'thrivedesk',
			'plugin'   => 'edd',
			'action'   => 'disconnect',
		];
		$sig     = td_test_sign_payload( $payload, self::TOKEN );

		$this->golden( 'disconnect-success', $payload, [ 'HTTP_X_TD_SIGNATURE' => $sig ] );

		$opts = get_option( 'thrivedesk_options' );
		$this->assertFalse( $opts['edd']['connected'] );
		$this->assertSame( '', $opts['edd']['api_token'] );
	}

	public function test_listener_param_mismatch_produces_no_output() {
		$_GET     = [ 'listener' => 'somethingelse' ];
		$_REQUEST = $_GET;

		$body = $this->capture_json(
			function () {
				\ThriveDesk\Api::instance()->api_listener();
			}
		);

		$this->assertSame( [], $body );
	}
}
