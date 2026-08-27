<?php
/**
 * What the admin screen shows before this site has a ThriveDesk account.
 *
 * The plugin used to answer that question by leaving the screen entirely: an
 * install with no key was sent to a fullscreen setup page, and one whose key
 * had been rejected to a welcome page. Neither showed the tabs, so a new
 * install could not read what the plugin integrates with, or watch the Portal
 * tour, before deciding to sign up.
 *
 * The tabs render either way now. These tests pin the three things that has to
 * mean: the connect card leads the Overview tab, the tabs that need an account
 * say so instead of rendering empty controls, and - the part that is easy to
 * regress - nothing on that screen calls ThriveDesk.
 *
 * @package ThriveDesk\Tests
 */

use ThriveDesk\Services\WorkspaceService;

class OnboardingScreenTest extends WP_UnitTestCase {

	/** @var string[] every URL requested while a screen rendered. */
	private $requested = [];

	public function set_up() {
		parent::set_up();

		WorkspaceService::flush();
		delete_option( 'td_helpdesk_system_info' );

		add_filter( 'pre_http_request', [ $this, 'intercept' ], 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'intercept' ], 10 );

		WorkspaceService::flush();
		wp_clear_scheduled_hook( WorkspaceService::REFRESH_HOOK );
		delete_option( 'td_helpdesk_settings' );
		delete_option( 'td_helpdesk_verified' );
		delete_option( 'td_helpdesk_system_info' );
		delete_transient( 'thrivedesk_reverify_attempted' );

