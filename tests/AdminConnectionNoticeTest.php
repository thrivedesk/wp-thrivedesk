<?php
/**
 * The verified flag going false is only half of it: the site owner has no
 * reason to open this plugin's screen, so a connection that broke on its own
 * has to announce itself wherever they already are. Tickets stop loading, the
 * portal empties, and until something says why, the site looks like the one
 * that is broken.
 *
 * @package ThriveDesk\Tests
 */

class AdminConnectionNoticeTest extends WP_UnitTestCase {

	public function tear_down() {
		unset( $GLOBALS['plugin_page'] );
		remove_all_filters( 'pre_http_request' );
		delete_transient( 'thrivedesk_reverify_attempted' );

		parent::tear_down();
	}

	/** Core resolves $_GET['page'] into this before any admin hook fires. */
	private function on_screen( string $slug ): void {
		$GLOBALS['plugin_page'] = $slug;
	}

	private function notice(): string {
		ob_start();
		\ThriveDesk\Admin::instance()->render_connection_notice();

		return (string) ob_get_clean();
	}

	private function as_administrator(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	private function key_rejected(): void {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'KEY-A' ] );
		update_option( 'td_helpdesk_verified', false );
	}

	public function test_it_warns_when_the_key_on_file_stopped_working() {
		$this->as_administrator();
		$this->key_rejected();

		$html = $this->notice();

		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'td-connection-notice', $html, 'the hook a site or a browser test finds this by' );
		$this->assertStringContainsString( 'admin.php?page=thrivedesk', $html, 'the warning has to lead somewhere it can be fixed' );
	}

	public function test_it_stays_quiet_while_the_connection_works() {
		$this->as_administrator();
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'KEY-A' ] );
		update_option( 'td_helpdesk_verified', true );

		$this->assertSame( '', $this->notice() );
	}

	public function test_it_stays_quiet_on_a_site_that_never_connected() {
		// No key is a plugin that was installed and not set up yet, which is
		// every fresh install - nothing has broken, so nothing is wrong.
		$this->as_administrator();
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => '' ] );
		update_option( 'td_helpdesk_verified', false );

		$this->assertSame( '', $this->notice() );
	}

	public function test_only_someone_who_can_fix_it_is_told() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->key_rejected();

		$this->assertSame( '', $this->notice(), 'an editor cannot re-verify the key and cannot act on this' );
	}

	public function test_the_plugin_screen_gets_the_reason_without_a_link_to_itself() {
		$this->as_administrator();
		$this->key_rejected();
		$this->on_screen( 'thrivedesk' );

		$html = $this->notice();

		$this->assertStringContainsString( 'notice-warning', $html, 'the screen showing "Not connected" is where the reason matters most' );
		$this->assertStringNotContainsString( 'admin.php?page=thrivedesk', $html, 'this is that page; a button back to it is noise' );
	}

	public function test_it_does_not_claim_the_key_was_rejected() {
		// The flag is a boolean. It is also false for a site restored from a
		// backup taken before the key was verified, and for a /v1/me that came
		// back 200 with no company on it - so the notice cannot assert which of
		// those happened.
		$this->as_administrator();
		$this->key_rejected();

		$html = $this->notice();

		$this->assertStringNotContainsString( 'rejected', $html );
		$this->assertStringContainsString( 'could not verify', $html );
	}

	public function test_the_flag_is_settled_before_the_notice_can_render_it() {
		// admin-header.php emits admin_notices before the page callback runs,
		// so the healing pass has to sit on 'load-<page_hook>' - anything later
		// and the plugin's own screen prints a warning above a connect card
		// that says the opposite.
		$class = new ReflectionClass( \ThriveDesk\Admin::class );
		$admin = $class->newInstanceWithoutConstructor();

		$this->as_administrator();
		$this->key_rejected();

		// A key that still authenticates: /v1/me answers with a company.
		add_filter(
			'pre_http_request',
			static function () {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( [ 'company' => [ 'id' => 7, 'name' => 'Acme' ] ] ),
				];
			}
		);

		$admin->settle_connection_state();

		$this->assertTrue(
			\ThriveDesk\Admin::get_api_verification_status(),
			'a key that still works must get its flag back'
		);
		$this->assertSame( '', $this->notice(), 'and then there is nothing to warn about' );
	}

	public function test_the_healing_pass_runs_before_admin_notices() {
		// admin_menu() is the only place the page hook becomes knowable, so it
		// is where the load-<hook> action has to be registered. It writes to
		// core's menu globals, which WP_UnitTestCase does not restore.
		$menu_globals = [];
		foreach ( [ 'menu', 'submenu', 'admin_page_hooks', '_registered_pages', '_parent_pages' ] as $key ) {
			$menu_globals[ $key ] = $GLOBALS[ $key ] ?? null;
		}

		$this->as_administrator();

		$admin = ( new ReflectionClass( \ThriveDesk\Admin::class ) )->newInstanceWithoutConstructor();
		$admin->admin_menu();

		$hooked = has_action( 'load-toplevel_page_thrivedesk', [ $admin, 'settle_connection_state' ] );

		foreach ( $menu_globals as $key => $value ) {
			if ( null === $value ) {
				unset( $GLOBALS[ $key ] );
				continue;
			}

			$GLOBALS[ $key ] = $value;
		}

		$this->assertNotFalse(
			$hooked,
			'nothing settles the flag before admin_notices reads it'
		);
	}

	public function test_the_notice_is_hooked_up() {
		// Admin is a singleton that registers its hooks in the constructor, and
		// WP_UnitTestCase restores $wp_filter after every test - so whichever
		// test builds the instance first is also the only one that would see
		// those registrations. Run the constructor here instead, on an instance
		// of its own, and the wiring is asserted whatever the test order.
		$class = new ReflectionClass( \ThriveDesk\Admin::class );
		$admin = $class->newInstanceWithoutConstructor();
		$ctor  = $class->getConstructor();
		$ctor->setAccessible( true );
		$ctor->invoke( $admin );

		$this->assertNotFalse(
			has_action( 'admin_notices', [ $admin, 'render_connection_notice' ] ),
			'a renderer nothing calls warns nobody'
		);
	}
}
