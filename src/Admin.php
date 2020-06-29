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
        add_action('admin_menu', [$this, 'admin_menu'], 10);

        add_action('admin_init', [$this, 'process_admin_setting']);

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
     * Admin sub menu page
     *
     * @since 0.0.1
     * @access public
     * @return void
     */
    public function admin_menu()
    {
        add_submenu_page(
            'options-general.php',
            'ThriveDesk - Settings',
            'ThriveDesk',
            'manage_options',
            'thrivedesk-setting',
            function () {
                return thrivedesk_view('setting');
            }
        );
    }

    /**
     * Process save admin setting
     *
     * @since 0.0.1
     * @access public
     * @return void
     */
    public function process_admin_setting()
    {
        if (!isset($_POST['thrivedesk_save_settings']) || !wp_verify_nonce($_POST['thrivedesk_save_settings'], 'thrivedesk_save_settings')) return;

        $thrivedesk_options = get_option('thrivedesk_options', []);

        if (isset($_POST['api_token'])) {

            $thrivedesk_options['api_token'] = sanitize_text_field($_POST['api_token']);

            update_option('thrivedesk_options', $thrivedesk_options);
        }

        // TODO: Add admin notice
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

        // If not installed then run installation process
        if (!$installed) {
            // Set installation time
            update_option('thrivedesk_installed', time());

            // Set plugin version
            update_option('thrivedesk_version', THRIVEDESK_VERSION);

            // Create thrivedesk_options
            if (false == get_option('thrivedesk_options')) add_option('thrivedesk_options', []);

            $thrivedesk_options = get_option('thrivedesk_options', []);

            $options = ['api_token' => ''];

            update_option('thrivedesk_options', array_merge($thrivedesk_options, $options));
        }
    }
}