		parent::tear_down();
	}

	/**
	 * Records the call and refuses it. Refusing is the point: a test that let
	 * these through would be measuring the network.
	 *
	 * @param mixed  $preempt Short-circuit value.
	 * @param array  $args    Request arguments.
	 * @param string $url     Request URL.
	 *
	 * @return array
	 */
	public function intercept( $preempt, $args, $url ) {
		$this->requested[] = $url;

		return [
			'headers'  => [],
			'response' => [ 'code' => 500, 'message' => 'blocked in tests' ],
			'body'     => '',
		];
	}

	private function connect( bool $verified = true ): void {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'REAL-KEY-1234567890' ] );
		update_option( 'td_helpdesk_verified', $verified );
		update_option( 'td_helpdesk_system_info', [ 'company' => 'Woo Demo' ] );
	}

	private function render_screen(): string {
		// Rendered through load_pages() rather than the view, because which
		// view it picks is half of what is under test.
		ob_start();
		\ThriveDesk\Admin::instance()->load_pages();

		return (string) ob_get_clean();
	}

	public function test_a_site_with_no_key_is_not_connected() {
		$this->assertFalse( thrivedesk_is_connected() );
	}

	/**
	 * The case the old routing got wrong in the other direction: a key on file
	 * is not a working connection, and the screen has to treat it as absent.
	 */
	public function test_a_key_that_was_never_verified_is_not_connected() {
		$this->connect( false );

		$this->assertFalse( thrivedesk_is_connected() );
	}

	public function test_a_verified_key_is_connected() {
		$this->connect();

		$this->assertTrue( thrivedesk_is_connected() );
	}

	public function test_an_unconnected_site_still_gets_the_tabs() {
		$html = $this->render_screen();

		$this->assertStringContainsString( 'id="td-admin-app"', $html, 'the tab app has to mount' );
		$this->assertStringContainsString( 'id="td-panel-overview"', $html );
		$this->assertStringContainsString( 'id="td-panel-livechat"', $html );
		$this->assertStringContainsString( 'id="td-panel-portal"', $html );
	}

	public function test_the_connect_card_leads_the_overview_tab() {
		$html = $this->render_screen();

		$this->assertStringContainsString( 'id="td-setup-split"', $html );
		$this->assertStringContainsString( 'Just one last step!', $html );

		// Ahead of the tour, which is the row it was inserted above.
		$this->assertLessThan(
			strpos( $html, 'Overview of WPPortal' ),
			strpos( $html, 'id="td-setup-split"' ),
			'the connect card is the first row of the tab'
		);
	}

	/**
	 * The Assistant card is rendered from one partial in two branches, so the
	 * thing to guard is that exactly one branch runs.
	 */
	public function test_what_the_product_is_sits_beside_the_ask() {
		$html = $this->render_screen();

		$this->assertSame( 1, substr_count( $html, 'What is Assistant?' ) );
		$this->assertLessThan(
			strpos( $html, 'What is Assistant?' ),
			strpos( $html, 'id="td-setup-split"' ),
			'the connect card leads, and what it buys you sits to its right'
		);
	}

	/**
	 * The IP addresses that card is really about are already on the connect
	 * card, behind its Additional Info rail, so showing it too is both noise
	 * and a second copy of the same advice.
	 */
	public function test_the_cloudflare_tip_waits_for_a_connection_to_troubleshoot() {
		$this->assertStringNotContainsString( 'Using Cloudflare?', $this->render_screen() );

		$this->connect();

		$this->assertStringContainsString( 'Using Cloudflare?', $this->render_screen() );
	}

	public function test_the_assistant_card_stays_in_the_tips_row_once_connected() {
		$this->connect();

		$html = $this->render_screen();

		$this->assertSame( 1, substr_count( $html, '>What is Assistant?</h3>' ) );
		$this->assertLessThan(
			strpos( $html, 'What is Assistant?' ),
			strpos( $html, 'Using Cloudflare?' ),
			'connected, it is the second card of the bottom row again'
		);
	}

	/**
	 * Three partials now render in one of two branches each, which is exactly
	 * how a shared partial goes wrong - so both branches are counted.
	 */
	public function test_every_shared_card_renders_exactly_once() {
		foreach ( [ 'disconnected', 'connected' ] as $state ) {
			if ( 'connected' === $state ) {
				$this->connect();
			}

			$html = $this->render_screen();

			$this->assertSame( 1, substr_count( $html, 'class="td-video"' ), "portal card, $state" );
			$this->assertSame( 1, substr_count( $html, '>What is Assistant?</h3>' ), "assistant card, $state" );
			$this->assertSame( 1, substr_count( $html, 'id="td-workspace-card"' ), "workspace card, $state" );
		}
	}

	/**
	 * Everything that answers "why would I sign up" moves in beside the card
	 * doing the asking, and moves back out once it has been answered.
	 */
	public function test_the_tour_and_the_tip_sit_beside_the_ask_until_connected() {
		$html = $this->render_screen();

		$connect = strpos( $html, 'id="td-setup-split"' );

		$this->assertLessThan( strpos( $html, 'What is Assistant?' ), $connect );
		$this->assertLessThan( strpos( $html, 'Overview of WPPortal' ), $connect );
		$this->assertLessThan( strpos( $html, 'id="td-workspace-card"' ), $connect );

		// Stacked, so the tour is not splitting a narrow column between a
		// paragraph and a thumbnail.
		$this->assertStringNotContainsString( 'md:grid-cols-2 gap-6 items-center', $html );
	}

	public function test_the_tour_takes_the_wide_column_back_once_connected() {
		$this->connect();

		$html = $this->render_screen();

		$this->assertStringContainsString( 'md:grid-cols-2 gap-6 items-center', $html );
		$this->assertLessThan(
			strpos( $html, 'id="td-workspace-card"' ),
			strpos( $html, 'Overview of WPPortal' ),
			'the tour leads the row and the workspace facts sit beside it'
		);
	}

	/**
	 * There is nothing to detail until something is connected, and the card
	 * carries a second field with the same id as the connect card's.
	 */
	public function test_connection_details_are_hidden_until_there_is_a_connection() {
		$html = $this->render_screen();

		$this->assertStringNotContainsString( 'Connection details', $html );
		$this->assertSame( 1, substr_count( $html, 'id="td_helpdesk_api_key"' ), 'one API key field per page' );
	}

	public function test_connection_details_come_back_once_connected() {
		$this->connect();

		$html = $this->render_screen();

		$this->assertStringContainsString( 'Connection details', $html );
		$this->assertStringNotContainsString( 'id="td-setup-split"', $html, 'a connected site is not asked to connect' );
	}

	public function test_the_workspace_card_asks_for_a_connection() {
		$html = $this->render_screen();

		$this->assertStringContainsString( 'id="td-workspace-card"', $html );
		$this->assertStringContainsString( 'Once this site is connected', $html );

		// The rows the card is made of when it has something to say, marked up
		// for the reveal that runs when they are filled in. Matching on the
		// attribute rather than on a label - the copy above mentions the same
		// words the labels use.
		$this->assertStringNotContainsString( 'data-td-reveal', $html );
	}

	public function test_live_chat_and_portal_offer_an_account_instead_of_empty_controls() {
		$html = $this->render_screen();

		$this->assertSame( 2, substr_count( $html, 'td-empty__actions' ), 'one placeholder on each tab' );
		$this->assertStringContainsString( 'Live Chat needs a ThriveDesk account', $html );
		$this->assertStringContainsString( 'Portal needs a ThriveDesk account', $html );

		// The controls those panels are made of, none of which can be filled.
		$this->assertStringNotContainsString( 'id="td-assistants"', $html );
		$this->assertStringNotContainsString( 'id="td-inboxes"', $html );
	}

	/**
	 * The regression this file exists for.
	 *
	 * Rendering the tabs for an unconnected site means rendering panels that
	 * are backed by five workspace probes, the assistant list, the inbox list
	 * and the knowledge base. All of them fail without a key, and a failure is
	 * not cached - so the screen would have made eight requests, waited for
	 * every one of them, and shown the same thing either way.
	 */
	public function test_nothing_on_an_unconnected_screen_calls_thrivedesk() {
		$this->render_screen();

		$this->assertSame( [], $this->requested );
	}

	/**
	 * Same guard for the halfway state, which is the one that used to reach the
	 * network hardest: a key is present, so every lookup thinks it is worth
	 * trying, and every one of them is refused.
	 */
	public function test_an_unverified_key_does_not_call_thrivedesk_on_every_page_load() {
		$this->connect( false );

		// The one call this screen is allowed: load_pages() asks ThriveDesk
		// directly whether a key it has stopped trusting still works, once a
		// minute. See Admin::load_pages().
		set_transient( 'thrivedesk_reverify_attempted', true, MINUTE_IN_SECONDS );

		$this->render_screen();

		$this->assertSame( [], $this->requested );
	}
}
