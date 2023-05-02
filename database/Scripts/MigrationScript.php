<?php

namespace ThriveDeskDBMigrations\Scripts;

class MigrationScript {

	private static $instance = null;

	public function __construct() {
		$this->run();
	}

	public static function instance(): MigrationScript {
		if ( !isset(self::$instance) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function run() {
		$this->runMigrationScript();
	}

	private function runMigrationScript(  ) {
		$this->migratePostSyncOption();
	}

	private function migratePostSyncOption( ) {
		$old_post_sync_option = get_option( 'thrivedesk_post_type_sync_option' );

		if ( !$old_post_sync_option ) {
			return;
		}

		$td_helpdesk_settings = get_td_helpdesk_options();
		$td_helpdesk_settings['td_helpdesk_post_sync'] = $old_post_sync_option;
		update_option( 'td_helpdesk_settings', $td_helpdesk_settings );
		delete_option( 'thrivedesk_post_type_sync_option' );
	}
}