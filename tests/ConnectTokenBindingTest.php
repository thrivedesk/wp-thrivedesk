<?php
/**
 * `admin.php?page=td-api&token=<attacker key>` used to pre-fill the API key
 * field from the raw query string, on any admin screen, at any time. One click
 * on "Complete Setup" then pointed the whole helpdesk at the attacker's
 * ThriveDesk tenant: every portal visitor's email and ticket body, plus their
 * assistant id booted on every front-end page.
 *
 * A token now only counts when this site actually started the authorization
 * round trip, proven by the one-time state Admin::issue_connect_state() leaves
 * behind.
 *
 * @package ThriveDesk\Tests
 */

class ConnectTokenBindingTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		$this->reset_memo();
		delete_transient( 'thrivedesk_connect_state' );
		unset( $_GET['token'], $_GET['state'] );
	}

	public function tear_down() {
		$this->reset_memo();
		delete_transient( 'thrivedesk_connect_state' );
		unset( $_GET['token'], $_GET['state'] );
		remove_all_filters( 'pre_http_request' );

		parent::tear_down();
	}

	/**
	 * Both accessors memoise per request; a test process is one long "request",
	 * so the statics have to be cleared between cases.
	 */
	private function reset_memo(): void {
		foreach ( [ 'issued_connect_state', 'connect_return_token' ] as $name ) {
			$property = new ReflectionProperty( \ThriveDesk\Admin::class, $name );
			$property->setAccessible( true );
			$property->setValue( null, null );
		}
	}

	/** The `state` carried on a connect link's nested auth_return_url. */
	private function state_in( string $connect_url ): string {
		parse_str( (string) wp_parse_url( $connect_url, PHP_URL_QUERY ), $query );
		parse_str( (string) wp_parse_url( $query['auth_return_url'] ?? '', PHP_URL_QUERY ), $inner );

		return (string) ( $inner['state'] ?? '' );
	}

	private function arrive_with( array $query ): string {
		$this->reset_memo();
		$_GET = array_merge( $_GET, $query );

		return \ThriveDesk\Admin::connect_return_token();
	}

	public function test_a_planted_token_is_ignored_when_no_connect_is_pending() {
		$this->assertSame(
			'',
			$this->arrive_with( [ 'token' => 'ATTACKER-KEY' ] ),
			'a token nobody on this site asked for must not be honoured'
		);
	}

	public function test_a_state_bound_token_from_our_own_connect_is_accepted() {
		$state = \ThriveDesk\Admin::issue_connect_state();

		$this->assertSame(
			'REAL-KEY',
			$this->arrive_with( [ 'token' => 'REAL-KEY', 'state' => $state ] ),
			'the token from a round trip this site started is the whole point of the flow'
		);
	}

	public function test_a_token_carrying_the_wrong_state_is_ignored() {
		\ThriveDesk\Admin::issue_connect_state();

		$this->assertSame(
			'',
			$this->arrive_with( [ 'token' => 'ATTACKER-KEY', 'state' => 'guessed-state' ] ),
			'a mismatched state must not pass'
		);
	}

	/**
	 * Deprecated compatibility shape: app.thrivedesk.com does not echo `state`
	 * back yet, so a bare token still has to work - but only inside the window
	 * of a connect this site started, never as a drive-by link.
	 */
	public function test_a_bare_token_is_accepted_only_while_a_connect_is_pending() {
		\ThriveDesk\Admin::issue_connect_state();

		$this->assertSame(
			'REAL-KEY',
			$this->arrive_with( [ 'token' => 'REAL-KEY' ] ),
			'the legitimate return must keep working until the SaaS echoes the state'
		);
	}

	public function test_the_pending_state_is_good_for_exactly_one_token() {
		$state = \ThriveDesk\Admin::issue_connect_state();

		$this->arrive_with( [ 'token' => 'REAL-KEY', 'state' => $state ] );

		$this->assertSame(
			'',
			$this->arrive_with( [ 'token' => 'ATTACKER-KEY', 'state' => $state ] ),
			'the state is consumed on first use, so a captured return URL cannot be replayed'
		);
	}

	public function test_a_failed_state_check_still_consumes_the_pending_connect() {
		\ThriveDesk\Admin::issue_connect_state();

		$this->arrive_with( [ 'token' => 'ATTACKER-KEY', 'state' => 'wrong' ] );

		$this->assertFalse(
			get_transient( 'thrivedesk_connect_state' ),
			'a rejected attempt must burn the window rather than leave it open to guess at'
		);
	}

	public function test_both_connect_buttons_on_a_page_carry_the_same_state() {
		$register  = \ThriveDesk\Admin::connect_url( '/auth/register' );
		$authorize = \ThriveDesk\Admin::connect_url( '/auth/authorize' );

		$this->assertSame(
			$this->state_in( $register ),
			$this->state_in( $authorize ),
			'issuing a second state would invalidate the first button on the same screen'
		);
		$this->assertSame(
			get_transient( 'thrivedesk_connect_state' ),
			$this->state_in( $register ),
			'the state on the link has to be the one the transient holds'
		);
	}

	/**
	 * auth_return_url carries a query string of its own. Un-encoded, `page` and
	 * `auth_platform` detach and land as top-level parameters on
	 * app.thrivedesk.com instead of as part of the return URL.
	 */
	public function test_the_return_url_is_encoded_as_a_single_parameter() {
		$url = \ThriveDesk\Admin::connect_url( '/auth/authorize' );

		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		$this->assertSame(
			[ 'auth_return_url' ],
			array_keys( $query ),
			'nothing from the return URL may leak into the app\'s own query string'
		);

		parse_str( (string) wp_parse_url( $query['auth_return_url'], PHP_URL_QUERY ), $inner );

		$this->assertSame( 'thrivedesk', $inner['page'] ?? null );
		$this->assertSame( 'WordPress', $inner['auth_platform'] ?? null );
		$this->assertNotEmpty( $inner['state'] ?? '' );
	}

	/**
	 * The whole point of the chain: a planted token must not reach the input
	 * that one click posts to thrivedesk_api_key_verify.
	 */
	public function test_a_planted_token_does_not_reach_the_api_verify_field() {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => '' ] );
		update_option( 'td_helpdesk_verified', false );

		$this->reset_memo();
		$_GET['token'] = 'ATTACKER-KEY';

		ob_start();
		include THRIVEDESK_DIR . '/includes/views/pages/api-verify.php';
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'ATTACKER-KEY', $html, 'the field must not be pre-filled from a planted link' );
	}

	/**
	 * The settings screen renders on a connected site, so a planted token there
	 * is the more dangerous half: it silently swaps the key the admin believes
	 * they are looking at.
	 */
	public function test_a_planted_token_does_not_reach_the_settings_screen() {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'REAL-KEY-1234567890' ] );
		update_option( 'td_helpdesk_verified', true );

		add_filter(
			'pre_http_request',
			static fn() => [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [] ),
			]
		);

		$this->reset_memo();
		$_GET['token'] = 'ATTACKER-KEY';

		ob_start();
		include THRIVEDESK_DIR . '/includes/views/partials/settings.php';
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'ATTACKER-KEY', $html, 'a connected site must ignore a planted token entirely' );
	}

	/**
	 * Finding A step 1: `isset($_GET['token'])` forced the authorize screen on
	 * any install, so an attacker could put a connected site back in front of
	 * the "Complete Setup" button.
	 *
	 * This used to assert that the connect card was absent, because an install
	 * with an unverified key was sent to a welcome screen that had no API key
	 * field on it. It is sent to the tabs now and the card is on the Overview
	 * tab, so its presence is no longer the thing that distinguishes an
	 * attacker's link from an ordinary visit - an admin who has not connected
	 * yet is meant to see that card.
	 *
	 * What was ever dangerous is the field being pre-filled, which is what is
	 * asserted here instead: the input renders, and it renders empty.
	 */
	public function test_a_planted_token_cannot_pre_fill_the_authorize_screen() {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'REAL-KEY-1234567890' ] );
		update_option( 'td_helpdesk_verified', false );
		set_transient( 'thrivedesk_reverify_attempted', true, MINUTE_IN_SECONDS );

		$this->reset_memo();
		$_GET['token'] = 'ATTACKER-KEY';

		ob_start();
		\ThriveDesk\Admin::instance()->load_pages();
		$html = (string) ob_get_clean();

		delete_transient( 'thrivedesk_reverify_attempted' );

		$this->assertStringNotContainsString( 'ATTACKER-KEY', $html );

		// Pinned together on purpose. Asserting only that the key is absent
		// would keep passing if the field itself ever stopped rendering, and
		// then this would be testing nothing.
		$this->assertStringContainsString( 'id="td_helpdesk_api_key"', $html );
		$this->assertMatchesRegularExpression(
			'/id="td_helpdesk_api_key"[^>]*value=""/',
			$html,
			'a token nobody asked for must not reach the field that Complete Setup posts'
		);
	}
}
