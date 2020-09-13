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

        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);

        register_activation_hook(THRIVEDESK_FILE, [$this, 'activate']);

        add_action('wp_ajax_thrivedesk_connect_plugin', [$this, 'ajax_connect_plugin']);
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

    public function admin_scripts()
    {
        // wp_enqueue_style('thrivedesk-admin-style', THRIVEDESK_PLUGIN_ASSETS . '/css/admin.min.css', '', THRIVEDESK_VERSION);

        wp_enqueue_script('thrivedesk-admin-script', THRIVEDESK_PLUGIN_ASSETS . '/js/admin.js', ['jquery'], THRIVEDESK_VERSION);

        wp_localize_script(
            'thrivedesk-admin-script',
            'thrivedesk',
            array('ajax_url' => admin_url('admin-ajax.php'))
        );
    }

    public function ajax_connect_plugin()
    {
        if (!isset($_POST['data']['plugin']) || !wp_verify_nonce($_POST['data']['nonce'], 'thrivedesk-connect-plugin'))  die;

        $plugin = sanitize_key($_POST['data']['plugin']);

        $api_token = md5(time());

        $thrivedesk_options = get_option('thrivedesk_options', []);
        $thrivedesk_options['api_tokens'] = $thrivedesk_options['api_tokens'] ?? [];
        $thrivedesk_options['api_tokens'][$plugin] = $api_token;

        update_option('thrivedesk_options', $thrivedesk_options);

        $hash = base64_encode(json_encode([
            'site_url' =>  get_bloginfo('url'),
            'api_token' => $api_token,
        ]));

        echo THRIVEDESK_APP_URL . '/apps/' . $plugin . '?connect=' . $hash;

        die();
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

            $options = ['api_token' => []];

            update_option('thrivedesk_options', array_merge($thrivedesk_options, $options));
        }
    }
}