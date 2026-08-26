<?php
/**
 * 'td_helpdesk_verified' decides whether the admin shows the settings screen or
 * sends the site owner through a fresh authorization, so it has to describe the
 * key actually on file. Two ways it can lie:
 *
 * - clearing it because the request never reached ThriveDesk (DNS/SSL/timeout,
 *   e.g. while a new domain is still settling), which says nothing about the
 *   key and forces a pointless re-auth;
 * - clearing it because some *other* submitted key failed to verify, when the
 *   key on file was never touched.
 *
 * The second only became possible to state this cleanly once the handler
 * stopped writing the submitted key before checking it.
 *
 * @package ThriveDesk\Tests
 */

class ApiKeyVerificationStatusTest extends TD_Ajax_TestCase {

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );

		parent::tear_down();
	}

	/**
	 * @param array|WP_Error $response what the HTTP layer answers with
	 */
	private function verify( string $submitted_key, $response ): array {
		add_filter( 'pre_http_request', static fn() => $response );

		$post = [
			'nonce' => wp_create_nonce( 'thrivedesk-nonce' ),
			'data'  => [ 'td_helpdesk_api_key' => $submitted_key ],
		];

		$_POST = $_REQUEST = $post;

		return $this->capture_json(
			static function () {
				\ThriveDesk\Conversations\Conversation::instance()->td_verify_helpdesk_api_key();
			}
		);
	}

	private function connected_with( string $key ): void {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => $key ] );
		update_option( 'td_helpdesk_verified', true );
	}

	public function test_network_failure_leaves_a_verified_key_verified() {
		$this->connected_with( 'KEY-A' );

		$body = $this->verify( 'KEY-A', new WP_Error( 'http_request_failed', 'Could not resolve host' ) );

		$this->assertSame( 'error', $body['status'] ?? null, 'the caller still has to be told the check failed' );
		$this->assertTrue(
			\ThriveDesk\Admin::get_api_verification_status(),
			'an unreachable API says nothing about the key, so verification must survive it'
		);
	}

	public function test_server_error_leaves_a_verified_key_verified() {
		$this->connected_with( 'KEY-A' );

		$this->verify(
			'KEY-A',
			[
				'response' => [ 'code' => 500 ],
				'body'     => wp_json_encode( [ 'message' => 'Internal Server Error' ] ),
			]
		);

		$this->assertTrue(
			\ThriveDesk\Admin::get_api_verification_status(),
			'a 5xx is ThriveDesk failing, not the key being rejected'
		);
	}

	public function test_auth_rejection_clears_verification() {
		$this->connected_with( 'KEY-A' );

		$this->verify(
			'KEY-A',
			[
				'response' => [ 'code' => 401 ],
				'body'     => wp_json_encode( [ 'message' => 'Unauthenticated' ] ),
			]
		);

		$this->assertFalse(
			\ThriveDesk\Admin::get_api_verification_status(),
			'a 401 is the API rejecting the key itself, which must clear verification'
		);
	}

	/**
	 * The handler used to save the submitted key before checking it, so a key
	 * that never authenticated - including one an attacker pre-filled into the
	 * form via ?token= - was already the key on file by the time the check came
	 * back. Nothing is written until verification succeeds.
	 */
	public function test_a_rejected_key_is_never_stored() {
		$this->connected_with( 'KEY-A' );

		$this->verify(
			'ATTACKER-KEY',
			[
				'response' => [ 'code' => 401 ],
				'body'     => wp_json_encode( [ 'message' => 'Unauthenticated' ] ),
			]
		);

		$this->assertSame(
			'KEY-A',
			get_option( 'td_helpdesk_settings' )['td_helpdesk_api_key'],
			'a key the API rejected must never become the key on file'
		);
		$this->assertTrue(
			\ThriveDesk\Admin::get_api_verification_status(),
			'the working key on file is untouched, so its verified flag stands'
		);
	}

	public function test_a_network_failure_on_some_other_key_does_not_disconnect_the_site() {
		$this->connected_with( 'KEY-A' );

		$this->verify( 'KEY-B', new WP_Error( 'http_request_failed', 'Could not resolve host' ) );

		$this->assertSame(
			'KEY-A',
			get_option( 'td_helpdesk_settings' )['td_helpdesk_api_key'],
			'an unverified key must not replace the stored one'
		);
		$this->assertTrue(
			\ThriveDesk\Admin::get_api_verification_status(),
			'KEY-A is still the key on file and still verified, so its flag must survive'
		);
	}

	public function test_a_verified_key_becomes_the_key_on_file() {
		$this->connected_with( 'KEY-A' );

		$this->verify(
			'KEY-B',
			[
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'company' => [ 'id' => 'c1', 'name' => 'Acme' ] ] ),
			]
		);

		$this->assertSame(
			'KEY-B',
			get_option( 'td_helpdesk_settings' )['td_helpdesk_api_key'],
			'a key that authenticated is the one that gets saved'
		);
	}

	public function test_a_200_carrying_no_company_data_is_survivable() {
		$this->connected_with( 'KEY-A' );

		// A proxy in front of the API can answer 200 with a bare JSON string,
		// which the failure message used to be built by indexing.
		$body = $this->verify(
			'KEY-A',
			[
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( 'OK' ),
			]
		);

		$this->assertSame( 'error', $body['status'] ?? null, 'the caller still has to be told the check failed' );
		$this->assertFalse(
			\ThriveDesk\Admin::get_api_verification_status(),
			'a 200 that carries no company data means the key did not authenticate'
		);
	}

	public function test_successful_verification_sets_the_status() {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'KEY-A' ] );
		update_option( 'td_helpdesk_verified', false );

		$body = $this->verify(
			'KEY-B',
			[
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'company' => [ 'id' => 'c1', 'name' => 'Acme' ] ] ),
			]
		);

		$this->assertSame( 'success', $body['status'] ?? null );
		$this->assertTrue( \ThriveDesk\Admin::get_api_verification_status() );
	}
}
