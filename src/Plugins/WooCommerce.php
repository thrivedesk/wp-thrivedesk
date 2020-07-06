<?php

namespace ThriveDesk\Plugins;

use ThriveDesk\Plugin;

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
     * @since 0.0.1
     * @return object|WooCommerce
     * @access public
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
    public function is_plugin_active(): bool
    {
        if (!function_exists('WC') || !class_exists('WooCommerce', false)) return false;

        return true;
    }

    /**
     * Check if customer exist or not
     *
     * @return boolean
     */
    public function customer_exist(): bool
    {
        if (!$this->customer_email) return false;

        if (!$this->customer) {
            $user_id = get_user_by('email', $this->customer_email)->ID ?? 0;
            $this->customer = new \WC_Customer($user_id);
        }

        if (!$this->customer->get_id()) return false;

        return true;
    }

    /**
     * The accepted payment statuses of this plugin
     *
     * @return array
     */
    public function accepted_statuses(): array
    {
        return ['Complete'];
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
            'registered_at' => date('d F Y', strtotime($this->customer->get_date_created())) ?? ''
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
        $orders = [];

        if (!$this->customer_exist()) return $orders;

        foreach (wc_get_orders('user_id', 1) as $order) {

            array_push($orders, [
                'order_id'        => $order->get_id(),
                'amount'          => (float) $order->get_total(),
                'amount_formated' => $this->get_formated_amount($order->get_total()),
                'date'            => date('d F Y', strtotime($order->get_date_created())),
                'order_status'    => ucfirst($order->get_status())
            ]);
        }

        return $orders;
    }
}
