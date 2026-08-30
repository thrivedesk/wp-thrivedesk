<?php
/**
 * has_portal_access() runs on every portal page render, and it used to cache
 * only the positive answer. A store on a non-entitled plan - or one whose plan
 * lookup failed - stored nothing, so every single render re-called the
 * ThriveDesk API, on a 90 second timeout, while holding a PHP worker. Any
 * logged-in Subscriber could pin the pool just by reloading the portal.
 *
 * The answer is cached either way now, as a 'yes'/'no' sentinel because a
 * transient holding false is indistinguishable from a miss, and the lookup
 * behind a miss uses a short render timeout.
 *
 * @package ThriveDesk\Tests
 */

class PortalAccessCacheTest extends WP_UnitTestCase {

	/** @var int */
	private $calls = 0;

	/** @var array */
	private $timeouts = array();

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		delete_transient( \ThriveDesk\Services\PortalService::PORTAL_ACCESS_TRANSIENT );
		parent::tear_down();
	}

	/**
	 * Count plan lookups and record the timeout each one was given.
	 *
	 * @param array $body Response body to hand back.
	 */
	private function stub_plan_api( array $body ): void {
		$this->calls    = 0;
		$this->timeouts = array();

		add_filter(
			'pre_http_request',
			function ( $preempt, $args ) use ( $body ) {
				++$this->calls;
				$this->timeouts[] = $args['timeout'] ?? null;

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode( $body ),
				);
			},
			10,
			2
		);
	}

	public function test_a_denied_plan_is_cached_so_the_api_is_not_re_called_every_render() {
		$this->stub_plan_api( array( 'overview' => array( 'slug' => 'some_plan_without_the_portal' ) ) );

		$service = new \ThriveDesk\Services\PortalService();

		$this->assertFalse( $service->has_portal_access() );
		$this->assertFalse( $service->has_portal_access() );
		$this->assertFalse( $service->has_portal_access() );

		$this->assertSame( 1, $this->calls, 'a "no" answer must be cached, not re-fetched on every render' );
		$this->assertSame( 'no', get_transient( \ThriveDesk\Services\PortalService::PORTAL_ACCESS_TRANSIENT ) );
	}

	public function test_a_failed_lookup_is_also_cached() {
		$this->calls = 0;
		add_filter(
			'pre_http_request',
			function () {
				++$this->calls;
				return new WP_Error( 'http_request_failed', 'Could not resolve host' );
			}
		);

		$service = new \ThriveDesk\Services\PortalService();

		$this->assertFalse( $service->has_portal_access() );
		$this->assertFalse( $service->has_portal_access() );

		$this->assertSame( 1, $this->calls, 'an API failure must not re-fetch on the next render' );
	}

	public function test_a_granted_plan_is_cached_as_the_yes_sentinel() {
		$this->stub_plan_api( array( 'overview' => array( 'slug' => 'pro' ) ) );

		$service = new \ThriveDesk\Services\PortalService();

		$this->assertTrue( $service->has_portal_access() );
		$this->assertTrue( $service->has_portal_access() );

		$this->assertSame( 1, $this->calls );
		$this->assertSame( 'yes', get_transient( \ThriveDesk\Services\PortalService::PORTAL_ACCESS_TRANSIENT ) );
	}

	/**
	 * Sites upgrading from the previous release hold a truthy transient rather
	 * than the sentinel. Reading that as a miss would send every one of them
	 * back to the API on the first portal render after deploy, which is the
	 * stampede the sentinel exists to prevent.
	 */
	public function test_a_legacy_truthy_cache_is_still_read_as_granted() {
		$this->stub_plan_api( array( 'overview' => array( 'slug' => 'pro' ) ) );

		set_transient( \ThriveDesk\Services\PortalService::PORTAL_ACCESS_TRANSIENT, true, HOUR_IN_SECONDS );

		$service = new \ThriveDesk\Services\PortalService();

		$this->assertTrue( $service->has_portal_access() );
		$this->assertSame( 0, $this->calls, 'a legacy cached grant must not re-call the API' );
	}

	public function test_the_render_path_lookup_uses_the_short_timeout() {
		$this->stub_plan_api( array( 'overview' => array( 'slug' => 'pro' ) ) );

		( new \ThriveDesk\Services\PortalService() )->has_portal_access();

		$this->assertSame(
			\ThriveDesk\Services\PortalService::RENDER_TIMEOUT,
			$this->timeouts[0],
			'the portal render must not block for the default 90s API timeout'
		);
		$this->assertLessThanOrEqual( 10, $this->timeouts[0] );
	}

	public function test_clearing_transients_drops_the_cached_answer() {
		set_transient( \ThriveDesk\Services\PortalService::PORTAL_ACCESS_TRANSIENT, 'no', HOUR_IN_SECONDS );

		( new \ThriveDesk\Services\TDApiService() )->clearAllTransients();

		$this->assertFalse( get_transient( \ThriveDesk\Services\PortalService::PORTAL_ACCESS_TRANSIENT ) );
	}
}
