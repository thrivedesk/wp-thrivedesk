<?php

namespace ThriveDesk\Portal;

class UserAccountPages {
	/**
	 * Option flag set when the td-support endpoint is toggled on/off, signalling
	 * that rewrite rules must be regenerated on the next request.
	 */
	const FLUSH_FLAG_OPTION = 'td_flush_rewrite_needed';

	private static $instance = null;

	public function __construct() {
		add_action( 'plugins_loaded', [ $this, 'handle_pages' ] );
		add_action( 'init', [ $this, 'maybe_flush_rewrite_rules' ], 99 );
	}

	public function handle_pages() {
		$td_helpdesk_selected_option    = get_td_helpdesk_settings();
		$td_selected_user_account_pages = (array) ($td_helpdesk_selected_option['td_user_account_pages'] ?? []);

		$woo_plugin_installed = defined('WC_VERSION');

		if ( ! empty( $td_selected_user_account_pages )  ) {
			if ( in_array( 'woocommerce', $td_selected_user_account_pages, true ) && $woo_plugin_installed ) {
				$this->woocommerce_account_page_handler();
			}
		}
	}

	public function woocommerce_account_page_handler() {
		add_action( 'init', [ $this, 'register_td_portal_endpoint_for_woocommerce_account_page' ] );
		add_filter( 'query_vars', [ $this, 'td_portal_query_vars' ] );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_td_portal_tab_into_account_page' ] );
		add_action( 'woocommerce_account_td-support_endpoint', [ $this, 'add_td_portal_content_into_account_page' ] );
	}

	public static function instance(): UserAccountPages {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof UserAccountPages ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function register_td_portal_endpoint_for_woocommerce_account_page() {
		add_rewrite_endpoint( 'td-support', EP_ROOT | EP_PAGES );
	}

	/**
	 * Queue a rewrite-rules flush when the WooCommerce "Support" tab is toggled on or off.
	 *
	 * The td-support endpoint only matches /my-account/td-support/ after rewrite rules are
	 * regenerated, so flipping membership must schedule a flush. Unchanged membership is a
	 * no-op to avoid flushing on every settings save. Called from the settings-save handler.
	 *
	 * @param string[] $old_pages Previously saved td_user_account_pages values.
	 * @param string[] $new_pages Newly saved td_user_account_pages values.
	 */
	public function maybe_queue_rewrite_flush( array $old_pages, array $new_pages ): void {
		$old_has_wc = in_array( 'woocommerce', $old_pages, true );
		$new_has_wc = in_array( 'woocommerce', $new_pages, true );

		if ( $old_has_wc !== $new_has_wc ) {
			update_option( self::FLUSH_FLAG_OPTION, true );
		}
	}

	/**
	 * Flush rewrite rules once after the td-support endpoint is toggled on or off.
	 *
	 * Runs at init priority 99, after register_td_portal_endpoint_for_woocommerce_account_page()
	 * (init, 10), so the regenerated rules include the endpoint when WooCommerce support is on
	 * and drop it when off. The flag is queued by maybe_queue_rewrite_flush(); without this,
	 * /my-account/td-support/ keeps 404ing until permalinks are manually re-saved.
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( get_option( self::FLUSH_FLAG_OPTION ) ) {
			flush_rewrite_rules();
			delete_option( self::FLUSH_FLAG_OPTION );
		}
	}

	public function td_portal_query_vars( $vars ) {

		$vars[] = 'td-support';

		return $vars;
	}

	public function add_td_portal_tab_into_account_page( $items ) {
		$items['td-support'] = __( 'Support', 'thrivedesk' );

		return $items;
	}

	public function add_td_portal_content_into_account_page() {
		echo do_shortcode( '[thrivedesk_portal]' );
	}
}