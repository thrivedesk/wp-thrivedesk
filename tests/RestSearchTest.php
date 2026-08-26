<?php
/**
 * The doc-search route backs the portal ticket modal, which only ever renders
 * for a logged-in customer. It used to register under a bare `td-search-query`
 * namespace with `permission_callback => true`, i.e. an unauthenticated,
 * unthrottled, uncached LIKE '%...%' scan of the posts table open to the world.
 *
 * It is logged-in only now, properly namespaced, and still bounds its own cost:
 * the result set is capped regardless of the site's posts_per_page.
 *
 * The cost/visibility tests call the callback directly, which bypasses the
 * permission check by design; the permission check has its own tests below.
 *
 * @package ThriveDesk\Tests
 */

class RestSearchTest extends WP_UnitTestCase {

	private const ROUTE = '/' . \ThriveDesk\RestRoute::REST_NAMESPACE . '/docs';

	public function set_up() {
		parent::set_up();
		// Routes register on rest_api_init, which booting the server fires.
		// WooCommerce's REST controllers read the $wp_roles global during that
		// hook, so make sure it is initialised before the server boots.
		wp_roles();
		rest_get_server();
	}

	private function search( string $term ): array {
		$request = new \WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'query_string', $term );

		return \ThriveDesk\RestRoute::instance()->get_search_data( $request );
	}

	// the route itself -------------------------------------------------------

	public function test_the_route_is_registered_under_a_versioned_vendor_namespace() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( self::ROUTE, $routes, 'doc search must live under thrivedesk/v1' );
		$this->assertArrayNotHasKey(
			'/td-search-query/docs',
			$routes,
			'the bare top-level namespace must be gone'
		);
	}

	public function test_an_anonymous_caller_is_refused() {
		wp_set_current_user( 0 );

		$response = rest_get_server()->dispatch( new \WP_REST_Request( 'POST', self::ROUTE ) );

		$this->assertSame( 401, $response->get_status(), 'the doc search must not be open to the world' );
	}

	public function test_a_logged_in_customer_is_allowed() {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_post_types' => 'post' ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$response = rest_get_server()->dispatch( new \WP_REST_Request( 'POST', self::ROUTE ) );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_the_query_param_is_sanitized_by_the_route_args() {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_post_types' => 'post' ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$request = new \WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'query_string', "<script>alert(1)</script>handbook\n" );

		rest_get_server()->dispatch( $request );

		$this->assertSame( 'handbook', $request->get_param( 'query_string' ) );
	}

	// cost and visibility ----------------------------------------------------

	public function test_doc_search_returns_published_posts_only() {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_post_types' => 'post' ] );

		$published = self::factory()->post->create(
			[
				'post_title'  => 'ThriveDeskUnreleased handbook',
				'post_status' => 'publish',
			]
		);
		foreach ( [ 'draft', 'private', 'pending' ] as $status ) {
			self::factory()->post->create(
				[
					'post_title'  => "ThriveDeskUnreleased {$status} notes",
					'post_status' => $status,
				]
			);
		}

		// An editor could otherwise widen WP_Query's default status set, and the
		// route is anonymous, so unpublished content must never be reachable.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$ids = wp_list_pluck( $this->search( 'ThriveDeskUnreleased' )['data'], 'id' );

		$this->assertSame( [ $published ], array_values( $ids ), 'only the published post may be returned' );
	}

	public function test_doc_search_result_count_is_capped() {
		// A site configured to return many posts per query by default.
		update_option( 'posts_per_page', 100 );
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_post_types' => 'post' ] );

		for ( $i = 0; $i < 25; $i++ ) {
			self::factory()->post->create(
				[
					'post_title'  => "ThriveDeskDoc {$i}",
					'post_status' => 'publish',
				]
			);
		}

		$request = new \WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'query_string', 'ThriveDeskDoc' );

		$result = \ThriveDesk\RestRoute::instance()->get_search_data( $request );

		// 25 posts match and the site would return up to 100, but the endpoint caps it.
		$this->assertCount( 20, $result['data'], 'public doc search must cap its result set' );
	}
}
