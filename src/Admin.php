<?php

namespace ThriveDesk;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

final class Admin
{
    /**
     * The single instance of this class
     */
    private static $instance = null;

    /**
     * Construct Admin class.
     *
     * @since 0.0.1
     * @access private
     */
    private function __construct()
    {
        register_activation_hook(THRIVEDESK_FILE, [$this, 'activate']);
    }

    /**
     * Main Admin Instance.
     *
     * Ensures that only one instance of Admin exists in memory at any one
     * time. Also prevents needing to define globals all over the place.
     *
     * @since 0.0.1
     * @return object|Admin
     * @access public
     */
    public static function instance()
    {
        if (!isset(self::$instance) && !(self::$instance instanceof Admin)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Plugin activate.
     *
     * @since 0.0.1
     * @access public
     * @return void
     */
    public function activate()
    {
        $installed = get_option('thrivedesk_installed');

        if (!$installed) {
            // Set installation time
            update_option('thrivedesk_installed', time());

            // Set plugin version
            update_option('thrivedesk_version', THRIVEDESK_VERSION);
        }
    }
}