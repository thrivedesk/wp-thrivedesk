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
     * @since  0.0.1
     * @access private
     */
    private function __construct()
    {
	    // allow to redirect to the getting started page
	    register_activation_hook(THRIVEDESK_FILE, [$this, 'add_option_for_welcome_page_redirection']);

        add_action('thrivedesk_db_migrate', [$this, 'db_migrate']);

        add_action('admin_menu', [$this, 'admin_menu'], 10);

        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);

		add_action('admin_init', [$this, 'redirect_to_getting_started_page']);

        register_activation_hook(THRIVEDESK_FILE, [$this, 'activate']);

        add_action('wp_ajax_thrivedesk_connect_plugin', [$this, 'ajax_connect_plugin']);
        add_action('wp_ajax_thrivedesk_disconnect_plugin', [$this, 'ajax_disconnect_plugin']);
    }

	public function add_option_for_welcome_page_redirection(): void {
		add_option('wp_thrivedesk_activation_redirect', true);
	}

	/**
	 * After successful activation, redirect to the welcome page.
	 * must not redirect if multi activation.
	 * @return void
	 */
	public function redirect_to_getting_started_page(): void {

		if (isset($_GET['activate-multi']) || is_network_admin()) {
			return;
		}

		if (get_option('wp_thrivedesk_activation_redirect', false)) {
			delete_option('wp_thrivedesk_activation_redirect');
			exit( wp_redirect("options-general.php?page=thrivedesk-setting#welcome") );
		}
	}

    public function db_migrate()
    {
        require_once(THRIVEDESK_DIR . '/database/DBMigrator.php');
        \ThriveDeskDBMigrator::migrate();
    }

    /**
     * Main Admin Instance.
     *
     * Ensures that only one instance of Admin exists in memory at any one
     * time. Also prevents needing to define globals all over the place.
     *
     * @return object|Admin
     * @access public
     * @since  0.0.1
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
     * @return void
     * @since  0.0.1
     * @access public
     */
    public function admin_menu(): void
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
     * Enqueue style
     *
     * @param mixed $hook
     * @return void
     */
    public function admin_scripts($hook): void
    {
        if ('settings_page_thrivedesk-setting' == $hook) {
            wp_enqueue_style('thrivedesk-admin-style', THRIVEDESK_PLUGIN_ASSETS . '/css/admin.css', '', THRIVEDESK_VERSION);
        }

        wp_enqueue_script('thrivedesk-admin-script', THRIVEDESK_PLUGIN_ASSETS . '/js/admin.js', ['jquery'], THRIVEDESK_VERSION);

        wp_localize_script(
            'thrivedesk-admin-script',
            'thrivedesk',
            array(
				'ajax_url' => admin_url('admin-ajax.php'),
	            'wp_json_url' => site_url('wp-json'),
            )
        );

        if (class_exists('BWF_Contacts')) {
            $asset_file = include(THRIVEDESK_PLUGIN_ASSETS_PATH . '/js/wp-scripts/thrivedesk-autonami-tab.asset.php');

            wp_enqueue_script('thrivedesk-autonami-script', THRIVEDESK_PLUGIN_ASSETS . '/js/wp-scripts/thrivedesk-autonami-tab.js', $asset_file['dependencies'], $asset_file['version'] ?? THRIVEDESK_VERSION);
        }
    }

    /**
     * Handle plugin connect action
     *
     * @return void
     */
    public function ajax_connect_plugin()
    {
        error_log(json_encode($_POST['data']));

        if (!isset($_POST['data']['plugin']) || !wp_verify_nonce($_POST['data']['nonce'], 'thrivedesk-plugin-action')) die;

        $plugin = sanitize_key($_POST['data']['plugin']);

        $api_token = md5(time());

        $thrivedesk_options          = get_option('thrivedesk_options', []);
        $thrivedesk_options[$plugin] = $thrivedesk_options[$plugin] ?? [];
        $thrivedesk_options[$plugin] = [
            'api_token' => $api_token,
            'connected' => false,
        ];

        update_option('thrivedesk_options', $thrivedesk_options);

        $hash = base64_encode(json_encode([
            'store_url'   => get_bloginfo('url'),
            'api_token'   => $api_token,
            'cancel_url'  => admin_url('options-general.php?page=thrivedesk-setting&plugin=' . $plugin . '&td-activated=false'),
            'success_url' => admin_url('options-general.php?page=thrivedesk-setting&plugin=' . $plugin . '&td-activated=true')
        ]));

        echo THRIVEDESK_APP_URL . '/apps/' . esc_attr($plugin) . '?connect=' . esc_attr($hash);

        die();
    }

    /**
     * Handle plugin disconnect action
     *
     * @return void
     */
    public function ajax_disconnect_plugin(): void
    {
        if (!isset($_POST['data']['plugin']) || !wp_verify_nonce($_POST['data']['nonce'], 'thrivedesk-plugin-action')) die;

        $plugin = sanitize_key($_POST['data']['plugin']);

        $thrivedesk_options          = get_option('thrivedesk_options', []);
        $thrivedesk_options[$plugin] = $thrivedesk_options[$plugin] ?? [];
        $thrivedesk_options[$plugin] = [
            'api_token' => '',
            'connected' => false,
        ];

        update_option('thrivedesk_options', $thrivedesk_options);

        echo 1;

        die();
    }

    /**
     * Plugin activate.
     *
     * @return void
     * @since  0.0.1
     * @access public
     */
    public function activate(): void
    {
        $installed = get_option('thrivedesk_installed');

        if (!$installed) {
            // Set installation time
            update_option('thrivedesk_installed', time());

            // Set plugin version
            update_option('thrivedesk_version', THRIVEDESK_VERSION);

            // Create thrivedesk_options
            if (false == get_option('thrivedesk_options')) update_option('thrivedesk_options', []);
        }

        // migrate action for thrivedesk database
        do_action('thrivedesk_db_migrate');
    }
}
