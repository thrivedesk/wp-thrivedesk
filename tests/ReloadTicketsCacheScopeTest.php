<?php
/**
 * td_reload_tickets is a portal action open to every logged-in user, so it must
 * refresh the caller's own ticket list and nothing else. It used to wipe the
 * whole plugin cache with a direct DELETE, which both evicted every other
 * customer's cached conversations and (because a raw query leaves WordPress's
 * option cache untouched) still served the caller their stale list.
 *
 * @package ThriveDesk\Tests
 */

class ReloadTicketsCacheScopeTest extends TD_Ajax_TestCase {

	/** @var string[] Outbound request URLs captured during the test. */
	private $requests = [];

	public function set_up() {
		parent::set_up();

		$this->requests = [];

		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				$this->requests[] = $url;

				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode(
						[
							'data' => [ [ 'id' => 'fresh' ] ],
							'meta' => [ 'last_page' => 3 ],
						]
					),
				];
			},
			10,
			3
		);
	}

	public function tear_down() {
		unset( $_SERVER['HTTP_REFERER'] );

		parent::tear_down();
	}

	private function login_subscriber( string $email ): void {
		wp_set_current_user(
			self::factory()->user->create(
				[
					'role'       => 'subscriber',
					'user_email' => $email,
				]
			)
		);
	}

	private function reload(): array {
		return $this->capture_json(
			function () {
				$_POST    = [ 'nonce' => wp_create_nonce( 'thrivedesk-nonce' ) ];
				$_REQUEST = $_POST;

				\ThriveDesk\Conversations\Conversation::instance()->td_reload_tickets();
			}
		);
	}

	public function test_reload_leaves_another_customers_cache_alone() {
		$other_key = 'thrivedesk_conversations_1_other@example.com_';
		set_transient( $other_key, [ 'data' => [ [ 'id' => 'theirs' ] ] ], 300 );

		$this->login_subscriber( 'me@example.com' );

		$body = $this->reload();
		$this->assertSame( true, $body['success'] ?? null, 'reload must succeed for a portal user' );

		// Read past the in-process option cache: the old blanket DELETE removed
		// the row while leaving the cached copy behind.
		wp_cache_flush();

		$this->assertNotFalse( get_transient( $other_key ), "another customer's cached list must survive" );
	}

	public function test_reload_serves_the_caller_fresh_data() {
		$this->login_subscriber( 'me@example.com' );

		set_transient(
			'thrivedesk_conversations_1_me@example.com_',
			[ 'data' => [ [ 'id' => 'stale' ] ] ],
			300
		);

		$body = $this->reload();

		$this->assertSame( 'fresh', $body['data']['data']['data'][0]['id'] ?? null );
	}

	/**
	 * The button posts to admin-ajax, which carries none of the portal page's
	 * query string, so page 1 is all the handler can infer on its own. A
	 * customer paging through their tickets would get the reload they asked
	 * for on a page they aren't looking at.
	 */
	public function test_reload_refreshes_the_page_the_customer_is_viewing() {
		$this->login_subscriber( 'me@example.com' );

		set_transient(
			'thrivedesk_conversations_2_me@example.com_',
			[ 'data' => [ [ 'id' => 'stale' ] ] ],
			300
		);

		$_SERVER['HTTP_REFERER'] = home_url( '/support/?cv_page=2' );

		$this->reload();

		parse_str( (string) wp_parse_url( $this->requests[0] ?? '', PHP_URL_QUERY ), $query );
		$this->assertSame( '2', $query['page'] ?? null, 'the refetch must ask for the page being viewed' );

		$cached = get_transient( 'thrivedesk_conversations_2_me@example.com_' );
		$this->assertSame( 'fresh', $cached['data'][0]['id'] ?? null, 'the viewed page is the one that must be dropped and refetched' );
	}

	public function test_an_offsite_referer_falls_back_to_the_first_page() {
		$this->login_subscriber( 'me@example.com' );

		$_SERVER['HTTP_REFERER'] = 'https://elsewhere.example/?cv_page=9';

		$this->reload();

		parse_str( (string) wp_parse_url( $this->requests[0] ?? '', PHP_URL_QUERY ), $query );
		$this->assertSame( '1', $query['page'] ?? null );
	}

	public function test_a_failing_fetch_is_reported_instead_of_taking_the_request_down() {
		$this->login_subscriber( 'me@example.com' );

		// Stands in for anything under get_conversations() throwing. The catch
		// named an Exception that resolved into this namespace, so it matched
		// nothing and the handler died instead of answering.
		add_filter(
			'pre_http_request',
			static function () {
				throw new \Exception( 'boom' );
			},
			9
		);

		$body = $this->reload();

		$this->assertFalse( $body['success'] ?? null );
		$this->assertSame( 'Failed to reload tickets', $body['data']['message'] ?? null );
	}
}
