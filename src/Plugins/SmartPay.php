<?php

namespace ThriveDesk\Plugins;

use ThriveDesk\Plugin;
use SmartPay\Customers\SmartPay_Customer;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

final class SmartPay extends Plugin
{
    /**
     * The single instance of this class
     */
    private static $instance = null;

    /**
     * Construct SmartPay class.
     *
     * @since 0.0.1
     * @access private
     */
    private function __construct()
    {
        //
    }

    /**
     * Main SmartPay Instance.
     *
     * Ensures that only one instance of SmartPay exists in memory at any one
     * time. Also prevents needing to define globals all over the place.
     *
     * @since 0.0.1
     * @return object|SmartPay
     * @access public
     */
    public static function instance()
    {
        if (!isset(self::$instance) && !(self::$instance instanceof SmartPay)) {
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
        if (!function_exists('SmartPay') || !class_exists('SmartPay', false)) return false;

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
            $this->customer = new SmartPay_Customer($this->customer_email);

        if (!$this->customer->ID) return false;

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
            $this->customer = new SmartPay_Customer($this->customer_email);

        if (!$this->customer->ID) return [];

        return [
            'name' => $this->customer->name ?? '',
            'registered_at' => date('d F Y', strtotime($this->customer->created_at)) ?? ''
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
        return smartpay_amount_format($amount);
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

        foreach ($this->customer->all_payments() as $order) {

            if ('live' != $order->mode) continue;

            array_push($orders, [
                'order_id'        => $order->ID,
                'amount'          => (float) $order->amount,
                'amount_formated' => $this->get_formated_amount((float) $order->amount),
                'date'            => date('d F Y', strtotime($order->date)),
                'order_status'    => $order->status_nicename
            ]);
        }

        return $orders;
    }
}