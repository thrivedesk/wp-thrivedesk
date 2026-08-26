<?php
/**
 * Uninstall cleanup for the ThriveDesk plugin.
 *
 * WordPress loads this file when the plugin is deleted. It runs standalone, so
 * nothing here may rely on the plugin's classes or THRIVEDESK_* constants being
 * defined. Removes every plugin option and transient and drops the
 * conversations table.
 *
 * @package ThriveDesk
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

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
);

foreach ( $td_options as $td_option ) {
	delete_option( $td_option );
}

// The plugin's constants are not loaded during uninstall, so the cleanup cron
// hook is named literally here; it must match includes/helper.php.
wp_clear_scheduled_hook( 'thrivedesk_cleanup_expired_transients' );

// Remove ThriveDesk transients.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", '_transient_%thrivedesk%' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", '_transient_timeout_%thrivedesk%' ) );

// Drop the conversations table. The name is hardcoded (the plugin's constants
// are not loaded during uninstall) and built only from $wpdb->prefix, so the
// interpolation is safe; a table identifier cannot be bound via prepare().
$td_table = $wpdb->prefix . 'td_conversations';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS `$td_table`" );
