<?php

namespace ThriveDesk\Plugins;

use AfterShip_Actions;
use ThriveDesk\Plugin;
use WC_Order_Query;
use WC_Subscriptions_Product;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WooCommerce extends Plugin {
	/**
	 * The single instance of this class
	 */
	private static $instance = null;

	/**
	 * To store customers order details.
	 */
	public $orders = [];

	/**
	 * To store tracking details.
	 */
	public $tracking = [];

	/**
	 * To track the get_orders method is already called or not.
	 */
	private $isCalled = false;

	/**
	 * Construct WooCommerce class.
	 *
	 * @since 0.0.1
	 * @access private
	 */
	private function __construct() {
		//
	}

	/**
	 * Main WooCommerce Instance.
	 *
	 * Ensures that only one instance of WooCommerce exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @return object|WooCommerce
	 * @access public
	 * @since 0.0.1
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof WooCommerce ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Check if plugin active or not
	 *
	 * @return boolean
	 */
	public static function is_plugin_active(): bool {
		if ( ! function_exists( 'WC' ) || ! class_exists( 'WooCommerce', false ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if a contact, guest or customer
	 *
	 * @return boolean
	 * @throws \Exception
	 */
	public function is_guest() {
		if ( empty( $this->orders ) ) {
			$this->orders = $this->get_orders();
		}
		if ( ! empty( $this->orders ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if customer exist or not
	 *
	 * @return boolean
	 * @throws \Exception
	 */
	public function is_customer_exist(): bool {
		if ( ! $this->customer_email ) {
			return false;
		}

		if ( ! $this->customer ) {
			$user_id        = get_user_by( 'email', $this->customer_email )->ID ?? 0;
			$this->customer = new \WC_Customer( $user_id );
		}

		if ( ! $this->customer->get_id() && ! $this->is_guest() ) {
			return false;
		}

		return true;
	}

	/**
	 * The accepted payment statuses of this plugin
	 *
	 * @return array
	 */
	public function accepted_statuses(): array {
		return [ 'Completed' ];
	}

	/**
	 * Get the customer data
	 *
	 * @return array
	 */
	public function get_customer(): array {
		if ( ! $this->customer_email ) {
			return [];
		}

		if ( ! $this->customer ) {
			$user_id        = get_user_by( 'email', $this->customer_email )->ID ?? 0;
			$this->customer = new \WC_Customer( $user_id );
		}

		if ( ! $this->customer->get_id() ) {
			return [];
		}

		return [
			'name'          => $this->customer->get_display_name() ?? '',
			'registered_at' => date( 'd M Y', strtotime( $this->customer->get_date_created() ) ) ?? '',
		];
	}

	/**
	 * Get the formatted amount
	 *
	 * @param  float  $amount
	 *
	 * @return string
	 */
	public function get_formated_amount( float $amount ): string {
		return get_woocommerce_currency_symbol() . $amount;
	}

    public function get_tracking_info($order_id){
        if ( in_array( 'aftership-woocommerce-tracking/aftership-woocommerce-tracking.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
            $afterShip = new AfterShip_Actions();
            $data = $afterShip->get_tracking_items($order_id);
            $aftership_tracking_link = $afterShip->generate_tracking_page_link($data[0]) ? $afterShip->generate_tracking_page_link($data[0]) : '#' ;

            if($data){
                $this->tracking = array_merge($this->tracking, array('aftership' => ['data' => $data, 'url' => $aftership_tracking_link]));
            }
        }

        return $this->tracking;
    }

	/**
	 * Get the customer orders
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function get_orders(): array {
		if ( empty( $this->orders ) && ! $this->isCalled ) {
			$query = new WC_Order_Query();
			$query->set( 'customer', $this->customer_email );
			$customer_orders = $query->get_orders();
			$this->isCalled  = true;

			foreach ( $customer_orders as $order ) {
				array_push( $this->orders, [
					'order_id'        => $order->get_order_number(),
					'amount'          => (float) $order->get_total(),
					'amount_formated' => $this->get_formated_amount( $order->get_total() ),
					'date'            => date( 'd M Y', strtotime( $order->get_date_created() ) ),
					'order_status'    => ucfirst( $order->get_status() ),
					'shipping'        => $this->shipping_param ? $this->get_shipping_details( $order ) : [],
					'payment_method'  => $order->get_payment_method_title() ?? '',
					'shipping_method' => $order->get_shipping_method() ?? '',
					'downloads'       => $this->get_order_items( $order ),
					'order_url'       => method_exists( $order,
						'get_edit_order_url' ) ? $order->get_edit_order_url() : '#',
					'coupon'          => $order->get_coupon_codes() ?? null,
					'tracking_info'   => $this->get_tracking_info( $order->get_id() ),
				] );
                $this->tracking = [];
			}
		}

		return $this->orders;
	}

	/**
	 * Resolve an order by its customer-facing order number, with support for
	 * custom order numbering plugins (WebToffee, SkyVerge Sequential Order
	 * Numbers, Tyche Custom Order Numbers, etc).
	 *
	 * Lookup strategy:
	 *   1. Numeric input is tried directly as a post ID.
	 *   2. `woocommerce_order_id_from_number` filter (used by Sequential
	 *      Order Numbers Pro and a few others) lets the active plugin
	 *      resolve the number in one call.
	 *   3. Meta query against the common `_order_number` post meta key
	 *      (WebToffee, Tyche, SkyVerge free, default WC since 7.x).
	 *
	 * Public because Api::guard_order_ownership() resolves the order once and
	 * hands it to the mutator, instead of every mutator re-resolving it and
	 * dereferencing `false` when it is missing.
	 *
	 * @param string|int $order_id_or_number
	 *
	 * @return \WC_Order|null
	 */
	public function get_order_by_number_or_id( $order_id_or_number ) {
		if ( ! $order_id_or_number ) {
			return null;
		}

		// 1) Fast path: pure numeric input → direct post ID lookup.
		if ( ctype_digit( (string) $order_id_or_number ) ) {
			$order = wc_get_order( (int) $order_id_or_number );
			if ( $order ) {
				return $order;
			}
		}

		// 2) Plugin filter — Sequential Order Numbers Pro and others.
		$resolved = (int) apply_filters( 'woocommerce_order_id_from_number', 0, $order_id_or_number );
		if ( $resolved ) {
			$order = wc_get_order( $resolved );
			if ( $order ) {
				return $order;
			}
		}

		// 3) Meta lookup — handles WebToffee, Tyche, SkyVerge free, default WC.
		// Direct SQL against both the legacy postmeta table AND the HPOS
		// orders-meta table. On HPOS-enabled sites without the compat layer,
		// _order_number lives only in wp_wc_orders_meta, so we MUST check
		// both — otherwise the lookup silently returns nothing.
		global $wpdb;
		$order_types = wc_get_order_types( 'view-orders' );
		$order_types = is_array( $order_types ) && $order_types ? array_values( $order_types ) : [ 'shop_order' ];

		// Placeholders, not an interpolated list. wc_get_order_types() is
		// filterable, so the values are not ours to trust, and building them
		// into prepare()'s format string means prepare() never sees them as
		// data — a value containing '%' would also corrupt the placeholders.
		$types_sql = implode( ',', array_fill( 0, count( $order_types ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$post_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				 WHERE pm.meta_key = '_order_number'
				   AND pm.meta_value = %s
				   AND p.post_type IN ({$types_sql})
				 LIMIT 1",
				array_merge( [ (string) $order_id_or_number ], $order_types )
			)
		);

		// HPOS fallback: check the dedicated orders meta table. Only do this
		// if the table exists — older WC installs don't have it.
		if ( ! $post_id ) {
			$hpos_table = $wpdb->prefix . 'wc_orders_meta';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table_exists = $wpdb->get_var(
				$wpdb->prepare( "SHOW TABLES LIKE %s", $hpos_table )
			);
			if ( $table_exists ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$post_id = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT order_id FROM {$hpos_table}
						 WHERE meta_key = '_order_number'
						   AND meta_value = %s
						 LIMIT 1",
						(string) $order_id_or_number
					)
				);
			}
		}

		if ( $post_id ) {
			$order = wc_get_order( $post_id );
			if ( $order ) {
				return $order;
			}
		}

		return null;
	}

	/**
	 * get woocommerce order status
	 *
	 * @param $order_id
	 *
	 * @return array
	 *
	 * @throws \Exception
	 * @since 0.8.4
	 */
	public function order_status( $order_id ): array {
		$order = $this->get_order_by_number_or_id( $order_id );

		if ( ! $order ) {
			return [];
		}

		// An order with no billing email is not "owned by nobody": a request that
		// supplies no email must not match it. Mirrors the guard in
		// order_belongs_to_customer().
		$email = (string) $this->customer_email;

		if ( '' === $email || strtolower( $order->get_billing_email() ) !== strtolower( $email ) ) {
			return [];
		}

		return [
			'order_id'         => $order->get_order_number(),
			'amount'           => $order->get_total(),
			'amount_formatted' => $this->get_formated_amount( $order->get_total() ),
			'date'             => date( 'd M Y', strtotime( $order->get_date_created() ) ),
			'order_status'     => ucfirst( $order->get_status() ),
			'shipping'         => $this->get_shipping_details( $order ),
			'downloads'        => $this->get_order_items( $order ),
		];
	}

	/**
	 * Whether a status is one this store actually offers.
	 *
	 * wc_get_order_statuses() is filterable, so stores that register their own
	 * statuses keep working; everything else does not. WooCommerce itself is
	 * permissive here — WC_Order::update_status() silently coerces an unknown
	 * status to 'pending' and explicitly allows 'trash' — so an unvalidated
	 * status string is a way to reset or bin an order.
	 *
	 * @param string $status Order status, with or without the 'wc-' prefix.
	 *
	 * @return bool
	 *
	 * @since 2.6.0
	 */
	public function is_valid_order_status( string $status ): bool {
		$status = strtolower( trim( $status ) );

		if ( '' === $status ) {
			return false;
		}

		if ( 0 !== strpos( $status, 'wc-' ) ) {
			$status = 'wc-' . $status;
		}

		return in_array( $status, array_keys( wc_get_order_statuses() ), true );
	}

	/**
	 * Update an order's status. Resolves the panel's customer-facing order
	 * number to the real order, so stores running sequential order numbering
	 * update the order the agent is actually looking at.
	 *
	 * @param string $order_id    Customer-facing order number or post ID.
	 * @param string $new_status  WooCommerce order status (wc- prefix allowed).
	 *
	 * @return bool false when no order matches.
	 *
	 * @throws \InvalidArgumentException When the status is not one this store offers.
	 *
	 * @since 2.5.0
	 */
	public function update_order_status( string $order_id, string $new_status ): bool {
		if ( ! $this->is_valid_order_status( $new_status ) ) {
			throw new \InvalidArgumentException( 'Invalid order_status.' );
		}

		$order = $this->get_order_by_number_or_id( $order_id );
		if ( ! $order ) {
			return false;
		}

		$order->update_status( $new_status, '' );

		return true;
	}

	/**
	 * Whether the order (by customer-facing number or post ID) belongs to the
	 * given customer, matched on billing email. Gates the inbound mutators,
	 * mirroring the ownership check in order_status().
	 *
	 * @since 2.6.0
	 */
	public function order_belongs_to_customer( string $order_id, string $email ): ?bool {
		$order = $this->get_order_by_number_or_id( $order_id );
		if ( ! $order ) {
			return null;
		}

		return '' !== $email
			&& strtolower( $order->get_billing_email() ) === strtolower( $email );
	}

	/**
	 * Cancel (or mark pending-cancel) subscriptions related to an order
	 * after an agent cancels or fully refunds it from the panel.
	 *
	 * No-op on stores that don't use subscriptions, since this fires after
	 * every panel cancel/refund.
	 *
	 * @param string   $order_id             Customer-facing order number or post ID.
	 * @param string   $subscription_status  'cancelled' or 'pending-cancel'.
	 * @param string[] $order_types          'parent' and/or 'renewal'. Pass
	 *                                       both on full refund so renewing
	 *                                       orders kill their own billing.
	 */
	public function cancel_subscriptions_for_order( string $order_id, string $subscription_status, array $order_types = [ 'parent' ] ): array {
		if ( ! in_array( $subscription_status, [ 'cancelled', 'pending-cancel' ], true ) ) {
			throw new \InvalidArgumentException( "Invalid subscription_status. Use 'cancelled' or 'pending-cancel'." );
		}

		$order_types = array_values( array_intersect( $order_types, [ 'parent', 'renewal' ] ) );
		if ( empty( $order_types ) ) {
			throw new \InvalidArgumentException( "Invalid order_types. Use 'parent' and/or 'renewal'." );
		}

		$has_wcs            = function_exists( 'wcs_get_subscriptions_for_order' );
		$has_wpsubscription = $this->is_wpsubscription_usable();

		// No subscription plugin means nothing can need cancelling.
		if ( ! $has_wcs && ! $has_wpsubscription ) {
			return [ 'found' => false, 'updated_ids' => [] ];
		}

		// The panel sends the customer-facing order number, which differs
		// from the post ID on stores running sequential order numbering.
		$order = $this->get_order_by_number_or_id( $order_id );
		if ( ! $order ) {
			throw new \RuntimeException( 'Order not found.' );
		}

		$found       = false;
		$updated_ids = [];

		if ( $has_wcs ) {
			$result      = $this->cancel_wcs_subscriptions( $order, $subscription_status, $order_types );
			$found       = $found || $result['found'];
			$updated_ids = array_merge( $updated_ids, $result['updated_ids'] );
		}

		if ( $has_wpsubscription ) {
			$result      = $this->cancel_wpsubscription_subscriptions( $order, $subscription_status, $order_types );
			$found       = $found || $result['found'];
			$updated_ids = array_merge( $updated_ids, $result['updated_ids'] );
		}

		return [
			'found'       => $found,
			'updated_ids' => $updated_ids,
		];
	}

	/**
	 * WooCommerce Subscriptions branch.
	 *
	 * @param \WC_Order $order
	 * @param string    $subscription_status 'cancelled' or 'pending-cancel'.
	 * @param string[]  $order_types
	 */
	private function cancel_wcs_subscriptions( $order, string $subscription_status, array $order_types ): array {
		$subscriptions = wcs_get_subscriptions_for_order( $order, [ 'order_type' => $order_types ] );
		if ( empty( $subscriptions ) ) {
			return [ 'found' => false, 'updated_ids' => [] ];
		}

		// Skip subs already at target status, plus ones WCS won't let us
		// transition (expired, etc.) — keeps retries idempotent.
		$updated_ids = [];
		foreach ( $subscriptions as $subscription ) {
			if ( $subscription->get_status() === $subscription_status ) {
				continue;
			}
			if ( ! $subscription->can_be_updated_to( $subscription_status ) ) {
				continue;
			}
			$subscription->update_status( $subscription_status );
			$updated_ids[] = (int) $subscription->get_id();
		}

		return [
			'found'       => true,
			'updated_ids' => $updated_ids,
		];
	}

	/**
	 * Whether WPSubscription is active with the exact internals this
	 * integration touches. These are the plugin's internal classes, not a
	 * published API, so every symbol is checked: if a future WPSubscription
	 * release renames or moves them (e.g. a SpringDevs to ConversWP
	 * namespace rebrand), the branch turns itself off instead of fataling.
	 * The durable fix is a stable public API in WPSubscription itself.
	 */
	private function is_wpsubscription_usable(): bool {
		// WPSubscription's current code needs PHP 8.0 (union types) despite
		// its declared 7.4 minimum. class_exists() autoloads the class, so
		// probing it on older PHP would trigger the plugin's own parse
		// error. Bail before touching its autoloader.
		if ( PHP_VERSION_ID < 80000 ) {
			return false;
		}

		return class_exists( '\SpringDevs\Subscription\Illuminate\Helper' )
			&& class_exists( '\SpringDevs\Subscription\Illuminate\Action' )
			&& method_exists( '\SpringDevs\Subscription\Illuminate\Helper', 'get_subscriptions_from_order' )
			&& method_exists( '\SpringDevs\Subscription\Illuminate\Action', 'status' );
	}

	/**
	 * WPSubscription branch.
	 *
	 * WPSubscription already cancels related subs via its own status-changed
	 * hook, so this is mostly a fallback for orders whose status didn't
	 * transition (already cancelled/refunded when the agent acted).
	 *
	 * @param \WC_Order $order
	 * @param string    $subscription_status 'cancelled' or 'pending-cancel'.
	 * @param string[]  $order_types
	 */
	private function cancel_wpsubscription_subscriptions( $order, string $subscription_status, array $order_types ): array {
		$type_map      = [ 'parent' => 'new', 'renewal' => 'renew' ];
		$wanted_types  = array_values( array_intersect_key( $type_map, array_flip( $order_types ) ) );
		$target_status = 'pending-cancel' === $subscription_status ? 'pe_cancelled' : 'cancelled';

		$histories = \SpringDevs\Subscription\Illuminate\Helper::get_subscriptions_from_order( $order->get_id() );

		$found       = false;
		$updated_ids = [];
		foreach ( (array) $histories as $history ) {
			if ( ! in_array( $history->type, $wanted_types, true ) ) {
				continue;
			}

			$found  = true;
			$status = get_post_status( (int) $history->subscription_id );

			// Terminal states stay put; only live subs flip.
			if ( $status === $target_status || in_array( $status, [ 'cancelled', 'expired' ], true ) ) {
				continue;
			}

			\SpringDevs\Subscription\Illuminate\Action::status( $target_status, (int) $history->subscription_id );
			$updated_ids[] = (int) $history->subscription_id;
		}

		return [
			'found'       => $found,
			'updated_ids' => $updated_ids,
		];
	}


	/**
	 * get order shipping details
	 *
	 * @param $order
	 *
	 * @return array
	 */
	public function get_shipping_details( $order ): array {
		$states = WC()->countries->get_states( $order->get_shipping_country() );
		$state  = ! empty( $states[ $order->get_shipping_state() ] ) ? $states[ $order->get_shipping_state() ] : '';

		$shipping_details = [];

		array_push( $shipping_details, [
			'street'                    => $order->get_shipping_address_1() . ' ' . ( $order->get_shipping_address_2() ?? '' ),
			'city'                      => $order->get_shipping_city() ?? '',
			'zip'                       => $order->get_shipping_postcode() ?? '',
			'state'                     => $state,
			'country'                   => WC()->countries->countries[ $order->get_shipping_country() ] ?? '',
			'shipping_address_overview' => $order->get_formatted_shipping_address() ?? '',
		] );

		return $shipping_details;
	}

	/**
	 * check if site url starts with http:// or https://
	 *
	 * @param $site_url
	 *
	 * @return bool
	 */
	public function check_site_url( $site_url ): bool {
		return substr( $site_url, 0, 7 ) === "http://" || substr( $site_url, 0, 8 ) === "https://";
	}

	/**
	 * get order items license details
	 *
	 * @param $order
	 *
	 * @return array
	 */
	public function get_order_items( $order ): array {
		$items = $order->get_items();

		$download_item     = [];
		$license_info      = [];
		$subscription_info = [];

		if ( method_exists( 'WOO_SL_functions', 'get_order_licence_details' ) ) {

			$orderLicenseDetails = \WOO_SL_functions::get_order_licence_details( $order->get_id() );

			foreach ( $orderLicenseDetails as $orderLicenses ) {
				foreach ( $orderLicenses as $orderLicense ) {

					$license = \WOO_SL_functions::get_order_product_generated_keys(
						$orderLicense->order_id,
						$orderLicense->order_item_id,
						$orderLicense->group_id
					)[0];

					$key_instances = \WOO_SL_functions::get_license_key_instances(
						$license->licence,
						$license->order_id,
						$license->order_item_id
					);

					$sites = [];

					$expire_date = intval( \WOO_SL_functions::get_order_item_meta( $orderLicense->order_item_id,
						'_woo_sl_licensing_expire_at' ) ?? '' );
					$expire_date = $expire_date == 0 ? '' : date( "d M Y", $expire_date );

					$woo_site_url = '';

					foreach ( $key_instances as $key_instance ) {
						if ( $key_instance->active_domain ) {
							$this->check_site_url( $key_instance->active_domain ) ?
								$woo_site_url = $key_instance->active_domain :
								$woo_site_url = "http://" . $key_instance->active_domain;
							array_push( $sites, $woo_site_url );
						}
					}

					$license_info[ $license->order_item_id ] = [
						'key'              => $license->licence ?? '',
						'activation_limit' => $orderLicense->license_data["max_instances_per_key"],
						'sites'            => $sites,
						'date_created'     => $license->created ?? '',
						'expiration'       => $expire_date,
						'is_lifetime'      => $orderLicense->license_data['product_use_expire'] == 'no',
						'status'           => \WOO_SL_functions::get_licence_key_status( $license->id ) ?? '',
					];
				}
			}
		}

		foreach ( $items as $item ) {
			$productInfo = [];			
			$product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();  
			$product = wc_get_product($product_id);

			if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) {
				$subscription_info = [
					"is_subscription"       => true,
					"period"                => WC_Subscriptions_Product::get_period( $product ),
					"trial_length"          => WC_Subscriptions_Product::get_trial_length( $product ),
					"trial_period"          => WC_Subscriptions_Product::get_trial_period( $product ),
					"trial_expiration_date" => WC_Subscriptions_Product::get_trial_expiration_date( $product ),
					"sign_up_fee"           => WC_Subscriptions_Product::get_sign_up_fee( $product ),
					"expiration_date"       => WC_Subscriptions_Product::get_expiration_date( $product ),
				];
			}

			// WP Subscription - Subscription data injection.
			if ( class_exists( 'SpringDevs\Subscription\Illuminate\Helper' ) ) {
				$order_item_id     = $item->get_id();
				$subscription      = \SpringDevs\Subscription\Illuminate\Helper::get_subscription_from_order_item_id( $order_item_id );
				$subscription_id   = $subscription ? $subscription->subscription_id : 0;
				$subscription_data = \SpringDevs\Subscription\Illuminate\Helper::get_subscription_data( $subscription_id );

				if ( $subscription_data ) {
					$subscription_info = [
						'is_subscription' => true,
						'interval'        => $subscription_data['schedule']['timing_per'] ?? '',
						'period'          => $subscription_data['schedule']['timing_option'] ?? '',
						'sign_up_fee'     => $subscription_data['signup_fee'] ?? '',
						'start_date'      => $subscription_data['start_date'] ?? '',
						'expiration_date' => $subscription_data['next_date'] ?? '',
					];

					if ( ! empty( $subscription_data['trial'] ) ) {
						$subscription_info['trial_length'] = $subscription_data['trial']['timing_per'] ?? '';
						$subscription_info['trial_period'] = $subscription_data['trial']['timing_option'] ?? '';
					}
				}
			}
			
			if($product){
				// wp_get_attachment_image_src() returns false when the product has no
				// featured image, so guard before indexing to avoid a warning.
				$thumbnail = wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ) );

				$productInfo = array(
					"product_id"        => $product_id,
					"title"             => $product->get_name(),
					"product_permalink" => get_permalink($product_id),
					"quantity"          => $item["quantity"],
					"total_tax"         => $this->get_formated_amount( (float) $item["total_tax"] ),
					"image"             => is_array( $thumbnail ) ? $thumbnail[0] : null,
					"type"              => $product->get_type(),
					"status"            => $product->get_status(),
					"sku"               => $product->get_sku(),
					"price"             => $this->get_formated_amount( (float) $item["subtotal"] ),
					"regular_price"     => $this->get_formated_amount( (float) $product->get_regular_price() ),
					"sale_price"        => $this->get_formated_amount( (float) $product->get_sale_price() ),
					"tax_status"        => $product->get_tax_status(),
					"stock"             => $product->get_stock_quantity(),
					"stock_status"      => $product->get_stock_status(),
					"weight"            => $product->get_weight(),
					"discount"          => $this->get_formated_amount( (float) $item->get_total() ),
					"subscription"      => $subscription_info,
				);

				$subscription_info = [];

				if ( array_key_exists( $item->get_id(), $license_info ) ) {
					$productInfo['license'] = $license_info[ $item->get_id() ];
				}
			}

			array_push( $download_item, $productInfo );
		}

		return $download_item;
	}

	public function get_plugin_data( string $key = '' ) {
		$thrivedesk_options = thrivedesk_options();

		$options = $thrivedesk_options['woocommerce'] ?? [];

		return $key ? ( $options[ $key ] ?? '' ) : $options;
	}

	public function connect() {
		$thrivedesk_options                = get_option( 'thrivedesk_options', [] );
		$thrivedesk_options['woocommerce'] = $thrivedesk_options['woocommerce'] ?? [];

		$thrivedesk_options['woocommerce']['connected'] = true;

		update_option( 'thrivedesk_options', $thrivedesk_options );
	}

	public function disconnect() {
		$thrivedesk_options                = get_option( 'thrivedesk_options', [] );
		$thrivedesk_options['woocommerce'] = $thrivedesk_options['woocommerce'] ?? [];

		$thrivedesk_options['woocommerce'] = [
			'api_token' => '',
			'connected' => false,
		];

		update_option( 'thrivedesk_options', $thrivedesk_options );
	}
}
