<?php
/**
 * The workspace card's data.
 *
 * The interesting case is the one that prompted it: a key that reads some
 * endpoints and is refused others. The plugin used to render that as "none
 * found", so the tests here care most about a 403 staying visible as a 403.
 *
 * @package ThriveDesk\Tests
 */

use ThriveDesk\Services\WorkspaceService;

class WorkspaceSummaryTest extends WP_UnitTestCase {

	/** @var array<string,int> path fragment => status to answer with */
	private $routes = [];

	/** @var string[] */
	private $requested = [];

	public function set_up() {
		parent::set_up();

		WorkspaceService::flush();
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'TEST-KEY' ] );
		update_option(
			'td_helpdesk_system_info',
			[
				'company' => 'Woo Demo',
				'slug'    => 'monster',
				'options' => [ 'timezone' => 'UTC' ],
			]
		);

		add_filter( 'pre_http_request', [ $this, 'intercept' ], 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'intercept' ], 10 );
		WorkspaceService::flush();
		wp_clear_scheduled_hook( WorkspaceService::REFRESH_HOOK );
		delete_option( 'td_helpdesk_settings' );
		delete_option( 'td_helpdesk_system_info' );
		parent::tear_down();
	}

	/**
	 * Answer from $this->routes instead of reaching ThriveDesk.
	 *
	 * @param mixed  $preempt Short-circuit value.
	 * @param array  $args    Request args.
	 * @param string $url     Request URL.
	 *
	 * @return array
	 */
	public function intercept( $preempt, $args, $url ) {
		$this->requested[] = $url;

		foreach ( $this->routes as $fragment => $status ) {
			if ( false !== strpos( $url, $fragment ) ) {
				return [
					'response' => [ 'code' => $status, 'message' => '' ],
					'body'     => 200 === $status ? $this->body_for( $fragment ) : '{"message":"This action is unauthorized."}',
					'headers'  => [],
					'cookies'  => [],
				];
			}
		}

		return [ 'response' => [ 'code' => 404, 'message' => '' ], 'body' => '{}', 'headers' => [], 'cookies' => [] ];
	}

	private function body_for( string $fragment ): string {
		if ( false !== strpos( $fragment, 'billing' ) ) {
			return wp_json_encode(
				[
					'overview' => [
						'label'                    => 'Startup - LTD',
						'slug'                     => 'startup-ltd',
						'billing_type'             => 'One Time',
						'is_subscription_expired'  => false,
					],
				]
			);
		}

		return '{}';
	}

	private function all_reachable(): void {
		$this->routes = [
			'/v1/me'                    => 200,
			'/v1/billing/plans/current' => 200,
			'/v1/assistants'            => 200,
			'/v1/inboxes'               => 200,
			'/v1/knowledgebases'        => 200,
		];
	}

	public function test_workspace_identity_comes_from_stored_info_without_a_request() {
		$this->all_reachable();

		$summary = WorkspaceService::summary( true );

		$this->assertSame( 'Woo Demo', $summary['workspace']['name'] );
		$this->assertSame( 'monster', $summary['workspace']['slug'] );
		$this->assertSame( 'UTC', $summary['workspace']['timezone'] );
	}

	public function test_a_refused_endpoint_reports_its_status_rather_than_looking_empty() {
		$this->all_reachable();
		$this->routes['/v1/assistants']     = 403;
		$this->routes['/v1/inboxes']        = 403;
		$this->routes['/v1/knowledgebases'] = 403;

		$summary = WorkspaceService::summary( true );

		$this->assertTrue( $summary['api']['account']['ok'] );
		$this->assertTrue( $summary['api']['billing']['ok'] );

		foreach ( [ 'assistants', 'inboxes', 'knowledgebase' ] as $refused ) {
			$this->assertFalse( $summary['api'][ $refused ]['ok'], $refused );
			$this->assertSame( 403, $summary['api'][ $refused ]['status'], $refused );
		}
	}

	public function test_plan_is_reduced_to_what_the_card_shows() {
		$this->all_reachable();

		$plan = WorkspaceService::summary( true )['plan'];

		$this->assertSame( 'Startup - LTD', $plan['label'] );
		$this->assertSame( 'startup-ltd', $plan['slug'] );
		$this->assertSame( 'One Time', $plan['billing_type'] );
		$this->assertFalse( $plan['expired'] );
	}

	/**
	 * `portal` is derived, not returned by ThriveDesk: it has to agree with the
	 * allowlist the portal itself is gated on.
	 */
	public function test_portal_entitlement_matches_the_gate_the_portal_uses() {
		$this->all_reachable();

		$plan = WorkspaceService::summary( true )['plan'];

		$this->assertSame(
			\ThriveDesk\Services\PortalService::instance()->is_portal_plan( 'startup-ltd' ),
			$plan['portal']
		);
		$this->assertFalse( $plan['portal'], 'startup-ltd is not on the portal allowlist' );
	}

	public function test_a_rejected_key_stops_after_the_account_probe() {
		$this->routes   = [ '/v1/me' => 401 ];
		$this->requested = [];

		$summary = WorkspaceService::summary( true );

		$this->assertFalse( $summary['connected'] );
		$this->assertCount( 1, $this->requested, 'four more probes would all fail the same way, slowly' );
		$this->assertSame( [ 'account' ], array_keys( $summary['api'] ) );
	}

	public function test_no_api_key_makes_no_requests_at_all() {
		delete_option( 'td_helpdesk_settings' );
		$this->requested = [];

		$summary = WorkspaceService::summary( true );

		$this->assertFalse( $summary['connected'] );
		$this->assertSame( [], $this->requested );
	}

	public function test_the_answer_is_cached_rather_than_probed_per_pageview() {
		$this->all_reachable();

		WorkspaceService::summary( true );
		$this->requested = [];

		WorkspaceService::summary();

		$this->assertSame( [], $this->requested );
	}

	public function test_saving_settings_drops_what_is_stored() {
		$this->all_reachable();
		WorkspaceService::summary( true );

		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'A-DIFFERENT-KEY' ] );

		$this->assertFalse( get_option( WorkspaceService::SUMMARY_OPTION ) );
	}

	/**
	 * The reason this is an option rather than a transient. Once something is
	 * stored, no page render may pay for the probes again - a stale answer is
	 * served and cron replaces it.
	 */
	public function test_a_stale_summary_is_served_rather_than_rebuilt_in_the_request() {
		$this->all_reachable();
		WorkspaceService::summary( true );

		$stored               = get_option( WorkspaceService::SUMMARY_OPTION );
		$stored['checked_at'] = time() - ( WorkspaceService::SUMMARY_TTL + 60 );
		update_option( WorkspaceService::SUMMARY_OPTION, $stored, false );

		$this->requested = [];
		$summary         = WorkspaceService::summary();

		$this->assertSame( [], $this->requested, 'a stale read must not block on HTTP' );
		$this->assertSame( 'Woo Demo', $summary['workspace']['name'] );
		$this->assertNotFalse(
			wp_next_scheduled( WorkspaceService::REFRESH_HOOK ),
			'a stale read should hand the work to cron'
		);
	}

	public function test_the_cron_refresh_replaces_what_is_stored() {
		$this->all_reachable();
		WorkspaceService::summary( true );

		$this->routes['/v1/assistants'] = 403;
		WorkspaceService::refresh();

		$summary = get_option( WorkspaceService::SUMMARY_OPTION );

		$this->assertSame( 403, $summary['api']['assistants']['status'] );
	}
}
