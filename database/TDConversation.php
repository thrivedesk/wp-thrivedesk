<?php
/**
 * Conversation table migration.
 *
 * @package ThriveDesk
 */

namespace ThriveDeskDBMigrations;

/**
 * Creates and upgrades the ThriveDesk conversation table.
 */
class TDConversation {

	/**
	 * Migration for ThriveDesk Conversation
	 *
	 * @since 0.7.0
	 */
	public static function migrate() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . THRIVEDESK_DB_TABLE_CONVERSATION;

		// Direct, uncached DDL against our own table is the point of this migration.
		// $wpdb->prefix can contain an underscore, a LIKE wildcard, so the
		// pattern is escaped as well as prepared. The row that comes back is
		// still the real table name, so the comparison below is unaffected.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) !== $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
			$sql = "CREATE TABLE $table_name (
                `id` varchar(50) NOT NULL UNIQUE,
                `title` varchar(192) NOT NULL,
                `ticket_id` bigint unsigned NOT NULL,
                `inbox_id` varchar(50) NOT NULL,
                `contact` varchar(50) NOT NULL,
                `status` varchar(20) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `deleted_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;";

			dbDelta( $sql );
			// update_option, not add_option: a stale td_db_version row left by
			// an earlier install makes add_option a silent no-op, and the gate
			// then stays open forever.
			update_option( (string) OPTION_THRIVEDESK_DB_VERSION, THRIVEDESK_DB_VERSION );
		} elseif ( version_compare( (string) get_option( (string) OPTION_THRIVEDESK_DB_VERSION ), (string) THRIVEDESK_DB_VERSION, '<' ) ) {
			maybe_add_column( $table_name, 'deleted_at', "ALTER TABLE $table_name ADD deleted_at timestamp NULL DEFAULT NULL;" );
			update_option( (string) OPTION_THRIVEDESK_DB_VERSION, THRIVEDESK_DB_VERSION );
		}
	}
}
