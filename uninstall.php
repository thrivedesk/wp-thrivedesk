<?php
/**
 * Uninstall cleanup for the ThriveDesk plugin.
 *
 * WordPress loads this file when the plugin is deleted. It runs standalone, so
 * nothing here may rely on the plugin's classes or THRIVEDESK_* constants being
 * defined. Removes every plugin option and transient and drops the
 * conversations table.
 *
 * On multisite every site has its own options table and its own conversations
 * table, and WordPress runs this file exactly once for the whole network - so
 * cleaning only the current site would leave every other subsite's stored
 * td_helpdesk_api_key behind.
 *
 * @package ThriveDesk
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$td_options = array(
	'td_db_version',
	'thrivedesk_options',
	'td_helpdesk_system_info',
	'td_helpdesk_settings',
	'td_helpdesk_options',
	'td_helpdesk_verified',
	'thrivedesk_installed',
	'thrivedesk_version',
	'td_inbox_settings',
	'td_assistant_settings',
	'td_user_account_pages',
	'wp_thrivedesk_activation_redirect',
	'td_flush_rewrite_needed',
	'thrivedesk_post_type_sync_option',
	'td_workspace_summary',
);

if ( ! function_exists( 'thrivedesk_uninstall_current_site' ) ) {
	/**
	 * Remove every trace of the plugin from whichever site is currently active.
	 *
	 * @param array $td_options Option names to delete.
	 * @return void
	 */
	function thrivedesk_uninstall_current_site( array $td_options ) {
		global $wpdb;

		foreach ( $td_options as $td_option ) {
			delete_option( $td_option );
		}

		// The plugin's constants are not loaded during uninstall, so the
		// cleanup cron hook is named literally here; it must match
		// includes/helper.php. Cron is per site on multisite.
		wp_clear_scheduled_hook( 'thrivedesk_cleanup_expired_transients' );
		wp_clear_scheduled_hook( 'thrivedesk_refresh_workspace_summary' );

		// Remove ThriveDesk transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", '_transient_%thrivedesk%' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", '_transient_timeout_%thrivedesk%' ) );

		// Drop the conversations table. The name is hardcoded (the plugin's
		// constants are not loaded during uninstall) and built only from
		// $wpdb->prefix, which switch_to_blog() keeps pointed at the active
		// site, so the interpolation is safe; a table identifier cannot be
		// bound via prepare().
		$td_table = $wpdb->prefix . 'td_conversations';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS `$td_table`" );
	}
}

if ( is_multisite() ) {
	// number => 0 lifts WP_Site_Query's default 100-site cap: a partial sweep
	// here is exactly the bug being fixed.
	$td_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $td_site_ids as $td_site_id ) {
		switch_to_blog( $td_site_id );
		thrivedesk_uninstall_current_site( $td_options );
		restore_current_blog();
	}
} else {
	thrivedesk_uninstall_current_site( $td_options );
}
