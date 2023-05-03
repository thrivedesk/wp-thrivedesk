<?php

namespace ThriveDeskDBMigrations\Scripts;

class MigrationScript {

	private static $instance = null;

	public function __construct() {
		register_activation_hook( THRIVEDESK_FILE, [ $this, 'run' ] );
		add_action( 'upgrader_process_complete', 'wp_td_update_completed', 10, 2 );
	}

	public static function instance(): MigrationScript {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function wp_td_update_completed( $upgrader_object, $options ) {
		if ( $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ) {
			foreach ( $options['plugins'] as $plugin ) {
				if ( $plugin == plugin_basename( THRIVEDESK_FILE ) ) {
					$this->run();
					break;
				}
			}
		}
	}

	public function run() {
		$this->runMigrationScript();
	}

	private function runMigrationScript() {
		$this->migratePostSyncOption();
	}

	private function migratePostSyncOption() {
		$old_post_sync_option = get_option( 'thrivedesk_post_type_sync_option' );

		if ( $old_post_sync_option === false ) {
			return;
		}

		$td_helpdesk_settings = get_td_helpdesk_options();

		if ( ! array_key_exists( 'td_helpdesk_post_sync', $td_helpdesk_settings ) ||
		     ! $td_helpdesk_settings['td_helpdesk_post_sync'] ) {
			$td_helpdesk_settings['td_helpdesk_post_sync'] = $old_post_sync_option;
			update_option( 'td_helpdesk_settings', $td_helpdesk_settings );
		}

		delete_option( 'thrivedesk_post_type_sync_option' );
	}
}