<?php
/**
 * The verify handler builds its failure message out of whatever the API
 * answered with. A 200 carrying neither company nor message data is the case
 * that reply cannot be indexed for, and the settings screen parses this JSON -
 * a PHP warning printed ahead of it is what the admin ends up reading.
 *
 * Deliberately not a TD_Ajax_TestCase: WP_Ajax_UnitTestCase turns E_WARNING
 * off, which is the signal this test exists to catch.
 *
 * @package ThriveDesk\Tests
 */

class ApiKeyVerificationMessageTest extends WP_UnitTestCase {

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		$_POST    = [];
		$_REQUEST = [];

		parent::tear_down();
	}

	public function test_a_200_without_company_or_message_still_renders_a_clean_envelope() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'KEY-A' ] );

		add_filter(
			'pre_http_request',
			static fn() => [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'foo' => 'bar' ] ),
			]
		);

		$_POST    = [
			'nonce' => wp_create_nonce( 'thrivedesk-nonce' ),
			'data'  => [ 'td_helpdesk_api_key' => 'KEY-A' ],
		];
		$_REQUEST = $_POST;

		ob_start();
		try {
			\ThriveDesk\Conversations\Conversation::instance()->td_verify_helpdesk_api_key();
		} catch ( WPDieException $e ) {
			// wp_die() ends the handler; the JSON is already in the buffer.
		}
		$body = json_decode( (string) ob_get_clean(), true );

		$this->assertSame( 'error', $body['status'] ?? null );
		$this->assertSame( 'Something went wrong: ', $body['data']['message'] ?? null );
	}
}
