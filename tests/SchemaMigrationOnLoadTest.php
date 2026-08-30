<?php
/**
 * The schema migration used to run only from the activation hook, so a version
 * bump shipped in a plugin update (no re-activation) never applied. The on-load
 * MigrationScript must catch a store up when td_db_version is behind.
 *
 * The gate also used to compare (float) casts. Float formatting is
 * locale-sensitive on PHP 7.4 - a locale rendering 1.2 as "1,2" casts back to
 * 1.0 and leaves the gate permanently open - and a float cannot tell 1.10 from
 * 1.1. It is a version string compared with version_compare() now.
 *
 * @package ThriveDesk\Tests
 */

class SchemaMigrationOnLoadTest extends WP_UnitTestCase {

	public function test_schema_migration_runs_on_load_when_version_is_behind() {
		require_once THRIVEDESK_DIR . '/database/DBMigrator.php';
		// The table exists (as on any installed site)...
		\ThriveDeskDBMigrator::migrate();
		// ...but the stored schema version is behind after an update with no re-activation.
		update_option( 'td_db_version', '1.1' );

		\ThriveDeskDBMigrations\Scripts\MigrationScript::instance()->run();

		$this->assertSame( THRIVEDESK_DB_VERSION, get_option( 'td_db_version' ) );
	}

	public function test_the_db_version_is_a_string_not_a_float() {
		$this->assertIsString( THRIVEDESK_DB_VERSION );
	}

	public function test_a_caught_up_store_is_left_alone() {
		require_once THRIVEDESK_DIR . '/database/DBMigrator.php';
		\ThriveDeskDBMigrator::migrate();
		update_option( 'td_db_version', THRIVEDESK_DB_VERSION );

		\ThriveDeskDBMigrations\Scripts\MigrationScript::instance()->run();

		$this->assertSame( THRIVEDESK_DB_VERSION, get_option( 'td_db_version' ) );
	}

	/**
	 * A float gate read 1.10 as 1.1 and considered a 1.10 store behind a 1.2
	 * release. version_compare() orders the segments properly.
	 */
	public function test_point_releases_order_by_segment_not_by_float_value() {
		$this->assertTrue( version_compare( '1.10', '1.2', '>' ) );
		$this->assertFalse( version_compare( '1.10', '1.2', '<' ) );
	}

	/**
	 * A store that stored the version back when the constant was a float still
	 * reads as caught up, so the upgrade does not re-run the migration.
	 */
	public function test_a_legacy_float_value_is_still_seen_as_current() {
		require_once THRIVEDESK_DIR . '/database/DBMigrator.php';
		\ThriveDeskDBMigrator::migrate();
		update_option( 'td_db_version', 1.2 );

		$this->assertTrue(
			version_compare( (string) get_option( 'td_db_version' ), (string) THRIVEDESK_DB_VERSION, '>=' )
		);
	}

	/**
	 * The create branch used add_option, which is a silent no-op when a stale
	 * td_db_version row is already present - leaving the gate open forever.
	 */
	public function test_a_stale_version_row_is_overwritten_on_create() {
		update_option( 'td_db_version', '0.1' );

		require_once THRIVEDESK_DIR . '/database/DBMigrator.php';
		\ThriveDeskDBMigrator::migrate();

		$this->assertSame( THRIVEDESK_DB_VERSION, get_option( 'td_db_version' ) );
	}
}
