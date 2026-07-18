<?php
/**
 * The schema migration used to run only from the activation hook, so a version
 * bump shipped in a plugin update (no re-activation) never applied. The on-load
 * MigrationScript must catch a store up when td_db_version is behind.
 *
 * @package ThriveDesk\Tests
 */

class SchemaMigrationOnLoadTest extends WP_UnitTestCase {

	public function test_schema_migration_runs_on_load_when_version_is_behind() {
		require_once THRIVEDESK_DIR . '/database/DBMigrator.php';
		// The table exists (as on any installed site)...
		\ThriveDeskDBMigrator::migrate();
		// ...but the stored schema version is behind after an update with no re-activation.
		update_option( 'td_db_version', 1.1 );

		\ThriveDeskDBMigrations\Scripts\MigrationScript::instance()->run();

		$this->assertEquals( THRIVEDESK_DB_VERSION, get_option( 'td_db_version' ) );
	}
}
