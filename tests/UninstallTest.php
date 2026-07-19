<?php
/**
 * There was no uninstall path: the td_conversations table orphaned forever and
 * options were never removed on delete. uninstall.php must remove the plugin's
 * options and drop the table.
 *
 * The DROP is asserted by capturing the query rather than by SHOW TABLES,
 * because DDL does not take visible effect inside the WP_UnitTestCase
 * transaction. Option removal is observable, so it is asserted directly.
 *
 * @package ThriveDesk\Tests
 */

class UninstallTest extends WP_UnitTestCase {

	public function test_uninstall_removes_options_and_drops_the_table() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'thrivedesk/thrivedesk.php' );
		}

		update_option( 'td_helpdesk_settings', array( 'td_helpdesk_api_key' => 'x' ) );

		global $wpdb;
		$queries = array();
		$capture = function ( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		};

		add_filter( 'query', $capture );
		require THRIVEDESK_DIR . '/uninstall.php';
		remove_filter( 'query', $capture );

		$this->assertFalse( get_option( 'td_helpdesk_settings' ), 'options should be removed' );

		$table   = $wpdb->prefix . 'td_conversations';
		$dropped = false;
		foreach ( $queries as $query ) {
			// The test harness rewrites DROP TABLE to DROP TEMPORARY TABLE for
			// isolation; either form proves uninstall issues the drop.
			if ( preg_match( '/drop\s+(temporary\s+)?table/i', $query ) && false !== strpos( $query, $table ) ) {
				$dropped = true;
				break;
			}
		}
		$this->assertTrue( $dropped, 'uninstall must issue a DROP TABLE for td_conversations' );
	}
}
