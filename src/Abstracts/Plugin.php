<?php

namespace ThriveDesk;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

abstract class Plugin
{
    public function __construct()
    {
        //
    }

    public function is_plugin_active(): bool
    {
        return false;
    }

    public function get_lifetime_order(array $orders): float
    {
        return array_sum(array_column($orders, 'amount'));
    }

    public function get_this_year_order(array $orders): float
    {
        $total_amount = 0;

        $total_amount = array_reduce($orders, function ($carry, $item) {
            if (strtotime($item['date']) >= strtotime('-1 year')) {
                $carry += $item['amount'];
            }
            return $carry;
        }, 0);

        return $total_amount;
    }

    public function customer_exist(): bool
    {
        return false;
    }

    public function get_customer(): array
    {
        return [
            'name' => '',
            'registered_at' => ''
        ];
    }

    public function get_orders(): array
    {
        return [];
    }

    public function prepare_data(): array
    {
        if (!$this->customer_exist()) return [];

        $customer = $this->get_customer();

        $orders = $this->get_orders();

        $lifetime_order = $this->get_lifetime_order($orders);

        $this_year_order = $this->get_this_year_order($orders);

        $avg_order = $lifetime_order ? ($lifetime_order / count($orders)) : 0;

        $data = [
            "customer_since" => $customer['registered_at'] ?? '',
            "lifetime_order" => $lifetime_order,
            "this_year_order" => $this_year_order,
            "avg_order" => $avg_order,
            "orders" => $orders
        ];

        return $data;
    }
}