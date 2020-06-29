<?php

namespace ThriveDesk\Plugins;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

final class EDD
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

    public function prepare_data()
    {
        $data = [
            "customer_since" => "06 Mar 2020",
            "lifetime_order" => "$100.00",
            "this_year_order" => "$100.00",
            "avg_order" => "$100.00",
            "orders" => [
                [
                    "order_id" => "1345",
                    "amount" => "$45.00",
                    "order_date" => "06 Mar 2020",
                    "order_status" => "Completed"
                ], [
                    "order_id" => "1345",
                    "amount" => "$45.00",
                    "order_date" => "06 Mar 2020",
                    "order_status" => "Completed"
                ], [
                    "order_id" => "1345",
                    "amount" => "$45.00",
                    "order_date" => "06 Mar 2020",
                    "order_status" => "Completed"
                ]
            ]
        ];

        return $data;
    }
}