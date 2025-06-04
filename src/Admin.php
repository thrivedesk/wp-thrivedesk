<?php

/**
 * Admin functionality for ThriveDesk plugin.
 *
 * @package ThriveDesk
 * @since 0.0.1
 */

namespace ThriveDesk;

use WP_Query;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class for ThriveDesk.
 *
 * @since 0.0.1
 */
final class Admin {

	/**
	 * The single instance of this class.
	 *
	 * @var Admin|null
	 * @since 0.0.1
	 */
	private static $instance = null;

	/**
	 * Construct Admin class.
	 *
	 * @since  0.0.1
	 * @access private
	 */
	private function __construct() {
		// Allow to redirect to the getting started page.
		register_activation_hook( THRIVEDESK_FILE, array( $this, 'add_option_for_welcome_page_redirection' ) );

		// Define the hook when this plugin is inactivated.
		register_deactivation_hook( THRIVEDESK_FILE, array( $this, 'deactivate' ) );

		add_action( 'thrivedesk_db_migrate', array( $this, 'db_migrate' ) );

		add_action( 'admin_menu', array( $this, 'admin_menu' ), 10 );

		add_action( 'activated_plugin', array( $this, 'create_portal_page' ), 10 );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		add_action( 'admin_init', array( $this, 'redirect_to_getting_started_page' ) );

		register_activation_hook( THRIVEDESK_FILE, array( $this, 'activate' ) );

		add_action( 'wp_ajax_thrivedesk_connect_plugin', array( $this, 'ajax_connect_plugin' ) );

		add_action( 'wp_ajax_thrivedesk_disconnect_plugin', array( $this, 'ajax_disconnect_plugin' ) );

		// Remove wp footer text and version.
		add_action( 'admin_init', array( $this, 'remove_wp_footer_text' ) );

		// Menu icon style.
		add_action( 'admin_enqueue_scripts', array( $this, 'menu_icon_style' ) );
	}

