<?php
/**
 * On-load migration runner.
 *
 * @package ThriveDesk
 */

namespace ThriveDeskDBMigrations\Scripts;

/**
 * Runs pending migrations on every load so plugin updates reach stores that never re-activate.
 */
class MigrationScript {

	/**
	 * Singleton instance.
	 *
	 * @var MigrationScript|null
	 */
	private static $instance = null;

	/**
	 * Hook the runner into the WordPress boot sequence.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'run' ), 10, 2 );
	}

	/**
	 * Retrieve the singleton instance.
	 *
	 * @return MigrationScript
	 */
	public static function instance(): MigrationScript {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Entry point invoked on plugins_loaded.
	 */
	public function run() {
		$this->run_migration_script();
	}

	/**
	 * Run every migration step in order.
	 */
	private function run_migration_script() {
		$this->migrate_schema();
		$this->migrate_post_sync_option();
	}

	/**
	 * Apply schema migrations on load, not just on activation, so a version bump
	 * shipped in a plugin update reaches already-installed stores that never
	 * re-activate. Gated on the stored version so it is a no-op once caught up.
	 */
	private function migrate_schema() {
		if ( (float) get_option( (string) OPTION_THRIVEDESK_DB_VERSION ) >= (float) THRIVEDESK_DB_VERSION ) {
			return;
		}

		require_once THRIVEDESK_DIR . '/database/DBMigrator.php';
		\ThriveDeskDBMigrator::migrate();
	}

	/**
	 * Fold the legacy standalone post-sync option into the unified settings array.
	 */
	private function migrate_post_sync_option() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$old_post_sync_option = get_option( 'thrivedesk_post_type_sync_option' );

		if ( false === $old_post_sync_option ) {
			return;
		}

		$td_helpdesk_settings = get_td_helpdesk_settings();

		if ( ! array_key_exists( 'td_helpdesk_post_sync', $td_helpdesk_settings ) ||
			! $td_helpdesk_settings['td_helpdesk_post_sync'] ) {
			$td_helpdesk_settings['td_helpdesk_post_sync'] = $old_post_sync_option;
			update_option( 'td_helpdesk_settings', $td_helpdesk_settings );
		}

		delete_option( 'thrivedesk_post_type_sync_option' );
	}
}
