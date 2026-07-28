<?php
/**
 * td_reload_tickets is a portal action open to every logged-in user, so it must
 * refresh the caller's own ticket list and nothing else. It used to wipe the
 * whole plugin cache with a direct DELETE, which both evicted every other
 * customer's cached conversations and — because a raw query leaves WordPress's
 * option cache untouched — still served the caller their stale list.
 *
 * @package ThriveDesk\Tests
 */

class ReloadTicketsCacheScopeTest extends TD_Ajax_TestCase {

	public function set_up() {
		parent::set_up();

		add_filter(
			'pre_http_request',
			static function () {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode(
						[
							'data' => [ [ 'id' => 'fresh' ] ],
							'meta' => [ 'last_page' => 1 ],
						]
					),
				];
			},
			10,
			3
		);
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
}
