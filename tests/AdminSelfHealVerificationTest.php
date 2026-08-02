<?php
/**
 * A site that migrates to a new domain keeps its saved ThriveDesk API key in
 * the database, but the local 'td_helpdesk_verified' flag can end up stale
 * (a persistent object cache that wasn't flushed as part of the migration,
 * or a transient network hiccup while DNS/SSL settle on the new domain).
 * Admin::load_pages() must not treat that as a dead token and push the site
 * owner through a brand new authorization when the saved key still works -
 * it should transparently re-verify the saved key first, and must not do so
 * on every page load once the key really is dead.
 *
 * @package ThriveDesk\Tests
 */

class AdminSelfHealVerificationTest extends WP_UnitTestCase {

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		delete_transient( 'thrivedesk_reverify_attempted' );

		parent::tear_down();
	}

	private function load_pages(): string {
		ob_start();
		\ThriveDesk\Admin::instance()->load_pages();

		return (string) ob_get_clean();
	}

	/**
	 * Stand in for a Redis/Memcached that carried over from the install this
	 * site's database was imported onto: the shared autoloaded-options entry
	 * still holds the values it had before the migration.
	 */
	private function prime_stale_alloptions( array $stale ): void {
		$alloptions = wp_load_alloptions();

		foreach ( $stale as $option => $value ) {
			$alloptions[ $option ] = serialize( $value );
		}

		wp_cache_set( 'alloptions', $alloptions, 'options' );
	}

	public function test_a_stale_object_cache_cannot_hide_a_connected_install() {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'DB-TOKEN' ] );
		update_option( 'td_helpdesk_verified', true );
		delete_transient( 'thrivedesk_reverify_attempted' );

		$this->prime_stale_alloptions(
			[
				'td_helpdesk_settings' => [ 'td_helpdesk_api_key' => '' ],
				'td_helpdesk_verified' => false,
			]
		);

		// The settings screen populates itself over HTTP; keep it offline.
		add_filter(
			'pre_http_request',
			static fn() => [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [] ),
			]
		);

		$html = $this->load_pages();

		$this->assertStringContainsString(
			'td-api-verification-btn',
			$html,
			'the database says this install is connected, so it must get the settings screen, not the setup screen'
		);
	}

	public function test_saved_key_is_silently_reverified_when_flag_is_stale() {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'STILL-VALID-TOKEN' ] );
		update_option( 'td_helpdesk_verified', false );
		delete_transient( 'thrivedesk_reverify_attempted' );

		$captured_auth = null;
		add_filter(
			'pre_http_request',
			static function ( $pre, $args ) use ( &$captured_auth ) {
				$captured_auth = $args['headers']['Authorization'] ?? null;

				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( [ 'company' => [ 'id' => 'c1', 'name' => 'Acme' ] ] ),
				];
			},
			10,
			2
		);

		$this->load_pages();

		$this->assertSame( 'Bearer STILL-VALID-TOKEN', $captured_auth, 'the saved key must be the one re-checked against the API' );
		$this->assertTrue( \ThriveDesk\Admin::get_api_verification_status(), 'a still-valid saved token must flip verification back on automatically' );
	}

	public function test_the_probe_gives_up_long_before_max_execution_time() {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'ANY-TOKEN' ] );
		update_option( 'td_helpdesk_verified', false );
		delete_transient( 'thrivedesk_reverify_attempted' );

		$timeout = null;
		add_filter(
			'pre_http_request',
			static function ( $pre, $args ) use ( &$timeout ) {
				$timeout = $args['timeout'] ?? null;

				return new WP_Error( 'http_request_failed', 'Connection timed out' );
			},
			10,
			2
		);

		$this->load_pages();

		// This probe runs inside a page render, so a hung connection must not
		// hold the settings screen past a typical 30s max_execution_time.
		$this->assertNotNull( $timeout, 'the re-verification probe must have run' );
		$this->assertLessThanOrEqual( 15, $timeout, 'the probe must not block the admin page on the default 90s timeout' );
	}

	public function test_reverification_is_throttled_to_once_per_minute() {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'DEAD-TOKEN' ] );
		update_option( 'td_helpdesk_verified', false );
		delete_transient( 'thrivedesk_reverify_attempted' );

		$calls = 0;
		add_filter(
			'pre_http_request',
			static function ( $pre ) use ( &$calls ) {
				$calls++;

				return [
					'response' => [ 'code' => 401 ],
					'body'     => wp_json_encode( [ 'message' => 'Unauthenticated' ] ),
				];
			}
		);

		$this->load_pages();

		$this->assertSame( 1, $calls, 'the first load must re-check the saved key' );
		$this->assertNotFalse( get_transient( 'thrivedesk_reverify_attempted' ), 'the attempt must be recorded, or the throttle never closes' );

		// The key is still unverified, so without that recorded attempt this
		// would fire a live API call on every single admin page load.
		$this->load_pages();

		$this->assertSame( 1, $calls, 'a throttled retry window must not fire another live API call' );
	}
}
