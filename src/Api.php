<?php

namespace ThriveDesk;

use ThriveDesk\Api\Response;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

final class Api
{
    /**
     * The single instance of this class
     */
    private static $instance = null;

    /**
     * Construct Api class.
     *
     * @since 0.0.1
     * @access private
     */
    private function __construct()
    {
        add_action('init', [$this, 'api_listener']);
    }

    /**
     * Main Api Instance.
     *
     * Ensures that only one instance of Api exists in memory at any one
     * time. Also prevents needing to define globals all over the place.
     *
     * @since 0.0.1
     * @return object|Api
     * @access public
     */
    public static function instance(): object
    {
        if (!isset(self::$instance) && !(self::$instance instanceof Admin)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Api listener
     *
     * @since 0.0.1
     * @return void
     */
    public function api_listener()
    {
        if (!isset($_GET['listener']) || 'thrivedesk' !== $_GET['listener'] || !isset($_REQUEST['token'])) return;

        $apiResponse = new Response();

        $token = $_REQUEST['token'] ?? '';

        if ('abc' !== $token) {
            $apiResponse->error(401, 'Token invalid');
            wp_die();
        }

        $data = [
            "service" => "WooCommerce",
            "customer_since" => "06 Mar 2020",
            "lifetime_order_value" => "$100.00",
            "this_year_order_value" => "$100.00",
            "avg_order_value" => "$100.00",
            "recent_orders" => [
                [
                    "order_id" => "#1345",
                    "amount" => "$45.00",
                    "order_date" => "06 Mar 2020",
                    "order_status" => "Completed"
                ], [
                    "order_id" => "#1345",
                    "amount" => "$45.00",
                    "order_date" => "06 Mar 2020",
                    "order_status" => "Completed"
                ], [
                    "order_id" => "#1345",
                    "amount" => "$45.00",
                    "order_date" => "06 Mar 2020",
                    "order_status" => "Completed"
                ]
            ]
        ];

        $apiResponse->success(200, $data, 'Success');
        wp_die();
    }
}