<?php

namespace ThriveDesk\Plugins;

use ThriveDesk\Plugin;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

final class EDD extends Plugin
{
    /**
     * The single instance of this class
     */
    private static $instance = null;

    /**
     * Construct EDD class.
     *
     * @since 0.0.1
     * @access private
     */
    private function __construct()
    {
    }

    /**
     * Main EDD Instance.
     *
     * Ensures that only one instance of EDD exists in memory at any one
     * time. Also prevents needing to define globals all over the place.
     *
     * @since 0.0.1
     * @return object|EDD
     * @access public
     */
    public static function instance()
    {
        if (!isset(self::$instance) && !(self::$instance instanceof EDD)) {
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
        if (!function_exists('EDD')) return false;

        return true;
    }

    /**
     * Check if customer exist or not
     *
     * @return boolean
     */
    public function is_customer_exist(): bool
    {
        if (!$this->customer_email) return false;

        if (!$this->customer)
            $this->customer = new \EDD_Customer($this->customer_email);

        if (!$this->customer->id) return false;

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

        if (!$this->customer)
            $this->customer = new \EDD_Customer($this->customer_email);

        if (!$this->customer->id) return [];

        return [
            'name' => $this->customer->name ?? '',
            'registered_at' => date('d F Y', strtotime($this->customer->date_created)) ?? ''
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
        return edd_currency_filter(edd_format_amount($amount));
    }

    /**
     * Get the customer orders
     *
     * @return array
     */
    public function get_orders(): array
    {
        $orders = [];

        if (!$this->is_customer_exist()) return $orders;

        foreach ($this->customer->get_payments() as $order) {

            if ('live' != $order->mode) continue;

            array_push($orders, [
                'order_id'        => $order->number,
                'amount'          => (float) $order->total,
                'amount_formated' => $this->get_formated_amount($order->total),
                'date'            => date('d F Y', strtotime($order->date)),
                'order_status'    => $order->status_nicename
            ]);
        }

        return $orders;
    }
}
