<?php

namespace ThriveDeskDBMigrations;

global $td_db_version;
$td_db_version = '1.0';

class TDConversation
{
    /**
     * migration for ThriveDesk Conversation
     *
     * @since 0.7.0
     */
    public static function migrate()
    {
        global $wpdb;
        global $td_db_version;

        $charset_collate = $wpdb->get_charset_collate();

        $table_name = $wpdb->prefix . 'td_conversations';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $sql = "CREATE TABLE $table_name (
                `id` varchar(50) NOT NULL UNIQUE,
                `title` varchar(192) NOT NULL,
                `ticket_id` bigint unsigned NOT NULL,
                `inbox_id` varchar(50) NOT NULL,
                `contact` varchar(50) NOT NULL,
                `status` varchar(20) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            add_option('td_db_version', $td_db_version);
        }
    }
}