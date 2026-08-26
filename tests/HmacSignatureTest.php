<?php
/**
 * Focused characterization of the ?listener= HMAC verification in
 * ThriveDesk\Api::verify_token(), which is the source of truth for the
 * signing scheme these tests pin.
 *
 * Outcomes are asserted via the JSON body (the HTTP status code is not
 * observable under the PHPUnit CLI — see TD_Ajax_TestCase).
 *
 * @package ThriveDesk\Tests
 */

class HmacSignatureTest extends TD_Ajax_TestCase {

	const TOKEN = 'shared-secret';

	public function set_up() {
		parent::set_up();
		update_option( 'thrivedesk_options', [ 'edd' => [ 'api_token' => self::TOKEN ] ] );
	}

	private function dispatch( array $payload, string $signature ): array {
		$_GET                           = $payload;
		$_POST                          = [];
		$_REQUEST                       = $payload;
		$_SERVER['HTTP_X_TD_SIGNATURE'] = $signature;

		return $this->capture_json(
			function () {
				\ThriveDesk\Api::instance()->api_listener();
			}
		);
	}

	public function test_valid_signature_is_accepted() {
		$payload = [
			'listener' => 'thrivedesk',
			'plugin'   => 'edd',
			'action'   => 'connect',
		];
		$body    = $this->dispatch( $payload, td_test_sign_payload( $payload, self::TOKEN ) );

		$this->assertSame( 'Site connected successfully', $body['message'] );
	}

	public function test_tampered_payload_is_rejected() {
		$payload   = [
			'listener' => 'thrivedesk',
			'plugin'   => 'edd',
			'action'   => 'connect',
		];
		$signature = td_test_sign_payload( $payload, self::TOKEN );

		// Sign one payload, then send a different one.
		$payload['action'] = 'disconnect';
		$body              = $this->dispatch( $payload, $signature );

		$this->assertSame( 'Request unauthorized', $body['message'] );
	}

	/**
	 * Send a split request the way wp_magic_quotes() assembles one: the query
	 * string and the body are merged into $_REQUEST, with POST winning.
	 */
	private function dispatch_split( array $get, array $post, string $signature ): array {
		$_GET                           = $get;
		$_POST                          = $post;
		$_REQUEST                       = array_merge( $get, $post ); // exactly what wp_magic_quotes() does.
		$_SERVER['HTTP_X_TD_SIGNATURE'] = $signature;

		return $this->capture_json(
			function () {
				\ThriveDesk\Api::instance()->api_listener();
			}
		);
	}

	public function test_a_connect_signature_cannot_execute_disconnect() {
		// The signature covered $_REQUEST (POST wins the merge) while the
		// dispatcher executed $_GET, so a signed `action=connect` in the body ran
		// `action=disconnect` from the query string and answered "Site has been
		// disconnected". Now the verified array is the executed array: only the
		// action that was actually signed runs.
		update_option(
			'thrivedesk_options',
			[ 'edd' => [ 'api_token' => self::TOKEN, 'connected' => true ] ]
		);

		$signed    = [
			'listener' => 'thrivedesk',
			'plugin'   => 'edd',
			'action'   => 'connect',
		];
		$signature = td_test_sign_payload( $signed, self::TOKEN );

		$body = $this->dispatch_split(
			[
				'listener' => 'thrivedesk',
				'plugin'   => 'edd',
				'action'   => 'disconnect',
			],
			[ 'action' => 'connect' ],
			$signature
		);

		$this->assertSame( 'Site connected successfully', $body['message'] ?? null );

		$opts = get_option( 'thrivedesk_options' );
		$this->assertTrue( $opts['edd']['connected'], 'the query-string disconnect must not have run' );
		$this->assertSame( self::TOKEN, $opts['edd']['api_token'], 'disconnect() blanks the token; it must still be set' );
	}

	public function test_signature_over_the_query_string_view_alone_is_rejected() {
		// The mirror image: the dispatcher no longer reads $_GET behind the
		// HMAC's back, so a signature computed over just the query-string view of
		// a GET+POST request does not match the contract that actually runs.
		$get  = [
			'listener' => 'thrivedesk',
			'plugin'   => 'edd',
			'action'   => 'disconnect',
		];
		$body = $this->dispatch_split( $get, [ 'action' => 'connect' ], td_test_sign_payload( $get, self::TOKEN ) );

		$this->assertSame( 'Request unauthorized', $body['message'] ?? null );
	}

	public function test_wrong_token_is_rejected() {
		$payload = [
			'listener' => 'thrivedesk',
			'plugin'   => 'edd',
			'action'   => 'connect',
		];
		$body    = $this->dispatch( $payload, td_test_sign_payload( $payload, 'not-the-token' ) );

		$this->assertSame( 'Request unauthorized', $body['message'] );
	}

