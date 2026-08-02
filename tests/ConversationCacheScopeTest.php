<?php
/**
 * Nothing in the plugin decides who may read a conversation - the API does,
 * from the customer_email on the request. A cache hit returns before that
 * request is made, so a cached copy keyed on the conversation id alone hands
 * the next reader whatever the previous one was allowed to see. The reader is
 * part of the identity of the cached copy, exactly as it is for the list.
 *
 * The reply path has the mirror obligation: it invalidates on behalf of one
 * customer, so it must not evict anybody else's cached conversation or list.
 *
 * @package ThriveDesk\Tests
 */

class ConversationCacheScopeTest extends TD_Ajax_TestCase {

	/** @var string[] Outbound request URLs captured during the test. */
	private $requests = [];

	/** @var array<string,int> Subscriber id per email, so switching back reuses the account. */
	private $users = [];

	public function set_up() {
		parent::set_up();

		$this->requests = [];
		$this->users    = [];

		// Answer with the customer_email the request carried, so a response
		// served to the wrong reader is visible in the returned payload rather
		// than only in the request count.
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				$this->requests[] = $url;

				parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode(
						[
							'data' => [
								'id'       => 'conv-1',
								'read_for' => $query['customer_email'] ?? '',
							],
							'message' => 'ok',
						]
					),
				];
			},
			10,
			3
		);
	}

	private function login_subscriber( string $email ): void {
		if ( ! isset( $this->users[ $email ] ) ) {
			$this->users[ $email ] = self::factory()->user->create(
				[
					'role'       => 'subscriber',
					'user_email' => $email,
				]
			);
		}

		wp_set_current_user( $this->users[ $email ] );
	}

	/** Reads only, so the reply POST doesn't distort the count. */
	private function read_count(): int {
		return count(
			array_filter(
				$this->requests,
				static function ( $url ) {
					return false === strpos( $url, '/reply?' );
				}
			)
		);
	}

	private function reply_to( string $conversation_id ): void {
		$this->capture_json(
			function () use ( $conversation_id ) {
				$_POST    = [
					'data' => [
						'nonce'           => wp_create_nonce( 'td-reply-conversation-action' ),
						'conversation_id' => $conversation_id,
						'reply_text'      => 'hello',
					],
				];
				$_REQUEST = $_POST;

				\ThriveDesk\Conversations\Conversation::instance()->td_send_reply();
			}
		);
	}

	public function test_a_cached_conversation_is_not_served_to_another_customer() {
		$this->login_subscriber( 'owner@example.com' );
		$owner_view = \ThriveDesk\Conversations\Conversation::get_conversation( 'conv-1' );
		$this->assertSame( 'owner@example.com', $owner_view['read_for'] ?? null );

		$this->login_subscriber( 'nosy@example.com' );
		$nosy_view = \ThriveDesk\Conversations\Conversation::get_conversation( 'conv-1' );

		$this->assertSame(
			2,
			$this->read_count(),
			'a second reader must reach the API, which is the only thing that checks ownership'
		);
		$this->assertSame(
			'nosy@example.com',
			$nosy_view['read_for'] ?? null,
			"a cached copy must never be handed to a customer it was not fetched for"
		);
	}

	public function test_the_same_customer_still_gets_a_cached_copy() {
		$this->login_subscriber( 'owner@example.com' );

		\ThriveDesk\Conversations\Conversation::get_conversation( 'conv-1' );
		\ThriveDesk\Conversations\Conversation::get_conversation( 'conv-1' );

		$this->assertSame( 1, $this->read_count(), 'the cache must still absorb a repeat read' );
	}

	public function test_a_reply_drops_the_callers_own_cached_conversation() {
		$this->login_subscriber( 'owner@example.com' );
		\ThriveDesk\Conversations\Conversation::get_conversation( 'conv-1' );

		$this->reply_to( 'conv-1' );

		\ThriveDesk\Conversations\Conversation::get_conversation( 'conv-1' );

		$this->assertSame(
			2,
			$this->read_count(),
			"the reply changed the thread, so the replier's own copy must be refetched"
		);
	}

	public function test_a_reply_leaves_another_customers_cached_conversation_alone() {
		$this->login_subscriber( 'other@example.com' );
		\ThriveDesk\Conversations\Conversation::get_conversation( 'conv-1' );

		$this->login_subscriber( 'owner@example.com' );
		\ThriveDesk\Conversations\Conversation::get_conversation( 'conv-1' );
		$this->reply_to( 'conv-1' );

		$this->login_subscriber( 'other@example.com' );
		\ThriveDesk\Conversations\Conversation::get_conversation( 'conv-1' );

		$this->assertSame(
			2,
			$this->read_count(),
			"one customer's reply must not evict another customer's cached conversation"
		);
	}

	public function test_a_reply_leaves_another_customers_cached_list_alone() {
		$other_list = 'thrivedesk_conversations_1_other@example.com_';
		set_transient( $other_list, [ 'data' => [ [ 'id' => 'theirs' ] ] ], 300 );

		$this->login_subscriber( 'owner@example.com' );
		$this->reply_to( 'conv-1' );

		// Read past the in-process option cache: a raw DELETE removes the row
		// while leaving the cached copy behind, which hides the eviction here.
		wp_cache_flush();

		$this->assertNotFalse(
			get_transient( $other_list ),
			"a reply must not reach another customer's cached ticket list"
		);
	}
}
