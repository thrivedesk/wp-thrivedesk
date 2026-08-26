<?php
/**
 * thrivedesk_system_info read $_POST['data']['td_helpdesk_api_key'] with no
 * unslashing and no sanitization, then concatenated it into an
 * 'Authorization: Bearer ' header. An array value made that concatenation a
 * PHP 8 fatal, and slashes added by WordPress went out on the wire.
 *
 * @package ThriveDesk\Tests
 */

class SystemInfoApiKeySanitizationTest extends TD_Ajax_TestCase {

	/** @var string|null */
	private $captured_auth;

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->captured_auth = null;

		add_filter(
			'pre_http_request',
			function ( $pre, $args ) {
				$this->captured_auth = $args['headers']['Authorization'] ?? null;

				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( [ 'company' => [ 'id' => 'c1', 'name' => 'Acme' ] ] ),
				];
			},
			10,
			2
		);
	}

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		$_POST    = [];
		$_REQUEST = [];

		parent::tear_down();
	}

	/** @param mixed $submitted */
	private function system_info( $submitted ): array {
		$post = [
			'nonce' => wp_create_nonce( 'thrivedesk-nonce' ),
			'data'  => [ 'td_helpdesk_api_key' => $submitted ],
		];

		$_POST = $_REQUEST = $post;

		return $this->capture_json(
			static function () {
				\ThriveDesk\Conversations\Conversation::instance()->thrivedesk_system_info();
			}
		);
	}

	public function test_an_array_api_key_does_not_reach_the_authorization_header() {
		$body = $this->system_info( [ 'not', 'a', 'key' ] );

		$this->assertSame( 'false', $body['status'] ?? null, 'an unusable key is rejected, not concatenated' );
		$this->assertNull( $this->captured_auth, 'no request should have gone out at all' );
	}

	public function test_slashes_added_by_wordpress_are_stripped_before_the_key_is_used() {
		// WordPress slashes every superglobal, so a key with a quote in it
		// arrives escaped and would otherwise be sent that way.
		$this->system_info( 'KEY-\\"quoted\\"' );

		$this->assertSame( 'Bearer KEY-"quoted"', $this->captured_auth );
	}

	public function test_a_plain_key_still_reaches_the_api_unchanged() {
		$this->system_info( 'KEY-ABC123' );

		$this->assertSame( 'Bearer KEY-ABC123', $this->captured_auth );
	}
}
