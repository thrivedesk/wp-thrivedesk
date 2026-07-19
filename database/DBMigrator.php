<?php
/**
 * Dispatches ThriveDesk database migrations.
 *
 * @package ThriveDesk
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once THRIVEDESK_DIR . '/database/TDConversation.php';

/**
 * Runs each registered migration in order.
 */
class ThriveDeskDBMigrator {

	/**
	 * ThriveDesk Database migrations
	 *
	 * @since 0.7.0
	 */
	public static function migrate() {
		\ThriveDeskDBMigrations\TDConversation::migrate();
	}
}
