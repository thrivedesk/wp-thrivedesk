<?php

namespace ThriveDesk;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

abstract class Plugin
{
    /**
     * Customer email
     *
     * @var string
     */
    public $customer_email = '';

    /**
     * Customer data
     *
     * @var object|EDD_Customer
     */
    public $customer = null;

    /**
     * Check if plugin active or not
     *
     * @return boolean
     */
    public function is_plugin_active(): bool
    {
        return false;
    }

    /**
     * Check if customer exist or not
     *
     * @return boolean
     */
    public function customer_exist(): bool
    {
        return false;
    }

    /**
     * Get the customer data
     *
     * @return array
     */
    public function get_customer(): array
    {
        return [
            'name' => '',
            'registered_at' => ''
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
        return $amount;
    }

    /**
     * Get the customer orders
     *
     * @return array
     */
    public function get_orders(): array
    {
        return [];
    }

    /**
     * Get the lifetime order value of the customer
     *
     * @return float
     */
    public function get_lifetime_order(array $orders): float
    {
        // TODO: Ignore pending or refunded amounts
        $amount = array_sum(array_column($orders, 'amount'));

        return $amount;
    }

    /**
     * Get this year order value of the customer
     *
     * @return float
     */
    public function get_this_year_order(array $orders): float
    {
        // TODO: Ignore pending or refunded amounts
        $amount = 0;

        $amount = array_reduce($orders, function ($carry, $item) {
            if (strtotime($item['date']) >= strtotime('-1 year')) {
                $carry += $item['amount'];
            }
            return $carry;
        }, 0);

        return $amount;
    }

    /**
     * Prepare data for API response
     *
     * @return array
     */
    public function prepare_data(): array
    {
        $customer = $this->get_customer();

        $orders = $this->get_orders();

        $this_year_order = $this->get_this_year_order($orders);

        $lifetime_order = $this->get_lifetime_order($orders);

        $avg_order = $lifetime_order ? ($lifetime_order / count($orders)) : 0;

        $data = [
            "customer_since" => $customer['registered_at'] ?? '',
            "lifetime_order" => $this->get_formated_amount($lifetime_order),
            "this_year_order" => $this->get_formated_amount($this_year_order),
            "avg_order" => $this->get_formated_amount($avg_order),
            "orders" => $orders
        ];

        return $data;
    }
}