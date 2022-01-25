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
     * @var object
     */
    public $customer = null;

    /**
     * Check if plugin active or not
     *
     * @return boolean
     */
    abstract public static function is_plugin_active(): bool;

    /**
     * Check if customer exist or not
     *
     * @return boolean
     */
    abstract public function is_customer_exist(): bool;

    /**
     * Get the accepted payment statuses of this plugin
     *
     * @return array
     */
    abstract public function accepted_statuses(): array;

    /**
     * Get the customer data
     *
     * @return array
     */
    abstract public function get_customer(): array;

    /**
     * Get plugin data
     *
     * @param string $key
     *
     * @return mixed
     */
    abstract public function get_plugin_data(string $key = '');

    /**
     * Update plugin connect information
     *
     * @return void
     */
    abstract public function connect();

    /**
     * Update plugin disconnect information
     *
     * @return void
     */
    abstract public function disconnect();

    /**
     * Get the formated amount
     *
     * @param float $amount
     *
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
    abstract public function get_orders(): array;

    /**
     * Get the accepted orders only
     *
     * @param array $orders
     *
     * @return array
     */
    public function filter_accepted_orders(array $orders): array
    {
        $accepted_statuses = $this->accepted_statuses() ?? [];

        return array_filter($orders, function ($order) use ($accepted_statuses) {
            return in_array($order['order_status'], $accepted_statuses);
        });
    }

    /**
     * Get the lifetime order value of the customer
     *
     * @param array $orders
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
     * @param array $orders
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

        $accepted_orders = $this->filter_accepted_orders($orders);

        $this_year_order = $this->get_this_year_order($accepted_orders);

        $lifetime_order = $this->get_lifetime_order($accepted_orders);

        $avg_order = $lifetime_order ? ($lifetime_order / count($accepted_orders)) : 0;

        $avg_order = number_format((float)$avg_order, 2, '.', '');

        return [
            "customer"        => $customer ?? [],
            "customer_since"  => $customer['registered_at'] ?? '',
            "lifetime_order"  => $this->get_formated_amount($lifetime_order),
            "this_year_order" => $this->get_formated_amount($this_year_order),
            "avg_order"       => $this->get_formated_amount($avg_order),
            "orders"          => $orders
        ];
    }
}