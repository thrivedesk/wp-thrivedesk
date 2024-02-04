<?php

namespace ThriveDesk;

use ThriveDesk\Api\ApiResponse;
use WC_Product_Query;
use WC_Order;
use WC_Order_Item_Product;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Api {
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

	/**
	 * Construct Api class.
	 *
	 * @since  0.0.1
	 * @access private
	 */
	private function __construct() {
		add_action( 'init', [ $this, 'api_listener' ] );

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
		$listener = sanitize_key( $_GET['listener'] ?? '' );
		if ( ! isset( $listener ) || 'thrivedesk' !== $listener ) {
			return;
		}

		try {
			$action = strtolower( sanitize_key( $_GET['action'] ?? '' ) );
			$plugin = strtolower( sanitize_key( $_GET['plugin'] ?? 'edd' ) );

			$this->order_id     = sanitize_key( $_GET['order_id'] ?? '' );
			$this->order_status = sanitize_key( $_GET['order_status'] ?? '' );
			$this->quantity     = sanitize_key( $_GET['quantity'] ?? '' );
			$this->item         = sanitize_key( $_GET['item'] ?? '' );
			$this->item_id      = sanitize_key( $_GET['item_id'] ?? '' );
			$this->coupon       = sanitize_key( $_GET['coupon'] ?? '' );
			$this->amount       = sanitize_key( $_GET['amount'] ?? '' );
			$this->reason       = sanitize_key( $_GET['reason'] ?? '' );

			// Plugin invalid response
			if ( ! in_array( $plugin, array_keys( $this->_available_plugins() ) ) ) {
				$this->apiResponse->error( 401, 'Plugin is invalid or not available now.' );
			}

			$plugin_name       = $this->_available_plugins()[ $plugin ] ?? 'EDD';
			$plugin_class_name = 'ThriveDesk\\Plugins\\' . $plugin_name;

			if ( ! class_exists( $plugin_class_name ) ) {
				$this->apiResponse->error( 500, "Class not found for the '{$plugin_name}' plugin" );
			}

			$this->plugin = $plugin_class_name::instance();

			if ( ! method_exists( $this->plugin, 'is_plugin_active' ) ) {
				$this->apiResponse->error( 500, "Method 'prepare_data' not exist in class '{$plugin_class_name}'" );
			}

			if ( ! $this->plugin->is_plugin_active() ) {
				$this->apiResponse->error( 500, "The plugin '{$plugin_name}' isn't installed or active." );
			}

			if ( ! $this->verify_token() ) {
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
				$remote_query_string = strtolower( $_GET['query'] ?? '' );
				$this->wp_postsync_data_handler( $remote_query_string );
			} elseif ( isset( $action ) && 'get_woocommerce_product_list' === $action ) {
				$this->get_woocommerce_product_list();
			} elseif ( isset( $action ) && 'get_woocommerce_order_status' === $action ) {
				$this->get_woocommerce_order_status();
			} elseif ( isset( $action ) && 'get_woocommerce_order_status_list' === $action ) {
				$this->get_woocommerce_status_list();
			} elseif ( isset( $action ) && 'woocommerce_order_status_update' === $action ) {
				$this->woocommerce_order_status_update( $this->order_id, $this->order_status );
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
		$syncType                     = strtolower( sanitize_key( $_REQUEST['sync_type'] ?? '' ) );
		$this->plugin->customer_email = sanitize_email( $_GET['email'] ?? '' );

		if ( $syncType ) {
			$this->plugin->sync_conversation_with_autonami( $syncType, $_REQUEST['extra'] ?? [] );
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
		$email    = sanitize_email( $_REQUEST['email'] ?? '' );
		$order_id = strtolower( sanitize_key( $_REQUEST['order_id'] ?? '' ) );

		if ( ! method_exists( $this->plugin, 'order_status' ) ) {
			$this->apiResponse->error( 500, "Method 'order_status' not exist in plugin" );
		}

		$this->plugin->customer_email = $email;

		if ( ! $this->plugin->is_customer_exist() ) {
			$this->apiResponse->error( 404, "Customer not found." );
		}

		$data = $this->plugin->order_status( $order_id );

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

			$productInfo = array(
				"product_id"        => $product_id,
				"title"             => $product->get_name(),
				"product_permalink" => get_permalink( $product_id ),
				"image"             => wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ) )[0],
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
		$product = wc_get_product_object( 'line_item', $item );

		$item = new WC_Order_Item_Product();
		$item->set_name( $product->name );
		$item->set_quantity( $this->quantity );
		$item->set_product_id( $product->id );
		$item->set_subtotal( $product->price ?? 0 );
		$item->set_total( $product->price * $this->quantity ?? 0 );
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
		$order = new WC_Order( $order_id );
		$order->update_status( $orderStatus, '' );

		$this->apiResponse->success( 200, [], 'Success' );
	}

	/**
	 * @param $order_id
	 * @param $product_id
	 * @param $quantity
	 *
	 * @return void
	 */
	public function woocommerce_order_quantity_update( string $order_id, string $product_id, string $quantity ) {

		$order = wc_get_order( $order_id );
		if ( $quantity > 0 ) {
			foreach ( $order->get_items() as $item_id => $item ) {

				if ( $item["product_id"] == (string) $product_id ) {
					wc_update_order_item_meta( $item_id, '_qty', $quantity );
					$order->calculate_totals();
				}
			}
			$this->apiResponse->success( 200, [], 'Success' );
		}
	}

	/**
	 * @param $order_id
	 * @param $coupon
	 *
	 * @return void
	 */
	public function woocommerce_order_apply_coupon( string $order_id, string $coupon ) {
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
		$syncType                     = strtolower( sanitize_key( $_REQUEST['sync_type'] ?? '' ) );
		$this->plugin->customer_email = sanitize_email( $_REQUEST['email'] ?? '' );

		if ( $syncType ) {
			$this->plugin->sync_conversation_with_fluentcrm( $syncType, $_REQUEST['extra'] ?? [] );
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

		$email          = sanitize_email( $_REQUEST['email'] ?? '' );
		$enableShipping = isset($_REQUEST['shipping_param']) == 1 ? true : false;

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
	 * Verify api request token
	 *
	 * @return boolean
	 * @since 0.0.4
	 */
	private function verify_token(): bool {
		$payload = $_REQUEST;

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

		$signature = $_SERVER['HTTP_X_TD_SIGNATURE'];

		return hash_equals( $signature, hash_hmac( 'SHA1', json_encode( $payload ), $api_token ) );
	}
}