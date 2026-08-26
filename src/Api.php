<?php

namespace ThriveDesk;

use ThriveDesk\Api\ApiResponse;
use WC_Product_Query;
use WC_Order_Item_Product;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Api {
	/**
	 * Request parameters that make up the signed inbound contract, across every
	 * integration (EDD, WooCommerce, FluentCRM, Autonami, WPPostSync). The SaaS
	 * signs an HMAC over exactly the params it sends; verify_token() hashes only
	 * these, so a store plugin that injects its own query var into the request
	 * (WOOF's woof_parse_query, a cache-buster, utm_*, a multilingual lang) can't
	 * alter the signature. Keep in step with the signers in
	 * app/apps/<Integration>/Services/*Service.php.
	 *
	 * @var string[]
	 */
	private const SIGNED_PARAMS = [
		'listener',
		'plugin',
		'action',
		'email',
		'shipping_param',
		'order_id',
		'order_status',
		'item',
		'item_id',
		'quantity',
		'coupon',
		'amount',
		'reason',
		'subscription_status',
		'order_types',
		'sync_type',
		'extra',
		'query',
	];

	/**
	 * The single instance of this class
	 */
	private static $instance = null;

	private $apiResponse;
	private $plugin = null;
	private $order_id = null;
	private $order_status = null;
	private $quantity = null;
	private $item = null;
	private $coupon = null;
	private $amount = null;
	private $reason = null;
	private $item_id = null;
	private $subscription_status = null;
	private $order_types = null;

	/**
	 * Construct Api class.
	 *
	 * @since  0.0.1
	 * @access private
	 */
	private function __construct() {
		// wp_loaded, not init: the listener drives other plugins (order
		// status changes fire WooCommerce Subscriptions' scheduler, which
		// only finishes its own setup at init 10). Running inside init can
		// execute before that setup and fatal mid-request.
		add_action( 'wp_loaded', [ $this, 'api_listener' ] );

		$this->apiResponse = new ApiResponse();
	}


	/**
	 * Main Api Instance.
	 *
	 * Ensures that only one instance of Api exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @return object|Api
	 * @access public
	 * @since  0.0.1
	 */
	public static function instance(): object {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Admin ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Available plugins
	 *
	 * @return array
	 * @since 0.0.1
	 */
	private function _available_plugins(): array {
		return [
			'edd'         => 'EDD',
			'woocommerce' => 'WooCommerce',
			'fluentcrm'   => 'FluentCRM',
			'wppostsync'  => 'WPPostSync',
			'autonami'    => 'Autonami',
		];
	}

	/**
	 * Api listener
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function api_listener(): void {
		// The listener flag only decides whether the request is ours at all; it
		// is part of the signed contract too, so a forged one still has to get
		// past verify_token() below.
		$listener = sanitize_key( $_GET['listener'] ?? '' );
		if ( 'thrivedesk' !== $listener ) {
			return;
		}

		try {
			// One array for the whole request: verify_token() hashes exactly
			// this and every handler reads exactly this, so the payload that
			// was signed is always the payload that runs.
			$contract = $this->contract();

			$action = strtolower( sanitize_key( $this->contract_string( 'action' ) ) );
			$plugin = strtolower( sanitize_key( $this->contract_string( 'plugin' ) ) ) ?: 'edd';

			$this->order_id     = sanitize_key( $this->contract_string( 'order_id' ) );
			$this->order_status = sanitize_key( $this->contract_string( 'order_status' ) );
			$this->quantity     = sanitize_key( $this->contract_string( 'quantity' ) );
			$this->item         = sanitize_key( $this->contract_string( 'item' ) );
			$this->item_id      = sanitize_key( $this->contract_string( 'item_id' ) );
			$this->coupon       = sanitize_key( $this->contract_string( 'coupon' ) );
			$this->amount       = sanitize_key( $this->contract_string( 'amount' ) );
			$this->reason       = sanitize_key( $this->contract_string( 'reason' ) );
			// subscription_status is a status key with a dash (e.g. 'cancelled',
			// 'pending-cancel') and order_types a comma list ('parent,renewal'),
			// so sanitize_text_field instead of sanitize_key for both. contract()
			// already unslashed the values, so no wp_unslash() here.
			$this->subscription_status = sanitize_text_field( $this->contract_string( 'subscription_status' ) );
			$this->order_types         = sanitize_text_field( $this->contract_string( 'order_types' ) );

			// Everything before verify_token() answers the same way. The
			// activation check used to run first, so four unauthenticated
			// requests (?plugin=woocommerce, edd, fluentcrm, autonami) told an
			// anonymous caller exactly which commerce and CRM stack the store
			// runs. An unknown plugin key is folded into the same answer.
			$plugin_name       = $this->_available_plugins()[ $plugin ] ?? null;
			$plugin_class_name = null !== $plugin_name ? 'ThriveDesk\\Plugins\\' . $plugin_name : null;

			if ( null === $plugin_class_name || ! class_exists( $plugin_class_name ) ) {
				$this->apiResponse->error( 401, 'Request unauthorized' );
			}

			// Constructing the singleton has to happen first — verify_token()
			// needs get_plugin_data('api_token') — but every integration's
			// constructor is a no-op, so this introspects nothing.
			$this->plugin = $plugin_class_name::instance();

			if ( ! $this->verify_token( $contract ) ) {
				$this->apiResponse->error( 401, 'Request unauthorized' );
			}

			// Past this point the caller holds the integration's shared secret,
			// so a specific diagnosis is no longer a disclosure.
			if ( ! method_exists( $this->plugin, 'is_plugin_active' ) ) {
				$this->apiResponse->error( 500, "Method 'is_plugin_active' not exist in class '{$plugin_class_name}'" );
			}

			if ( ! $this->plugin->is_plugin_active() ) {
				$this->apiResponse->error( 500, "The plugin '{$plugin_name}' isn't installed or active." );
			}

			// Only connect/disconnect may run before the integration is
			// connected. Every data or mutation action requires a completed
			// connection, so a valid signature alone (e.g. during the connect
			// handshake, before the SaaS callback flips 'connected') can't drive
			// store changes.
			if ( 'connect' !== $action && 'disconnect' !== $action && ! $this->plugin->get_plugin_data( 'connected' ) ) {
				$this->apiResponse->error( 401, 'Request unauthorized' );
			}

			if ( isset( $action ) && 'connect' === $action ) {
				$this->connect_action_handler();
			} elseif ( isset( $action ) && 'disconnect' === $action ) {
				$this->disconnect_action_handler();
			} elseif ( isset( $action ) && 'get_fluentcrm_data' === $action ) {
				$this->fluentcrm_handler();
			} elseif ( isset( $action ) && 'handle_autonami' === $action ) {
				$this->autonami_handler();
			} elseif ( isset( $action ) && 'get_wppostsync_data' === $action ) {
				$remote_query_string = strtolower( $this->contract_string( 'query' ) );
				$this->wp_postsync_data_handler( $remote_query_string );
			} elseif ( isset( $action ) && 'get_woocommerce_product_list' === $action ) {
				$this->get_woocommerce_product_list();
			} elseif ( isset( $action ) && 'get_woocommerce_order_status' === $action ) {
				$this->get_woocommerce_order_status();
			} elseif ( isset( $action ) && 'get_woocommerce_order_status_list' === $action ) {
				$this->get_woocommerce_status_list();
			} elseif ( isset( $action ) && 'woocommerce_order_status_update' === $action ) {
				$this->woocommerce_order_status_update( $this->order_id, $this->order_status );
			} elseif ( isset( $action ) && 'woocommerce_subscription_cancel' === $action ) {
				$this->woocommerce_subscription_cancel( $this->order_id, $this->subscription_status, $this->order_types );
			} elseif ( isset( $action ) && 'woocommerce_order_quantity_update' === $action ) {
				$this->woocommerce_order_quantity_update( $this->order_id, $this->item_id, $this->quantity );
			} elseif ( isset( $action ) && 'woocommerce_order_apply_coupon' === $action ) {
				$this->woocommerce_order_apply_coupon( $this->order_id, $this->coupon );
			} elseif ( isset( $action ) && 'add_item_on_woocommerce_order' === $action ) {
				$this->wc_order_add_new_item( $this->order_id, $this->item );
			} elseif ( isset( $action ) && 'remove_item_from_woocommerce_order' === $action ) {
				$this->wc_order_remove_item( $this->order_id, $this->item );
			} else {
				$this->plugin_data_action_handler();
			}
		} catch ( \Exception $e ) {
			$this->apiResponse->error( 500, 'Can\'t not prepare data' );
		}

		wp_die();
	}

	/**
	 * handler autonami action
	 */
	public function autonami_handler() {
		$syncType                     = strtolower( sanitize_key( $this->contract_string( 'sync_type' ) ) );
		$this->plugin->customer_email = sanitize_email( $this->contract_string( 'email' ) );

		if ( $syncType ) {
			$this->plugin->sync_conversation_with_autonami( $syncType, $this->contract()['extra'] ?? [] );
		} else {
			if ( ! method_exists( $this->plugin, 'prepare_data' ) ) {
				$this->apiResponse->error( 500, "Method 'prepare_data' not exist in plugin" );
			}

			if ( ! $this->plugin->is_customer_exist() ) {
				$this->apiResponse->error( 404, "Customer not found." );
			}

			$data = $this->plugin->prepare_data();

			$this->apiResponse->success( 200, $data, 'Success' );
		}
	}

	/**
	 * get woocommerce order status
	 *
	 * @since 0.9.0
	 */
	public function get_woocommerce_order_status() {
		$email    = sanitize_email( $this->contract_string( 'email' ) );
		// Use sanitize_text_field instead of sanitize_key because custom order numbers
		// can contain characters like slashes, dashes, or spaces (e.g., "2025/001").
		$order_id = sanitize_text_field( $this->contract_string( 'order_id' ) );

		if ( ! $order_id ) {
			$this->apiResponse->error( 400, 'order_id is required.' );
		}

		if ( ! method_exists( $this->plugin, 'order_status' ) ) {
			$this->apiResponse->error( 500, "Method 'order_status' not exist in plugin" );
		}

		$this->plugin->customer_email = $email;

		$data = $this->plugin->order_status( $order_id );

		if ( empty( $data ) ) {
			$this->apiResponse->error( 404, "Order not found." );
		}

		$this->apiResponse->success( 200, $data, 'Success' );
	}

	/**
	 * @return void
	 */
	public function get_woocommerce_product_list() {

		$query = new WC_Product_Query( array(
			'status' => 'publish',
			'return' => 'ids',
		) );

		$products    = $query->get_products();
		$productList = [];

		foreach ( $products as $product_id ) {
			$product = wc_get_product( $product_id );
			$thumbnail_id = get_post_thumbnail_id( $product_id );
			$image_src_array = [];

			if( $thumbnail_id){
				$image_src_array = wp_get_attachment_image_src( $thumbnail_id );
			}

			$productInfo = array(
				"product_id"        => $product_id,
				"title"             => $product->get_name(),
				"product_permalink" => get_permalink( $product_id ),
				"image"             =>  is_array( $image_src_array ) && ! empty( $image_src_array ) ? $image_src_array[0] : '',
				"sale_price"        => get_woocommerce_currency_symbol() . $product->get_regular_price(),
				"stock"             => ( 'instock' === $product->get_stock_status() ) ? 'In Stock' : 'Out of Stock',
			);

			array_push( $productList, $productInfo );
		}

		$data = $productList;

		$this->apiResponse->success( 200, $data, 'Success' );
	}

	/**
	 * @return void
	 */
	public function get_woocommerce_status_list() {

		$statuses = wc_get_order_statuses();

		$this->apiResponse->success( 200, $statuses, 'Success' );
	}

	/**
	 * @param $order_id
	 * @param $item
	 *
	 * @return void
	 */
	public function wc_order_add_new_item( string $order_id, $item ) {
		$this->guard_order_ownership( $order_id );

		$product = wc_get_product_object( 'line_item', $item );

		$item = new WC_Order_Item_Product();
		$item->set_name( $product->name );
		$item->set_quantity( $this->quantity );
		$item->set_product_id( $product->id );
		$item->set_subtotal( $product->price ?? 0 );
		$item->set_total( $product->price * $this->quantity ?? 0 );
		
		// if(is_plugin_active('wt-woocommerce-sequential-order-numbers-pro/wt-advanced-order-number-pro.php')) 
		// {
		// 	if customer use this type of plugin, wc doesn't have the same order number as the plugin.
		// }

		$order = wc_get_order( $order_id );
		$order->add_item( $item );
		$order->calculate_totals();

		$this->apiResponse->success( 200, [], 'Success' );
	}

	/**
	 * @param $order_id
	 * @param $product_id
	 *
	 * @return void
	 */
	public function wc_order_remove_item( string $order_id, string $product_id ) {
		$this->guard_order_ownership( $order_id );

		$order = wc_get_order( $order_id );

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( $item["product_id"] == $product_id ) {
				wc_delete_order_item( $item_id );
			}
		}

		$order->calculate_totals();

		$this->apiResponse->success( 200, [], 'Success' );
	}


	/**
	 * @param $order_id
	 * @param $orderStatus
	 *
	 * @return void
	 */
	public function woocommerce_order_status_update( string $order_id, string $orderStatus ) {
		$this->guard_order_ownership( $order_id );

		if ( ! method_exists( $this->plugin, 'update_order_status' ) ) {
			$this->apiResponse->error( 500, "Method 'update_order_status' not exist in plugin" );
		}

		// The panel sends the customer-facing order number, which on stores
		// running sequential order numbering differs from the post ID. The
		// plugin resolves it and reports a miss instead of a blind success.
		if ( ! $this->plugin->update_order_status( $order_id, $orderStatus ) ) {
			$this->apiResponse->error( 404, 'Order not found.' );
		}

		$this->apiResponse->success( 200, [], 'Success' );
	}

	/**
	 * Cancel (or mark pending-cancel) any WooCommerce Subscriptions related
	 * to the given order. Delegates to the plugin class.
	 *
	 * Runs as an automatic follow-up to every panel cancel/refund, so an
	 * order with no related subscriptions (or a store without the
	 * Subscriptions extension) responds 200 with an empty id list rather
	 * than an error. Only bad input or a missing order is a failure.
	 *
	 * @param string $order_id
	 * @param string $subscription_status  'cancelled' or 'pending-cancel'.
	 * @param string $order_types          Comma list of order relationships
	 *                                     ('parent', 'renewal'). Empty means parent.
	 *
	 * @since 2.5.0
	 */
	public function woocommerce_subscription_cancel( string $order_id, string $subscription_status, string $order_types = '' ) {
		if ( ! $order_id ) {
			$this->apiResponse->error( 400, 'order_id is required.' );
		}

		$this->guard_order_ownership( $order_id );

		if ( ! method_exists( $this->plugin, 'cancel_subscriptions_for_order' ) ) {
			$this->apiResponse->error( 500, "Method 'cancel_subscriptions_for_order' not exist in plugin" );
		}

		$order_types = $order_types ? array_map( 'trim', explode( ',', $order_types ) ) : [ 'parent' ];

		try {
			$result = $this->plugin->cancel_subscriptions_for_order( $order_id, $subscription_status, $order_types );
		} catch ( \InvalidArgumentException $e ) {
			$this->apiResponse->error( 400, $e->getMessage() );
		} catch ( \RuntimeException $e ) {
			$this->apiResponse->error( 404, $e->getMessage() );
		}

		$this->apiResponse->success( 200, [ 'subscription_ids' => array_map( 'strval', $result['updated_ids'] ) ], 'Success' );
	}

	/**
	 * @param $order_id
	 * @param $product_id
	 * @param $quantity
	 *
	 * @return void
	 */
	public function woocommerce_order_quantity_update( string $order_id, string $product_id, string $quantity ) {
		$this->guard_order_ownership( $order_id );

		if ( (int) $quantity <= 0 ) {
			$this->apiResponse->error( 400, 'Quantity must be greater than zero.' );
		}

		$order = wc_get_order( $order_id );
		foreach ( $order->get_items() as $item_id => $item ) {

			if ( $item["product_id"] == (string) $product_id ) {
				wc_update_order_item_meta( $item_id, '_qty', $quantity );
				$order->calculate_totals();
			}
		}
		$this->apiResponse->success( 200, [], 'Success' );
	}

	/**
	 * @param $order_id
	 * @param $coupon
	 *
	 * @return void
	 */
	public function woocommerce_order_apply_coupon( string $order_id, string $coupon ) {
		$this->guard_order_ownership( $order_id );

		$order = wc_get_order( $order_id );

		if ( $coupon ) {
			$res = $order->apply_coupon( $coupon );
			if ( isset( $res->errors ) ) {
				$this->apiResponse->error( 404, "Coupon does not exist!." );
			} else {
				$this->apiResponse->success( 200, [], 'Success' );
			}
		}

	}

	/**
	 * data handler for FluentCRM
	 *
	 * @return void
	 * @since 0.7.0
	 */
	public function fluentcrm_handler(): void {
		$syncType                     = strtolower( sanitize_key( $this->contract_string( 'sync_type' ) ) );
		$this->plugin->customer_email = sanitize_email( $this->contract_string( 'email' ) );

		if ( $syncType ) {
			$this->plugin->sync_conversation_with_fluentcrm( $syncType, $this->contract()['extra'] ?? [] );
		} else {
			if ( ! method_exists( $this->plugin, 'prepare_fluentcrm_data' ) ) {
				$this->apiResponse->error( 500, "Method 'prepare_fluentcrm_data' not exist in plugin" );
			}

			if ( ! $this->plugin->is_customer_exist() ) {
				$this->apiResponse->error( 404, "Customer not found." );
			}
			$data = $this->plugin->prepare_fluentcrm_data();

			$this->apiResponse->success( 200, $data, 'Success' );
		}
	}

	/**
	 * data handler for wp-post-sync
	 *
	 * @param $remote_query_string
	 *
	 * @since 0.8.0
	 */
	public function wp_postsync_data_handler( $remote_query_string ): void {
		$search_data = $this->plugin->get_post_search_result( $remote_query_string );

		$this->apiResponse->success( 200, $search_data, 'Success' );
	}

	/**
	 * Handle plugin connect request
	 *
	 * @return void
	 * @since 0.0.4
	 */
	public function connect_action_handler(): void {
		$this->plugin->connect();

		$this->apiResponse->success( 200, [], 'Site connected successfully' );
	}

	/**
	 * Handle plugin disconnect request
	 *
	 * @return void
	 * @since 0.0.4
	 */
	public function disconnect_action_handler(): void {
		$this->plugin->disconnect();

		$this->apiResponse->success( 200, [], 'Site has been disconnected' );
	}

	/**
	 * Handle plugin data request
	 *
	 * @return void
	 * @since 0.0.4
	 */
	public function plugin_data_action_handler() {

		$email          = sanitize_email( $this->contract_string( 'email' ) );
		$enableShipping = isset( $this->contract()['shipping_param'] ) == 1 ? true : false;

		if ( ! method_exists( $this->plugin, 'prepare_data' ) ) {
			$this->apiResponse->error( 500, "Method 'prepare_data' not exist in plugin" );
		}

		$this->plugin->customer_email = $email;
		$this->plugin->shipping_param = $enableShipping;

		if ( ! $this->plugin->is_customer_exist() ) {
			$this->apiResponse->error( 404, "Customer not found." );
		}

		$data = $this->plugin->prepare_data();

		$this->apiResponse->success( 200, $data, 'Success' );
	}

	/**
	 * Reject an inbound WooCommerce mutation whose signed customer email does
	 * not match the target order's billing email. The SaaS signs `email` on
	 * every mutator request, so this is a second factor on top of the HMAC.
	 */
	private function guard_order_ownership( string $order_id ): void {
		if ( ! method_exists( $this->plugin, 'order_belongs_to_customer' ) ) {
			// Can't verify ownership without the WooCommerce resolver: fail closed.
			$this->apiResponse->error( 403, 'Order does not belong to this customer.' );
		}

		$email = sanitize_email( $this->contract_string( 'email' ) );
		$owns  = $this->plugin->order_belongs_to_customer( $order_id, $email );

		// null = order not found; let the mutator emit its own 404 rather than a
		// misleading ownership error. A definite false is a real mismatch.
		if ( false === $owns ) {
			$this->apiResponse->error( 403, 'Order does not belong to this customer.' );
		}
	}

	/**
	 * The signed inbound contract: exactly the params the SaaS signs, read from
	 * $_REQUEST (WordPress builds it as $_GET + $_POST in wp_magic_quotes(), so
	 * this works whether the panel sends the payload on the query string or in
	 * the body) and unslashed back to the raw values the SaaS hashed.
	 *
	 * Hash only these, never the whole $_REQUEST: a third-party plugin on the
	 * store can add its own query var to every request (this hit a customer whose
	 * WOOF plugin injected woof_parse_query), and folding that into the HMAC
	 * breaks the signature the SaaS computed over the contract alone.
	 *
	 * Every handler reads through here and verify_token() hashes this same array,
	 * so what was signed is always what runs. The dispatcher used to read $_GET
	 * while the HMAC covered $_REQUEST, which let a POSTed `action=connect` sign a
	 * query-string `action=disconnect`. This is a pure function of $_REQUEST, so
	 * the array the signature was checked against and the array the handlers read
	 * are the same array however often it is derived.
	 *
	 * @return array
	 */
	private function contract(): array {
		return wp_unslash( array_intersect_key( $_REQUEST, array_flip( self::SIGNED_PARAMS ) ) );
	}

	/**
	 * One contract param as a raw string. Anything the SaaS never sends as a
	 * scalar (an array smuggled in as `order_id[]=…`) collapses to '' instead of
	 * fataling the sanitizers, which all expect a string.
	 *
	 * @param string $key Contract param name.
	 *
	 * @return string
	 */
	private function contract_string( string $key ): string {
		$value = $this->contract()[ $key ] ?? '';

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Verify api request token
	 *
	 * @param array $contract The signed contract params, as built by contract().
	 *
	 * @return boolean
	 * @since 0.0.4
	 */
	private function verify_token( array $contract ): bool {
		$payload = $contract;

		if ( $payload ) {
			foreach ( $payload as $key => $value ) {
				if ( ! is_string( $value ) ) {
					continue;
				}
				switch ( strtolower( $value ) ) {
					case "true":
						$payload[ $key ] = true;
						break;

					case "false":
						$payload[ $key ] = false;
						break;
				}
			}
		}

		$api_token = $this->plugin->get_plugin_data( 'api_token' );

		// An empty key is forgeable: hash_hmac() with '' yields a value anyone
		// can reproduce. A disconnected or never-connected integration has no
		// token, so reject rather than authorize against an empty secret.
		if (empty($api_token)) {
			return false;
		}

		$signature = (string) ( $_SERVER['HTTP_X_TD_SIGNATURE'] ?? '' );
		if (empty($signature)) {
			return false;
		}

		// Hash the raw values. The SaaS signs what it sends, so running the
		// payload through sanitize_text_field() first hashed a value the sender
		// never signed and 401'd legitimate requests; worse, the handlers below
		// sanitize with sanitize_key()/sanitize_email() instead, so the value
		// that was hashed and the value that ran could differ ('%20123' hashes
		// as '123' but executes as '20123'). Sanitizing happens at the point of
		// use, after the signature has been checked.
		$expected = hash_hmac( 'SHA1', wp_json_encode( $payload ), $api_token );

		// Computed digest first: hash_equals()'s timing guarantee is on the
		// length of the first argument, which must be the known-good value.
		return hash_equals( $expected, $signature );
	}
}