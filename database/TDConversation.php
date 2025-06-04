<?php

namespace ThriveDeskDBMigrations;

class TDConversation {

	/**
	 * migration for ThriveDesk Conversation
	 *
	 * @since 0.7.0
	 */
	public static function migrate() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . THRIVEDESK_DB_TABLE_CONVERSATION;

		// Use prepared statement for checking if table exists
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		if ( $table_exists != $table_name ) {
			$sql = $wpdb->prepare(
				'CREATE TABLE %i (
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
            ) %s',
				$table_name,
				$charset_collate
			);

			dbDelta( $sql );
			add_option( (string) OPTION_THRIVEDESK_DB_VERSION, THRIVEDESK_DB_VERSION );
		} elseif ( get_option( (string) OPTION_THRIVEDESK_DB_VERSION ) < THRIVEDESK_DB_VERSION ) {
			maybe_add_column( $table_name, 'deleted_at', "ALTER TABLE $table_name ADD deleted_at timestamp NULL DEFAULT NULL;" );
			update_option( (string) OPTION_THRIVEDESK_DB_VERSION, THRIVEDESK_DB_VERSION );
		}
	}
}
