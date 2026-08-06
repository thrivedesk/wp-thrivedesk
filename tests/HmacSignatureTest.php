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

	public function test_wrong_token_is_rejected() {
		$payload = [
			'listener' => 'thrivedesk',
			'plugin'   => 'edd',
			'action'   => 'connect',
		];
		$body    = $this->dispatch( $payload, td_test_sign_payload( $payload, 'not-the-token' ) );

		$this->assertSame( 'Request unauthorized', $body['message'] );
	}

	public function test_raw_signed_value_that_sanitization_alters_is_rejected_today() {
		// The sender signs the *raw* value, but verify_token() hashes the
		// sanitized value, so anything sanitize_text_field() rewrites fails to
		// verify. This documents the Phase 1 bug: a legitimately raw-signed
		// request whose content collapses under sanitization is rejected.
		// Phase 1 (hash raw, sanitize after) is expected to flip this to accept.
		$payload = [
			'listener' => 'thrivedesk',
			'plugin'   => 'edd',
			'action'   => 'connect',
			'note'     => 'multiple    spaces',
		];

		$raw_signature = hash_hmac( 'SHA1', wp_json_encode( $payload ), self::TOKEN );
		$body          = $this->dispatch( $payload, $raw_signature );

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
