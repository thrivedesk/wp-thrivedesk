<?php

namespace ThriveDesk\Plugins;

use ThriveDesk\Plugin;

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
            'registered_at' => '5 Jun 2020'
        ];
    }

    public function get_orders(): array
    {
        return [];
    }
}