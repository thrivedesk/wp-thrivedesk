<?php

require_once(ABSPATH.'wp-admin/includes/upgrade.php');
require_once(THRIVEDESK_DIR.'/database/TDConversation.php');


class ThriveDeskDBMigrator
{
    /**
     * migrations
     */
    public static function migrate()
    {
        \ThriveDeskDBMigrations\TDConversation::migrate();
    }
}