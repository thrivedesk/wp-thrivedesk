<?php
/**
 * Portal input reaches ThriveDesk as a URL, and TDApiService always attaches the
 * site's API key, so whatever the visitor controls must be bounded before it
 * lands there. A raw page number can append a second customer_email and take
 * over the customer scope; a raw conversation id can repoint the path of an
 * authenticated call.
 *
 * @package ThriveDesk\Tests
 */

class ConversationRequestScopingTest extends TD_Ajax_TestCase {

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
							'data' => [ [ 'id' => 'abc' ] ],
							'meta' => [ 'last_page' => 1 ],
						]
					),
				];
			},
			10,
			3
		);
	}

	public function tear_down() {
		unset( $_GET['cv_page'] );

		parent::tear_down();
	}

	/**
	 * Resolve the query of the first captured request the way a server would:
	 * parse_str keeps the last occurrence of a repeated key.
	 */
	private function first_request_query(): array {
		$this->assertNotEmpty( $this->requests, 'expected an outbound request' );
		parse_str( (string) wp_parse_url( $this->requests[0], PHP_URL_QUERY ), $query );

		return $query;
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

	public function test_cv_page_cannot_inject_another_customer_email() {
		$this->login_subscriber( 'me@example.com' );

		$_GET['cv_page'] = '1&customer_email=victim@example.com';

		\ThriveDesk\Conversations\Conversation::get_conversations();

		$query = $this->first_request_query();
		$this->assertSame( 'me@example.com', $query['customer_email'] );
	}

	public function test_cv_page_reaches_the_api_as_digits_only() {
		$this->login_subscriber( 'me@example.com' );

		$_GET['cv_page'] = '2 and some junk';

		\ThriveDesk\Conversations\Conversation::get_conversations();

		$query = $this->first_request_query();
		$this->assertSame( '2', $query['page'] );
	}

	public function test_reply_rejects_a_conversation_id_that_repoints_the_request() {
		$this->login_subscriber( 'me@example.com' );

		$this->capture_json(
			function () {
				$_POST    = [
					'data' => [
						'nonce'           => wp_create_nonce( 'td-reply-conversation-action' ),
						'conversation_id' => '1/../../v1/me?leak=',
						'reply_text'      => 'hello',
					],
				];
				$_REQUEST = $_POST;

				\ThriveDesk\Conversations\Conversation::instance()->td_send_reply();
			}
		);

		$this->assertSame( [], $this->requests, 'an unusable id must not reach the API' );
	}

	public function test_reply_still_sends_a_well_formed_conversation_id() {
		$this->login_subscriber( 'me@example.com' );

		$body = $this->capture_json(
			function () {
				$_POST    = [
					'data' => [
						'nonce'           => wp_create_nonce( 'td-reply-conversation-action' ),
						'conversation_id' => 'abc-123',
						'reply_text'      => 'hello',
					],
				];
				$_REQUEST = $_POST;

				\ThriveDesk\Conversations\Conversation::instance()->td_send_reply();
			}
		);

		$this->assertCount( 1, $this->requests );
		$this->assertStringContainsString( '/abc-123/reply', $this->requests[0] );
		// The portal JS branches on response.status, so the envelope must survive
		// the switch from a bare die to wp_die().
		$this->assertSame( 'success', $body['status'] ?? null );
	}

	public function test_get_conversation_rejects_an_unusable_id() {
		$this->login_subscriber( 'me@example.com' );

		$this->assertNull( \ThriveDesk\Conversations\Conversation::get_conversation( '1/../../v1/me' ) );
		$this->assertSame( [], $this->requests, 'an unusable id must not reach the API' );
	}
}