	/**
	 * Plugin deactivate.
	 *
	 * @return void
	 * @since  0.0.1
	 * @access public
	 */
	public function deactivate() {
		// Clear any plugin-related options.
		delete_option( 'td_db_version' );
		delete_option( 'thrivedesk_options' );
		delete_option( 'td_helpdesk_system_info' );
		delete_option( 'td_helpdesk_settings' );
		delete_option( 'thrivedesk_installed' );
		delete_option( 'thrivedesk_version' );

		// Remove all transient data.
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_%thrivedesk%' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_%thrivedesk%' ) );

		// Flush the server cache.
		wp_cache_flush();
	}

	/**
	 * Remove WordPress footer text.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function remove_wp_footer_text() {
		remove_filter( 'update_footer', 'core_update_footer' );
		add_filter( 'admin_footer_text', '__return_empty_string', 11 );
	}

	/**
	 * Add option for welcome page redirection.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function add_option_for_welcome_page_redirection(): void {
		add_option( 'wp_thrivedesk_activation_redirect', true );
	}

	/**
	 * After successful activation, redirect to the welcome page.
	 * must not redirect if multi activation.
	 *
	 * @return void
	 */
	public function redirect_to_getting_started_page(): void {

		if ( isset( $_GET['activate-multi'] ) || is_network_admin() ) {
			return;
		}

		if ( get_option( 'wp_thrivedesk_activation_redirect', false ) ) {
			delete_option( 'wp_thrivedesk_activation_redirect' );

			wp_safe_redirect( admin_url( 'admin.php?page=thrivedesk' ) );
			exit;
		}
	}

	/**
	 * Run database migration.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function db_migrate() {
		require_once THRIVEDESK_DIR . '/database/DBMigrator.php';
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
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Admin ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Admin sub menu page.
	 *
	 * @return void
	 * @since  0.0.1
	 * @access public
	 */
	public function admin_menu(): void {
		add_menu_page(
			__( 'ThriveDesk', 'thrivedesk' ),
			'ThriveDesk',
			'manage_options',
			'thrivedesk',
			array( $this, 'load_pages' ),
			esc_url( THRIVEDESK_PLUGIN_ASSETS . '/' . 'images/td-icon.svg' ),
			100
		);
		add_submenu_page(
			'thrivedesk',
			'API Verify',
			'API Verify',
			'manage_options',
			'td-api',
			array( $this, 'verification_page' ),
		);
	}

	/**
	 * Get page by title.
	 *
	 * @param string $page_title The page title.
	 * @param string $output     The output format.
	 * @param string $post_type  The post type.
	 * @return WP_Post|null
	 * @since 0.0.1
	 */
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

	/**
	 * Create portal page.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function create_portal_page() {
		$title   = 'Thrivedesk Support Portal';
		$my_post = array(
			'post_type'    => 'page',
			'post_title'   => $title,
			'post_content' => '[thrivedesk_portal]',
			'post_status'  => 'publish',
			'post_author'  => 1,
		);

		if ( null === $this->get_page_by_title( $title ) ) {
			wp_insert_post( $my_post );
		}
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 * @since 0.0.1
	 */
	public function admin_scripts( $hook ): void {
		if ( 'toplevel_page_thrivedesk' === $hook || 'thrivedesk_page_td-api' === $hook ) {
			wp_enqueue_script(
				'thrivedesk-admin-script',
				THRIVEDESK_PLUGIN_ASSETS . '/js/admin.js',
				array( 'jquery' ),
				THRIVEDESK_VERSION,
				true
			);

			$parsed_home_url = wp_parse_url( home_url() );
			$parsed_site_url = wp_parse_url( site_url() );

			$localized_data = array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'thrivedesk_admin_nonce' ),
				'home_url'  => $parsed_home_url['host'],
				'site_url'  => $parsed_site_url['host'],
				'admin_url' => admin_url(),
			);

			wp_localize_script( 'thrivedesk-admin-script', 'thrivedesk_admin', $localized_data );

			wp_enqueue_style(
				'thrivedesk-admin-style',
				THRIVEDESK_PLUGIN_ASSETS . '/css/admin.css',
				array(),
				THRIVEDESK_VERSION,
				false
			);
		}
	}

	/**
	 * Load admin pages.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function load_pages() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo thrivedesk_view( 'admin/layout' );
		if ( 'thrivedesk' === sanitize_key( $_GET['page'] ?? '' ) ) {
			// Render the admin page.
			$api_token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
			error_log( 'Api token from request: ' . $api_token );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo thrivedesk_view( 'pages/welcome' );
	}

	/**
	 * Verification page.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function verification_page() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo thrivedesk_view( 'pages/api-verify' );
	}

	/**
	 * Set API verification status.
	 *
	 * @param bool $status The verification status.
	 * @return void
	 * @since 0.0.1
	 */
	public static function set_api_verification_status( $status = false ): void {
		// Update API verification status.
		update_option( 'td_api_verified', $status );
	}

	/**
	 * Get API verification status.
	 *
	 * @return bool
	 * @since 0.0.1
	 */
	public static function get_api_verification_status(): bool {
		// Get API verification status.
		return get_option( 'td_api_verified', false );
	}

	/**
	 * AJAX handler for plugin connection.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function ajax_connect_plugin() {
		// Verify nonce for security.
		if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['data']['nonce'] ) ), 'thrivedesk_admin_nonce' ) ) {
			wp_die( 'Security check failed.' );
		}

		// Log the request data for debugging.
		error_log( 'AJAX Connect Plugin Request: ' . wp_json_encode( sanitize_textarea_field( wp_unslash( $_POST['data'] ) ) ) );

		// Prepare data for API verification.
		if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['data']['nonce'] ) ), 'thrivedesk_admin_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		$response_data = array(
			'status'  => 'success',
			'message' => 'Plugin connected successfully',
		);

		$encoded_data = base64_encode( wp_json_encode( $response_data ) );

		$redirect_url = add_query_arg(
			array(
				'page'   => 'td-api',
				'data'   => $encoded_data,
				'source' => 'connect',
			),
			admin_url( 'admin.php' )
		);

		wp_send_json_success(
			array(
				'redirect_url' => esc_url( THRIVEDESK_APP_URL . '/integrations/wordpress/verify' ),
				'message'      => 'Redirecting to verification...',
			)
		);
	}

	/**
	 * AJAX handler for plugin disconnection.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function ajax_disconnect_plugin(): void {
		// Verify nonce for security.
		if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['data']['nonce'] ) ), 'thrivedesk_admin_nonce' ) ) {
			wp_die( 'Security check failed.' );
		}

		// Clear API verification status.
		self::set_api_verification_status( false );

		// Clear any stored API data.
		delete_option( 'td_helpdesk_api_key' );
		delete_option( 'td_helpdesk_settings' );

		wp_send_json_success(
			array(
				'message' => 'Plugin disconnected successfully',
			)
		);
	}

	/**
	 * Plugin activation.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function activate(): void {
		// Run database migration.
		do_action( 'thrivedesk_db_migrate' );

		// Set plugin version.
		update_option( 'thrivedesk_version', THRIVEDESK_VERSION );

		// Set installed flag.
		update_option( 'thrivedesk_installed', true );

		// Create portal page.
		$this->create_portal_page();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Add menu icon styles.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function menu_icon_style() {
		$current_screen = get_current_screen();

		// Only add styles on ThriveDesk admin pages.
		if ( ! $current_screen || false === strpos( $current_screen->id, 'thrivedesk' ) ) {
			return;
		}

		// Add styles for menu icon.
		if ( 'toplevel_page_thrivedesk' === $current_screen->id ) {
			// Custom styles specific to this admin page.
		}
	}
}
