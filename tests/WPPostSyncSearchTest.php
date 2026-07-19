<?php
/**
 * WPPostSync::get_post_search_result queries the admin-selected post types for
 * the portal search. It short-circuits when no sync types are configured and
 * returns matched posts otherwise.
 *
 * @package ThriveDesk\Tests
 */

class WPPostSyncSearchTest extends WP_UnitTestCase {

	public function test_returns_empty_when_no_sync_types_configured() {
		update_option( 'td_helpdesk_settings', array() );

		$result = \ThriveDesk\Plugins\WPPostSync::instance()->get_post_search_result( 'anything' );

		$this->assertSame( array( 'data' => array() ), $result );
	}

	public function test_returns_matching_posts_for_configured_types() {
		update_option( 'td_helpdesk_settings', array( 'td_helpdesk_post_sync' => array( 'post' ) ) );
		$id = self::factory()->post->create(
			array(
				'post_title'  => 'ThriveDeskFindMe',
				'post_status' => 'publish',
			)
		);

		$result = \ThriveDesk\Plugins\WPPostSync::instance()->get_post_search_result( 'ThriveDeskFindMe' );

		$this->assertArrayHasKey( 'data', $result );
		$this->assertArrayHasKey( $id, $result['data'] );
		$this->assertSame( $id, $result['data'][ $id ]['id'] );
	}
}
