<?php

namespace ThriveDesk\Plugins;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

final class SmartPay
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
}