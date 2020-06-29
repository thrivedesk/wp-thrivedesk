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

    public $customer_email = '';

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

    public function is_plugin_active(): bool
    {
        if (!function_exists('EDD')) return false;

        return true;
    }

    public function customer_exist(): bool
    {
        // TODO: Check customer
        if ($this->customer_email === 'alaminfirdows@gmail.com') return true;

        return false;
    }

    public function get_customer(): array
    {
        return [
            'name' => 'alamin',
            'registered_at' => '10 Jun 2020'
        ];
    }

    public function get_orders(): array
    {
        return [[
            "order_id" => "1345",
            "amount" => "10",
            "amount_formated" => "$10",
            "date" => "06 Mar 2020",
            "order_status" => "Completed"
        ], [
            "order_id" => "1345",
            "amount" => "25.10",
            "amount_formated" => "$25",
            "date" => "06 Mar 2020",
            "order_status" => "Completed"
        ], [
            "order_id" => "1345",
            "amount" => "55",
            "amount_formated" => "$55",
            "date" => "21 Jun 2019",
            "order_status" => "Completed"
        ]];
    }
}