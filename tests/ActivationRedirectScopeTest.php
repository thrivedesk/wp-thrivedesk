<?php
/**
 * The post-activation welcome redirect hangs off admin_init, which also fires
 * on admin-ajax.php, on cron and on REST requests. So an unauthenticated
 * visitor hitting any nopriv endpoint consumed the one-shot activation flag -
 * the admin who just activated never saw the welcome screen - and got a 302
 * where the caller expected JSON.
 *
 * @package ThriveDesk\Tests
 */

class ActivationRedirectScopeTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		update_option( 'wp_thrivedesk_activation_redirect', true );
	}

	public function tear_down() {
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wp_redirect' );
		delete_option( 'wp_thrivedesk_activation_redirect' );

		parent::tear_down();
	}

	/**
	 * Run the hook and report whether it tried to redirect. A redirect is
	 * turned into an exception because the handler exits straight after it.
	 */
	private function run_hook(): bool {
		add_filter(
			'wp_redirect',
			static function () {
				throw new RuntimeException( 'redirected' );
			}
		);

		try {
			\ThriveDesk\Admin::instance()->redirect_to_getting_started_page();
		} catch ( RuntimeException $e ) {
			return true;
		}

		return false;
	}

	public function test_an_ajax_request_neither_redirects_nor_consumes_the_flag() {
		add_filter( 'wp_doing_ajax', '__return_true' );
		wp_set_current_user( 0 );

		$this->assertFalse( $this->run_hook(), 'an ajax caller must not be handed a 302 in place of its JSON' );
		$this->assertTrue(
			(bool) get_option( 'wp_thrivedesk_activation_redirect' ),
			'the flag belongs to the admin who activated the plugin, not to a nopriv visitor'
		);
	}

	public function test_a_visitor_without_the_capability_does_not_consume_the_flag() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$this->assertFalse( $this->run_hook() );
		$this->assertTrue( (bool) get_option( 'wp_thrivedesk_activation_redirect' ) );
	}

	public function test_the_administrator_who_activated_still_gets_the_welcome_screen() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->assertTrue( $this->run_hook(), 'the redirect itself must keep working' );
		$this->assertFalse(
			get_option( 'wp_thrivedesk_activation_redirect', false ),
			'and it must be one-shot'
		);
	}
}
