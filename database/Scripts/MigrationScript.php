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
		$thrivedesk_post_type_sync_option = get_option( 'thrivedesk_post_type_sync_option');
		if ( $thrivedesk_post_type_sync_option ) {
			$td_post_type_sync_option = get_option( 'td_helpdesk_post_sync');
			if ( $td_post_type_sync_option ) {
				update_option( 'td_helpdesk_post_sync', $thrivedesk_post_type_sync_option );
				delete_option( 'thrivedesk_post_type_sync_option' );
			}
		}
	}
}