	public function test_raw_signed_value_that_sanitization_alters_is_accepted() {
		// The sender signs the *raw* value. verify_token() used to hash the
		// sanitize_text_field()'d value instead, so any legitimate request whose
		// content collapses under sanitization ('multiple    spaces' becomes
		// 'multiple spaces') was rejected — while the handlers went on to use
		// sanitize_key()/sanitize_email(), which rewrite the value differently
		// again, so the hashed value and the executed value could diverge.
		// Phase 1 landed: the raw payload is hashed and sanitization happens at
		// the point of use, so this now verifies.
		//
		// `reason` rather than an arbitrary key because only SIGNED_PARAMS are
		// hashed; a foreign key would be dropped before the HMAC.
		$payload = [
			'listener' => 'thrivedesk',
			'plugin'   => 'edd',
			'action'   => 'connect',
			'reason'   => 'multiple    spaces',
		];

		$raw_signature = hash_hmac( 'SHA1', wp_json_encode( $payload ), self::TOKEN );
		$body          = $this->dispatch( $payload, $raw_signature );

		$this->assertSame( 'Site connected successfully', $body['message'] );
	}

	public function test_sanitized_signature_is_no_longer_accepted() {
		// The mirror of the above: a signature over the sanitized value is not
		// what the SaaS produces and must not verify, or both spellings of the
		// payload would authorize and the "signed == executed" property is gone.
		$payload = [
			'listener' => 'thrivedesk',
			'plugin'   => 'edd',
			'action'   => 'connect',
			'reason'   => 'multiple    spaces',
		];

		$sanitized              = $payload;
		$sanitized['reason']    = sanitize_text_field( $payload['reason'] );
		$this->assertNotSame( $payload['reason'], $sanitized['reason'], 'fixture must actually collapse' );

		$body = $this->dispatch( $payload, hash_hmac( 'SHA1', wp_json_encode( $sanitized ), self::TOKEN ) );

		$this->assertSame( 'Request unauthorized', $body['message'] );
	}

	public function test_empty_api_token_rejects_forged_signature() {
		// Active-but-never-connected and post-disconnect both leave api_token ''.
		// hash_hmac() with an empty key is a value anyone can reproduce, so a
		// signature computed against it is forgeable. It must never authorize.
		update_option( 'thrivedesk_options', [ 'edd' => [ 'api_token' => '' ] ] );
		$payload = [
			'listener' => 'thrivedesk',
			'plugin'   => 'edd',
			'action'   => 'connect',
		];
		// The attacker signs with the known-empty key, exactly as verify_token would.
		$forged = td_test_sign_payload( $payload, '' );
		$body   = $this->dispatch( $payload, $forged );

		$this->assertSame( 'Request unauthorized', $body['message'] );
	}

	public function test_foreign_request_params_are_ignored() {
		// A store plugin can add its own query var to every front-end request,
		// including our ?listener= endpoint. HUSKY/WOOF's woof_parse_query is the
		// real-world case: it broke both connect and the conversation widget's
		// data calls on a customer store; utm_/lang stand in for the rest
		// (cache-busters, analytics, multilingual). verify_token() runs the same
		// for every action (connect and every widget/data call alike), so filtering
		// on the connect payload here covers all of them: the SaaS signs only the
		// contract params, so verify_token() must hash only those and ignore
		// anything else in $_REQUEST, or the signature never matches.
		$signed    = [
			'listener' => 'thrivedesk',
			'plugin'   => 'edd',
			'action'   => 'connect',
		];
		$signature = td_test_sign_payload( $signed, self::TOKEN );

		$received = array_merge(
			$signed,
			[
				'woof_parse_query' => '1',
				'utm_source'       => 'newsletter',
				'lang'             => 'en',
			]
		);
		$body = $this->dispatch( $received, $signature );

		$this->assertSame( 'Site connected successfully', $body['message'] );
	}

	public function test_plugin_activation_is_not_observable_without_a_signature() {
		// is_plugin_active() used to run before verify_token(), so four
		// unauthenticated requests (?plugin=edd, woocommerce, fluentcrm,
		// autonami) told an anonymous caller which commerce/CRM stack the store
		// runs. EDD is active here (the stubbed EDD() function), FluentCRM never
		// is, and 'doesnotexist' is not an integration at all — unsigned, all
		// three must be indistinguishable.
		$probe = function ( string $plugin ): array {
			return $this->dispatch(
				[
					'listener' => 'thrivedesk',
					'plugin'   => $plugin,
					'action'   => 'connect',
				],
				''
			);
		};

		$active = $probe( 'edd' );

		$this->assertSame( [ 'message' => 'Request unauthorized' ], $active );
		$this->assertSame( $active, $probe( 'fluentcrm' ), 'an inactive integration must not be distinguishable' );
		$this->assertSame( $active, $probe( 'doesnotexist' ), 'an unknown plugin key must not be distinguishable' );
	}

	public function test_data_action_requires_connected_integration() {
		// After the admin starts connect the token exists but 'connected' stays
		// false until the SaaS calls back. In that window only connect/disconnect
		// may run: a data or mutation action must be rejected even with a valid
		// signature, so a leaked token can't drive store changes pre-connection.
		update_option(
			'thrivedesk_options',
			[ 'woocommerce' => [ 'api_token' => self::TOKEN, 'connected' => false ] ]
		);
		$payload = [
			'listener' => 'thrivedesk',
			'plugin'   => 'woocommerce',
			'action'   => 'get_woocommerce_order_status_list',
		];
		$sig  = td_test_sign_payload( $payload, self::TOKEN );
		$body = $this->dispatch( $payload, $sig );

		$this->assertSame( 'Request unauthorized', $body['message'] );
	}
}
