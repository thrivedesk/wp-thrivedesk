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
 * whole substance of it, and 401 is the only one: that is ThriveDesk refusing
 * the credential - revoked, expired, or an org that has lost API access. A 403
 * is a key the API accepted being turned away from one endpoint, and a timeout,
 * a 5xx or a Cloudflare block says nothing about the key at all. None of those
 * may cost a working site its connection.
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

	public function test_a_forbidden_response_leaves_a_working_connection_alone() {
		// 403 is not a refused credential, and this is the regression guard for
		// the whole change. ThriveDesk answers 403 when a key it accepted is not
		// allowed at one endpoint - a key minted with a narrowed capability set
		// authenticates fine, because /v1/me has no capability behind it, and
		// then 403s on what its grant leaves out. The settings screen reads
		// /v1/inboxes on every render, so reading that as "the key is dead"
		// would disconnect a working site, and the /v1/me re-verification would
		// reconnect it, over and over.
		$this->connected_with( 'RESTRICTED-KEY' );
		$this->answer_with( self::response( 403, [ 'message' => 'This action is unauthorized.' ] ) );

		$this->fetch();

		$this->assertTrue(
			thrivedesk_is_connected(),
			'a key without one capability is still the key this site connects with'
		);
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

		$result = ( new \ThriveDesk\Services\TDApiService() )->getRequest( THRIVEDESK_API_URL . '/v1/me' );

		$this->assertTrue(
			\ThriveDesk\Admin::get_api_verification_status(),
			'an edge block is not the API rejecting the key'
		);
		// Asserting only the flag would pass with the Cloudflare sniff deleted,
		// now that no 403 clears it. The typing is the thing under test.
		$this->assertSame( 'network', $result['error_type'], 'an edge block must not be typed as an auth failure' );
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

	public function test_the_reply_path_asks_for_json_so_a_401_can_reach_it() {
		// The test below fakes a 401 on the reply path. Without this header the
		// real request can never receive one: the API renders its JSON 401 only
		// for a request that asked for JSON, and answers anything else with a
		// 302 to the login page. wp_remote_post() follows that as a GET, the
		// login page returns 200 text/html, and handle_response() reads a
		// non-JSON 200 as an empty success - so the dead key went undetected
		// and the customer was told a reply had been sent that never left.
		$this->connected_with( 'KEY-A' );

		$headers = null;
		add_filter(
			'pre_http_request',
			static function ( $pre, $args ) use ( &$headers ) {
				$headers = $args['headers'] ?? [];

				return self::response( 401, [ 'message' => 'Unauthenticated' ] );
			},
			10,
			2
		);

		( new \ThriveDesk\Services\TDApiService() )->postRequest(
			THRIVEDESK_API_URL . '/v1/customer/conversations/abc-123/reply',
			[ 'message' => 'hello' ]
		);

		$this->assertSame(
			'application/json',
			$headers['Accept'] ?? null,
			'without Accept: application/json the API answers 302, not 401, and the failure becomes invisible'
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
