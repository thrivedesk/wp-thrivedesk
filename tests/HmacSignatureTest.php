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
}
