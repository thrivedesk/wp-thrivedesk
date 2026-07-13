<?php

namespace ThriveDesk\Portal;

class UserAccountPages {
	// Flag set when the WC "Support" tab toggle flips; tells us rewrite rules
	// need a refresh on the next request.
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
	 * Queue a rewrite-rules flush when the WC tab toggle changes.
	 *
	 * /my-account/td-support/ only resolves once rewrite rules are regenerated,
	 * so flipping the toggle has to schedule a flush. If the toggle stays the
	 * same we bail out, otherwise every settings save would flush for nothing.
	 *
	 * @param string[] $old_pages
	 * @param string[] $new_pages
	 */
	public function maybe_queue_rewrite_flush( array $old_pages, array $new_pages ): void {
		$old_has_wc = in_array( 'woocommerce', $old_pages, true );
		$new_has_wc = in_array( 'woocommerce', $new_pages, true );

		if ( $old_has_wc !== $new_has_wc ) {
			update_option( self::FLUSH_FLAG_OPTION, true );
		}
	}

	/**
	 * Flush rewrite rules once, when the flag is set.
	 *
	 * Pinned to init:99 so register_td_portal_endpoint_for_woocommerce_account_page()
	 * (init:10) has already run by the time we flush. That way the new rules
	 * pick up the endpoint when WC support is on and drop it when off. Without
	 * this, /my-account/td-support/ stays 404 until someone re-saves permalinks.
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