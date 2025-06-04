<?php

/**
 * ThriveDesk Database Migration Handler.
 *
 * @package ThriveDesk
 * @since 0.0.1
 */

/**
 * Class ThriveDeskDBMigrator for handling database migrations.
 *
 * @since 0.0.1
 */
class ThriveDeskDBMigrator {

	/**
	 * Run database migrations.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public static function migrate() {
		// Run conversation table migration.
		require_once __DIR__ . '/TDConversation.php';
		TDConversation::init();

		// Add more migrations here as needed.
		self::migrate_version_1_0_0();
	}

	/**
	 * Migrate to version 1.0.0.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	private static function migrate_version_1_0_0() {
		$current_version = get_option( OPTION_THRIVEDESK_DB_VERSION, '0.0.0' );

		if ( version_compare( $current_version, '1.0.0', '<' ) ) {
			// Run specific migrations for version 1.0.0.
			self::create_indexes();

			// Update version.
			update_option( OPTION_THRIVEDESK_DB_VERSION, '1.0.0' );
		}
	}

	/**
	 * Create additional database indexes.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	private static function create_indexes() {
		global $wpdb;

		$table_name = $wpdb->prefix . THRIVEDESK_DB_TABLE_CONVERSATION;

		// Check if table exists before creating indexes.
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		if ( $table_exists === $table_name ) {
			// Add indexes if they don't exist.
			$wpdb->query( "CREATE INDEX IF NOT EXISTS idx_status_created ON $table_name (status, created_at)" );
			$wpdb->query( "CREATE INDEX IF NOT EXISTS idx_email_status ON $table_name (customer_email, status)" );
		}
	}
}
