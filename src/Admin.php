<?php

namespace ThriveDesk;
use WP_Query;

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
        add_action('admin_menu', [$this, 'admin_menu'], 10);

        add_action('activated_plugin', [$this, 'create_portal_page'], 10);

        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);

		add_action('admin_init', [$this, 'redirect_to_getting_started_page']);

        add_action('wp_ajax_thrivedesk_connect_plugin', [$this, 'ajax_connect_plugin']);

        add_action('wp_ajax_thrivedesk_disconnect_plugin', [$this, 'ajax_disconnect_plugin']);

		//remove wp footer text and version
	    add_action( 'admin_init', [$this, 'remove_wp_footer_text'] );
        // menu icon style 
        add_action( 'admin_enqueue_scripts', [ $this, 'menu_icon_style' ] );
    }

    /**
     * Plugin deactivate.
     *
     * @return void
     * @since  0.0.1
     * @access public
     */
    public static function deactivate()
    {
        // Configuration and settings are preserved so deactivation is
        // reversible; destructive teardown (options + table) lives in
        // uninstall.php.
    
        // Remove all transient data
        global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name LIKE %s", '_transient_%thrivedesk%'));
        $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name LIKE %s", '_transient_timeout_%thrivedesk%'));
    
        // Flush the server cache
        wp_cache_flush();
    }

	public function remove_wp_footer_text() {
		remove_filter( 'update_footer', 'core_update_footer' );
		add_filter( 'admin_footer_text', '__return_empty_string', 11 );
	}

	public static function add_option_for_welcome_page_redirection(): void {
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

            wp_safe_redirect( admin_url( 'admin.php?page=thrivedesk' ) );
			exit;
		}
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
        add_menu_page( 
            __( 'ThriveDesk', 'thrivedesk' ),
            'ThriveDesk',
            'manage_options',
            'thrivedesk',
            array( $this, 'load_pages' ),
            THRIVEDESK_PLUGIN_ASSETS . '/' . 'images/td-icon.svg',
            100
        ); 
        add_submenu_page(
            'thrivedesk',
            __( 'API Verify', 'thrivedesk' ),
            __( 'API Verify', 'thrivedesk' ),
            'manage_options',
            'td-api',
            array( $this, 'verification_page'),
        );
    }

    public function get_page_by_title( $page_title, $output = OBJECT, $post_type = 'page' ) {
        $args  = array(
            'title'                  => $page_title,
            'post_type'              => $post_type,
            'post_status'            => get_post_status(),
            'posts_per_page'         => 1,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
            'no_found_rows'          => true,
            'orderby'                => 'post_date ID',
            'order'                  => 'ASC',
        );
        $query = new WP_Query( $args );
        $pages = $query->posts;
    
        if ( empty( $pages ) ) {
            return null;
        }
    
        return get_post( $pages[0], $output );
    }

    public function create_portal_page()
    {
        $title = "Thrivedesk Support Portal";
        $my_post = array(
            'post_type'     => 'page',
            'post_title'    => $title,
            'post_content'  => '[thrivedesk_portal]',
            'post_status'   => 'publish',
            'post_author'   => 1
        );

        if($this->get_page_by_title($title) == null){
            wp_insert_post( $my_post );
        }
    }

    /**
     * Enqueue style
     *
     * @param mixed $hook
     * @return void
     */
    public function admin_scripts($hook): void
    {
        if ('toplevel_page_thrivedesk' == $hook OR 'thrivedesk_page_td-api' == $hook) {
            $css_version = thrivedesk_get_asset_version('/css/admin.css');
            $js_version = thrivedesk_get_asset_version('/js/admin.js');
            
            wp_enqueue_style('thrivedesk-css', THRIVEDESK_PLUGIN_ASSETS . '/css/admin.css', [], $css_version);
            wp_enqueue_script('thrivedesk-js', THRIVEDESK_PLUGIN_ASSETS . '/js/admin.js', ['jquery', 'wp-i18n'], $js_version);
            wp_set_script_translations('thrivedesk-js', 'thrivedesk');

            if (current_user_can( 'manage_options' )) {
                echo '<style>.update-nag, .updated, .error, .is-dismissible { display: none; }</style>';
            }
            
            // Localize script only when it's actually loaded
            $td_helpdesk_settings = get_td_helpdesk_settings();
            $knowledgebase_slug = isset($td_helpdesk_settings['td_knowledgebase_slug']) ? $td_helpdesk_settings['td_knowledgebase_slug'] : 'help';
            $knowledgebase_url = $knowledgebase_slug ? parse_url(THRIVEDESK_KB_API_ENDPOINT)['scheme'] . '://' . $knowledgebase_slug . '.' . parse_url(THRIVEDESK_KB_API_ENDPOINT)['host'] : null;

            wp_localize_script(
                'thrivedesk-js',
                'thrivedesk',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('thrivedesk-nonce'),
                    'wp_json_url' => site_url('wp-json'),
                    'kb_url' => $knowledgebase_url,
                )
            );
        }

        if (class_exists('BWF_Contacts')) {
            $asset_file = include(THRIVEDESK_PLUGIN_ASSETS_PATH . '/js/wp-scripts/thrivedesk-autonami-tab.asset.php');

            wp_enqueue_script('thrivedesk-autonami-script', THRIVEDESK_PLUGIN_ASSETS . '/js/wp-scripts/thrivedesk-autonami-tab.js', $asset_file['dependencies'], $asset_file['version'] ?? THRIVEDESK_VERSION);
        }
    }

    public function load_pages(){
        if (current_user_can( 'manage_options' )) {
            echo '<style>.update-nag, .updated, .error, .is-dismissible { display: none; }</style>';
        }

        // Get the correct settings option where the API key is actually stored
        $td_helpdesk_settings = get_td_helpdesk_settings();
        $td_api_key = $td_helpdesk_settings['td_helpdesk_api_key'] ?? '';
        
        // Essential logging only
        if (empty($td_api_key)) {
            error_log('ThriveDesk: No API key found in settings');
        }

        $api_status = self::get_api_verification_status();

        if($td_api_key && $api_status){
            // Clear any cached options to ensure fresh data
            wp_cache_delete('td_helpdesk_settings', 'options');
            wp_cache_delete('td_helpdesk_verified', 'options');
            thrivedesk_view('setting');
        }
        elseif($td_api_key == '' || isset($_GET['token'])){
            thrivedesk_view('pages/api-verify');
        }
        else{
            thrivedesk_view('pages/welcome');
        }
    }

    public function verification_page(){
                    thrivedesk_view('pages/api-verify');
    }

    public static function set_api_verification_status($status = false): void
    {
        // set the api key to the database
        update_option('td_helpdesk_verified', $status);
    }

    public static function get_api_verification_status(): bool
    {
        // get the api verification status from the database
        return get_option('td_helpdesk_verified', false);
    }

    /**
     * Generate a high-entropy per-store api_token: 32 random bytes, hex-encoded.
     * This is the HMAC key for the inbound ?listener= contract, so it must not
     * be guessable from the connect time the way the old md5(time()) was.
     */
    private static function generate_api_token(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Handle plugin connect action
     *
     * @return void
     */
    public function ajax_connect_plugin()
    {
        if (
            ! current_user_can('manage_options')
            || ! isset($_POST['data']['plugin'])
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['data']['nonce'] ?? '')), 'thrivedesk-plugin-action')
        ) {
            wp_send_json_error(['message' => __('Unauthorized', 'thrivedesk')], 403);
        }

        $plugin = sanitize_key($_POST['data']['plugin']);

        $api_token = self::generate_api_token();

        $thrivedesk_options          = get_option('thrivedesk_options', []);
        $thrivedesk_options[$plugin] = $thrivedesk_options[$plugin] ?? [];
        $thrivedesk_options[$plugin] = [
            'api_token' => $api_token,
            'connected' => false,
        ];

        update_option('thrivedesk_options', $thrivedesk_options);

        $hash = base64_encode(wp_json_encode([
            'store_url'   => esc_url_raw(get_bloginfo('url')),
            'api_token'   => sanitize_text_field($api_token),
            'org_id'    => sanitize_text_field(get_option('td_helpdesk_system_info')['id'] ?? ''),
            'cancel_url'  => esc_url_raw(admin_url('options-general.php?page=thrivedesk&plugin=' . sanitize_key($plugin) . '&td-activated=false')),
            'success_url' => esc_url_raw(admin_url('options-general.php?page=thrivedesk&plugin=' . sanitize_key($plugin) . '&td-activated=true'))
        ]));

        echo esc_url(THRIVEDESK_APP_URL . '/apps/' . esc_attr($plugin) . '?connect=' . esc_attr($hash));

        wp_die();
    }

    /**
     * Handle plugin disconnect action
     *
     * @return void
     */
    public function ajax_disconnect_plugin(): void
    {
        if (
            ! current_user_can('manage_options')
            || ! isset($_POST['data']['plugin'])
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['data']['nonce'] ?? '')), 'thrivedesk-plugin-action')
        ) {
            wp_send_json_error(['message' => __('Unauthorized', 'thrivedesk')], 403);
        }

        $plugin = sanitize_key($_POST['data']['plugin']);

        $thrivedesk_options          = get_option('thrivedesk_options', []);
        $thrivedesk_options[$plugin] = $thrivedesk_options[$plugin] ?? [];
        $thrivedesk_options[$plugin] = [
            'api_token' => '',
            'connected' => false,
        ];

        update_option('thrivedesk_options', $thrivedesk_options);

        echo 1;

        wp_die();
    }

    /**
     * Plugin activate.
     *
     * @return void
     * @since  0.0.1
     * @access public
     */
    public static function activate(): void
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

        // Create/upgrade the ThriveDesk schema directly (not via an action) so
        // activation works under WP-CLI, where the admin-only Admin singleton
        // that hosted the migrate listener is never constructed.
        require_once(THRIVEDESK_DIR . '/database/DBMigrator.php');
        \ThriveDeskDBMigrator::migrate();
    }
  
    /**
	 * Add menu icon style.
	 *
	 * @return void
	 */
	public function menu_icon_style() {
		echo '<style>
            #toplevel_page_thrivedesk img{ max-width:20px;opacity:.9!important;} 
            #toplevel_page_thrivedesk li.wp-first-item{ display:none }
            #toplevel_page_thrivedesk .wp-submenu{display:none}
            </style>';
	}

}
