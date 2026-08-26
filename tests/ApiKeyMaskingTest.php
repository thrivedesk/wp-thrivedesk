<?php
/**
 * The settings screen printed the full ThriveDesk API key into two inputs.
 * type="password" hides it on screen and nowhere else - it is still in
 * view-source, in the DOM, in password managers, on a screen share, and one
 * selector away from any script on the page (which is exactly what the
 * unescaped assistant/inbox option markup could have been).
 *
 * Masking the field is only half the fix: the admin JS posts whatever is in
 * `#td_helpdesk_api_key` on every save, so the save handler has to read an
 * empty submission as "unchanged". Without that, saving any unrelated setting
 * silently disconnects the integration.
 *
 * @package ThriveDesk\Tests
 */

class ApiKeyMaskingTest extends TD_Ajax_TestCase {

	private const KEY = 'abcdSECRET-KEY-9876543210';

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => self::KEY ] );
		update_option( 'td_helpdesk_verified', true );

		// The settings screen populates itself over HTTP; keep it offline.
		add_filter(
			'pre_http_request',
			static fn() => [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [] ),
			]
		);
	}

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		$_POST    = [];
		$_REQUEST = [];

		parent::tear_down();
	}

	/**
	 * The partial is included directly rather than through thrivedesk_view(),
	 * which uses require_once and so renders a given view at most once per
	 * process - fine for a real request, useless across several test cases.
	 */
	private function render_settings(): string {
		ob_start();
		include THRIVEDESK_DIR . '/includes/views/partials/settings.php';

		return (string) ob_get_clean();
	}

	public function test_the_settings_screen_never_renders_the_key() {
		$html = $this->render_settings();

		$this->assertStringNotContainsString(
			self::KEY,
			$html,
			'the API key must not reach the admin DOM at all'
		);
	}

	public function test_the_preview_shows_enough_to_recognise_the_key_and_no_more() {
		$html = $this->render_settings();

		$this->assertStringContainsString(
			'abcd' . str_repeat( '*', 20 ),
			$html,
			'the admin still has to be able to tell which key is on file'
		);
		$this->assertStringNotContainsString( 'SECRET-KEY', $html );
	}

	/**
	 * The companion half. resources/js/admin.js reads #td_helpdesk_api_key and
	 * sends it with every save, so a blank field must not wipe the stored key.
	 */
	public function test_an_empty_submission_keeps_the_stored_key() {
		$body = $this->save( [ 'td_helpdesk_api_key' => '' ] );

		$this->assertSame( 'success', $body['status'] ?? null );
		$this->assertSame(
			self::KEY,
			get_option( 'td_helpdesk_settings' )['td_helpdesk_api_key'],
			'saving an unrelated setting must not disconnect the integration'
		);
	}

	public function test_a_whitespace_only_submission_keeps_the_stored_key() {
		$this->save( [ 'td_helpdesk_api_key' => '   ' ] );

		$this->assertSame( self::KEY, get_option( 'td_helpdesk_settings' )['td_helpdesk_api_key'] );
	}

	public function test_other_settings_still_save_alongside_the_retained_key() {
		$this->save(
			[
				'td_helpdesk_api_key'   => '',
				'td_knowledgebase_slug' => 'docs',
			]
		);

		$settings = get_option( 'td_helpdesk_settings' );

		$this->assertSame( self::KEY, $settings['td_helpdesk_api_key'] );
		$this->assertSame( 'docs', $settings['td_knowledgebase_slug'] );
	}

	public function test_a_deliberately_re_keyed_field_still_replaces_the_stored_key() {
		$this->save( [ 'td_helpdesk_api_key' => 'NEW-KEY-0001' ] );

		$this->assertSame(
			'NEW-KEY-0001',
			get_option( 'td_helpdesk_settings' )['td_helpdesk_api_key'],
			'"unchanged" must only mean empty, never "ignore what the admin typed"'
		);
	}

	private function save( array $data ): array {
		$post = [
			'nonce' => wp_create_nonce( 'thrivedesk-nonce' ),
			'data'  => $data,
		];

		$_POST = $_REQUEST = $post;

		return $this->capture_json(
			static function () {
				\ThriveDesk\Conversations\Conversation::instance()->td_save_helpdesk_form();
			}
		);
	}
}
