<?php

namespace ThriveDesk\Plugins;

use ThriveDesk\Plugin;
use WC_Order_Query;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

final class WooCommerce extends Plugin
{
    /**
     * The single instance of this class
     */
    private static $instance = null;

    /**
     * To store customers order details. 
     */
    public $orders = [];
    private $flug = false;

    /**
     * Construct WooCommerce class.
     *
     * @since 0.0.1
     * @access private
     */
    private function __construct()
    {
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
    public static function instance()
    {
        if (!isset(self::$instance) && !(self::$instance instanceof WooCommerce)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Check if plugin active or not
     *
     * @return boolean
     */
    public static function is_plugin_active(): bool
    {
        if (!function_exists('WC') || !class_exists('WooCommerce', false)) return false;

        return true;
    }

    /**
     * Check if a contact, guest or customer 
     *
     * @return boolean
     */
    public function is_guest()
    {
        if (empty($this->orders) && !$this->flug) {
            $this->orders = $this->get_orders();
            $this->flug = true;
        }
        if (!empty($this->orders)) return true;
        return false;
    }

    /**
     * Check if customer exist or not
     *
     * @return boolean
     */
    public function is_customer_exist(): bool
    {
        if (!$this->customer_email) return false;

        if (!$this->customer) {
            $user_id = get_user_by('email', $this->customer_email)->ID ?? 0;
            $this->customer = new \WC_Customer($user_id);
        }

        if (!$this->customer->get_id() && !$this->is_guest()) return false;

        return true;
    }

    /**
     * The accepted payment statuses of this plugin
     *
     * @return array
     */
    public function accepted_statuses(): array
    {
        return ['Completed'];
    }

    /**
     * Get the customer data
     *
     * @return array
     */
    public function get_customer(): array
    {
        if (!$this->customer_email) return [];

        if (!$this->customer) {
            $user_id = get_user_by('email', $this->customer_email)->ID ?? 0;
            $this->customer = new \WC_Customer($user_id);
        }

        if (!$this->customer->get_id()) return [];

        return [
            'name' => $this->customer->get_display_name() ?? '',
            'registered_at' => date('d M Y', strtotime($this->customer->get_date_created())) ?? ''
        ];
    }

    /**
     * Get the formated amount
     *
     * @param float $amount
     * @return string
     */
    public function get_formated_amount(float $amount): string
    {
        return get_woocommerce_currency_symbol() . $amount;
    }

    /**
     * Get the customer orders
     *
     * @return array
     */
    public function get_orders(): array
    {

        if (empty($this->orders)) {
            $query = new WC_Order_Query();
            $query->set('customer', $this->customer_email);
            $customer_orders = $query->get_orders();

            foreach ($customer_orders as $order) {
                array_push($this->orders, [
                    'order_id' => $order->get_id(),
                    'amount' => (float)$order->get_total(),
                    'amount_formated' => $this->get_formated_amount($order->get_total()),
                    'date' => date('d M Y', strtotime($order->get_date_created())),
                    'order_status' => ucfirst($order->get_status()),
                    'shipping' => $this->get_shipping_details($order),
                    'downloads' => $this->get_order_items($order),
                    'order_url' => $order->get_edit_order_url(),
                ]);
            }
        }

        return $this->orders;
    }

    /**
     * get woocommerce order status
     *
     * @param $order_id
     * @return array
     *
     * @since 0.8.4
     */
    public function order_status($order_id): array
    {
        if (!$this->is_customer_exist()) return [];

        $order = wc_get_order($order_id);

        if (!$order) return [];

        return [
            'order_id' => $order->get_id(),
            'amount' => $order->get_total(),
            'amount_formatted' => $this->get_formated_amount($order->get_total()),
            'date' => date('d M Y', strtotime($order->get_date_created())),
            'order_status' => ucfirst($order->get_status()),
            'shipping' => $this->get_shipping_details($order),
            'downloads' => $this->get_order_items($order),
        ];
    }


    /**
     * get order shipping details
     *
     * @param $order
     * @return array
     */
    public function get_shipping_details($order): array
    {
        $states = WC()->countries->get_states($order->get_shipping_country());
        $state = !empty($states[$order->get_shipping_state()]) ? $states[$order->get_shipping_state()] : '';

        $shipping_details = [];

        array_push($shipping_details, [
            'street' => $order->get_shipping_address_1() ?? '',
            'city' => $order->get_shipping_city() ?? '',
            'zip' => $order->get_shipping_postcode() ?? '',
            'state' => $state,
            'country' => WC()->countries->countries[$order->get_shipping_country()] ?? '',
        ]);
        return $shipping_details;
    }

    /**
     * check if site url starts with http:// or https://
     *
     * @param $site_url
     * @return bool
     */
    public function check_site_url($site_url): bool
    {
        return substr($site_url, 0, 7) === "http://" ||
            substr($site_url, 0, 8) === "https://";
    }

    /**
     * get order items license details
     *
     * @param $order
     * @return array
     */
    public function get_order_items($order): array
    {
        $items = $order->get_items();

        $download_item = [];
        $license_info = [];

        if (method_exists('WOO_SL_functions', 'get_order_licence_details')) {

            $orderLicenseDetails = \WOO_SL_functions::get_order_licence_details($order->get_id());

            foreach ($orderLicenseDetails as $orderLicenses) {
                foreach ($orderLicenses as $orderLicense) {

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

                    $expire_date = intval(\WOO_SL_functions::get_order_item_meta($orderLicense->order_item_id, '_woo_sl_licensing_expire_at') ?? '');
                    $expire_date = $expire_date == 0 ? '' : date("d M Y", $expire_date);

                    $woo_site_url = '';

                    foreach ($key_instances as $key_instance) {
                        if ($key_instance->active_domain) {
                            $this->check_site_url($key_instance->active_domain) ?
                                $woo_site_url = $key_instance->active_domain :
                                $woo_site_url = "http://" . $key_instance->active_domain;
                            array_push($sites, $woo_site_url);
                        }
                    }

                    $license_info[$license->order_item_id] = [
                        'key' => $license->licence ?? '',
                        'activation_limit' => $orderLicense->license_data["max_instances_per_key"],
                        'sites' => $sites,
                        'date_created' => $license->created ?? '',
                        'expiration' => $expire_date,
                        'is_lifetime' => $orderLicense->license_data['product_use_expire'] == 'no',
                        'status' => \WOO_SL_functions::get_licence_key_status($license->id) ?? '',
                    ];
                }
            }
        }
        foreach ($items as $item) {
            if (array_key_exists($item->get_id(), $license_info)) {
                array_push($download_item, array(
                    "title" => $item["name"],
                    "license" => $license_info[$item->get_id()],
                ));
            } else {
                array_push($download_item, array(
                    "title" => $item["name"],
                ));
            }
        }

        return $download_item;
    }

    public function get_plugin_data(string $key = '')
    {
        $thrivedesk_options = thrivedesk_options();

        $options = $thrivedesk_options['woocommerce'] ?? [];

        return $key ? ($options[$key] ?? '') : $options;
    }

    public function connect()
    {
        $thrivedesk_options = get_option('thrivedesk_options', []);
        $thrivedesk_options['woocommerce'] = $thrivedesk_options['woocommerce'] ?? [];

        $thrivedesk_options['woocommerce']['connected'] = true;

        update_option('thrivedesk_options', $thrivedesk_options);
    }

    public function disconnect()
    {
        $thrivedesk_options = get_option('thrivedesk_options', []);
        $thrivedesk_options['woocommerce'] = $thrivedesk_options['woocommerce'] ?? [];

        $thrivedesk_options['woocommerce'] = [
            'api_token' => '',
            'connected' => false,
        ];

        update_option('thrivedesk_options', $thrivedesk_options);
    }
}
