<?php
/**
 * The public doc-search route (permission_callback => true) is anonymous by
 * design for the portal ticket modal, so it must bound its own cost: the result
 * set is capped regardless of the site's posts_per_page.
 *
 * @package ThriveDesk\Tests
 */

class RestSearchTest extends WP_UnitTestCase {

	private function search( string $term ): array {
		$request = new \WP_REST_Request( 'POST', '/td-search-query/docs' );
		$request->set_param( 'query_string', $term );

		return \ThriveDesk\RestRoute::instance()->get_search_data( $request );
	}

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

		$request = new \WP_REST_Request( 'POST', '/td-search-query/docs' );
		$request->set_param( 'query_string', 'ThriveDeskDoc' );

		$result = \ThriveDesk\RestRoute::instance()->get_search_data( $request );

		// 25 posts match and the site would return up to 100, but the endpoint caps it.
		$this->assertCount( 20, $result['data'], 'public doc search must cap its result set' );
	}
}
