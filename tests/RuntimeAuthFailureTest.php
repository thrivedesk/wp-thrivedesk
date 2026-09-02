<?php
/**
 * A key that was verified once is verified forever, as far as the plugin is
 * concerned: nothing revisits 'td_helpdesk_verified' after the manual check.
 * So a key the site owner revoked in ThriveDesk, or one that expired, leaves
 * the admin reporting a healthy connection while every call fails - the
 * failure reaching only error_log and the portal.
 *
 * The outbound layer sees each rejection and is the only place that sees all
 * of them, so that is where the flag is cleared. Which rejections count is the
 * whole substance of it: a 401/403 is ThriveDesk refusing the key, while a
 * timeout, a 5xx or a Cloudflare block says nothing about the key and must not
 * cost a working site its connection.
 *
 * @package ThriveDesk\Tests
 */

class RuntimeAuthFailureTest extends WP_UnitTestCase {

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );

		parent::tear_down();
	}

	private function connected_with( string $key ): void {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => $key ] );
		update_option( 'td_helpdesk_verified', true );
	}

	/**
	 * @param array|WP_Error $response what the HTTP layer answers with
	 */
	private function answer_with( $response ): void {
		add_filter( 'pre_http_request', static fn() => $response );
	}

	private static function response( int $code, array $body ): array {
		return [
			'response' => [ 'code' => $code ],
			'body'     => wp_json_encode( $body ),
		];
	}

	/** A runtime read, the way the portal makes one. */
	private function fetch(): void {
		( new \ThriveDesk\Services\TDApiService() )->getRequest( THRIVEDESK_API_URL . '/v1/me' );
	}

	public function test_a_revoked_key_stops_reading_as_connected() {
		$this->connected_with( 'KEY-A' );
		$this->answer_with( self::response( 401, [ 'message' => 'Unauthenticated' ] ) );

		$this->fetch();

		$this->assertFalse(
			thrivedesk_is_connected(),
			'ThriveDesk rejected the key on file, so the admin must stop reporting the site as connected'
		);
	}

	public function test_a_forbidden_response_also_clears_it() {
		$this->connected_with( 'KEY-A' );
		$this->answer_with( self::response( 403, [ 'message' => 'You are not authorized to access this resource.' ] ) );

		$this->fetch();

		$this->assertFalse( \ThriveDesk\Admin::get_api_verification_status() );
	}

	public function test_a_server_error_leaves_a_working_connection_alone() {
		$this->connected_with( 'KEY-A' );
		$this->answer_with( self::response( 500, [ 'message' => 'Internal Server Error' ] ) );

		$this->fetch();

		$this->assertTrue(
			\ThriveDesk\Admin::get_api_verification_status(),
			'a 5xx is ThriveDesk failing, not the key being rejected'
		);
	}

	public function test_a_network_failure_leaves_a_working_connection_alone() {
		$this->connected_with( 'KEY-A' );
		$this->answer_with( new WP_Error( 'http_request_failed', 'Could not resolve host' ) );

		$this->fetch();

		$this->assertTrue(
			\ThriveDesk\Admin::get_api_verification_status(),
			'a request that never arrived says nothing about the key'
		);
	}

	public function test_a_cloudflare_block_leaves_a_working_connection_alone() {
		// The API answers 403 when the site's IP is blocked at the edge, which
		// is a firewall problem the site owner fixes by allowlisting - the key
		// is untouched, and re-authorizing would not help.
		$this->connected_with( 'KEY-A' );
		$this->answer_with(
			[
				'response' => [ 'code' => 403 ],
				'body'     => '<html><body>Attention Required! | Cloudflare</body></html>',
			]
		);

		$this->fetch();

		$this->assertTrue(
			\ThriveDesk\Admin::get_api_verification_status(),
			'an edge block is not the API rejecting the key'
		);
	}

	public function test_a_rejection_of_some_other_key_leaves_the_stored_one_alone() {
		// The verify screen checks a submitted key before it is ever stored.
		// That key being refused says nothing about the one on file.
		$this->connected_with( 'KEY-A' );
		$this->answer_with( self::response( 401, [ 'message' => 'Unauthenticated' ] ) );

		$service = new \ThriveDesk\Services\TDApiService();
		$service->setApiKey( 'SOMEONE-ELSES-KEY' );
		$service->getRequest( THRIVEDESK_API_URL . '/v1/me' );

		$this->assertTrue(
			\ThriveDesk\Admin::get_api_verification_status(),
			'only the key on file can lose its verified flag'
		);
	}

	public function test_a_rejected_customer_reply_clears_it_too() {
		// The reply path is a POST and shares the same failure handling; a
		// customer waiting on a support reply is the worst place for a dead
		// key to stay invisible.
		$this->connected_with( 'KEY-A' );
		$this->answer_with( self::response( 401, [ 'message' => 'Unauthenticated' ] ) );

		( new \ThriveDesk\Services\TDApiService() )->postRequest(
			THRIVEDESK_API_URL . '/v1/customer/conversations/abc-123/reply',
			[ 'message' => 'hello' ]
		);

		$this->assertFalse( \ThriveDesk\Admin::get_api_verification_status() );
	}

	public function test_the_portal_list_is_enough_to_detect_it() {
		// End to end through a real caller: a logged-in customer loading their
		// tickets is the traffic most sites have, and often the only traffic
		// that touches the API between admin visits.
		$this->connected_with( 'KEY-A' );
		$this->answer_with( self::response( 401, [ 'message' => 'Unauthenticated' ] ) );

		wp_set_current_user(
			self::factory()->user->create(
				[
					'role'       => 'subscriber',
					'user_email' => 'me@example.com',
				]
			)
		);

		\ThriveDesk\Conversations\Conversation::get_conversations();

		$this->assertFalse( thrivedesk_is_connected() );
	}
}
