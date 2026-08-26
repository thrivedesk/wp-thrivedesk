<?php
/**
 * How the ?listener=thrivedesk dispatcher treats the values inside a signed
 * contract: types it never expects, and flags whose value it used to ignore.
 *
 * @package ThriveDesk\Tests
 */

class ListenerInputTest extends TD_Ajax_TestCase {

	const TOKEN = 'listener-secret';

	public function set_up() {
		parent::set_up();
		update_option(
			'thrivedesk_options',
			array( 'wppostsync' => array( 'api_token' => self::TOKEN, 'connected' => true ) )
		);
		update_option( 'td_helpdesk_settings', array( 'td_helpdesk_post_sync' => array( 'post' ) ) );
	}

	private function dispatch( array $payload ): array {
		$_GET                           = $payload;
		$_POST                          = array();
		$_REQUEST                       = $payload;
		$_SERVER['HTTP_X_TD_SIGNATURE'] = td_test_sign_payload( $payload, self::TOKEN );

		return $this->capture_json(
			function () {
				\ThriveDesk\Api::instance()->api_listener();
			}
		);
	}

	/**
	 * `query` was the one contract param that reached its handler with no
	 * sanitizing at all, and strtolower() fatals on an array. A signed
	 * `query[]=…` therefore took the request down instead of answering.
	 */
	public function test_an_array_valued_query_does_not_fatal() {
		$body = $this->dispatch(
			array(
				'listener' => 'thrivedesk',
				'plugin'   => 'wppostsync',
				'action'   => 'get_wppostsync_data',
				'query'    => array( 'a', 'b' ),
			)
		);

		$this->assertSame( 'Success', $body['message'] ?? null );
	}

	/**
	 * `isset($_REQUEST['shipping_param']) == 1` is true for any value the key
	 * holds, so a signed shipping_param=false still switched the (expensive,
	 * per-order) shipping lookups on. The value has to decide.
	 *
	 * @dataProvider shipping_param_values
	 */
	public function test_shipping_param_value_is_honoured( $sent, bool $expected ) {
		$plugin                 = \ThriveDesk\Plugins\WPPostSync::instance();
		$plugin->shipping_param = 'not-yet-set';

		$payload = array(
			'listener' => 'thrivedesk',
			'plugin'   => 'wppostsync',
			'action'   => 'get_plugin_data',
			'email'    => 'customer@example.com',
		);

		if ( null !== $sent ) {
			$payload['shipping_param'] = $sent;
		}

		$this->dispatch( $payload );

		$this->assertSame( $expected, $plugin->shipping_param );
	}

	public static function shipping_param_values(): array {
		return array(
			'literal false' => array( 'false', false ),
			'zero'          => array( '0', false ),
			'empty'         => array( '', false ),
			'literal true'  => array( 'true', true ),
			'one'           => array( '1', true ),
			'absent'        => array( null, false ),
		);
	}
}
