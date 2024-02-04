<?php

namespace ThriveDesk\Portal;

class UserAccountPages {
	private static $instance = null;

	public function __construct() {
		add_action( 'plugins_loaded', [ $this, 'handle_pages' ] );
	}

	public function handle_pages() {
		$td_helpdesk_selected_option    = get_td_helpdesk_options();
		$td_selected_user_account_pages = $td_helpdesk_selected_option['td_user_account_pages'] ?? [];

		$woo_plugin_installed = defined('WC_VERSION');;

		if ( ! empty( $td_selected_user_account_pages )  ) {
			if ( in_array( 'woocommerce', $td_selected_user_account_pages ) && $woo_plugin_installed ) {
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

	public function td_portal_query_vars( $vars ) {

		$vars[] = 'td-support';

		return $vars;
	}

	public function add_td_portal_tab_into_account_page( $items ) {
		$items['td-support'] = 'Support';

		return $items;
	}

	public function add_td_portal_content_into_account_page() {
		echo do_shortcode( '[thrivedesk_portal]' );
	}
}