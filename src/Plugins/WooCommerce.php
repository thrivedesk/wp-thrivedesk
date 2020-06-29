<?php

namespace ThriveDesk\Plugins;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

final class WooCommerce
{
    /**
     * The single instance of this class
     */
    private static $instance = null;

    /**
     * Construct WooCommerce class.
     *
     * @since 0.0.1
     * @access private
     */
    private function __construct()
    {
    }

    /**
     * Main WooCommerce Instance.
     *
     * Ensures that only one instance of WooCommerce exists in memory at any one
     * time. Also prevents needing to define globals all over the place.
     *
     * @since 0.0.1
     * @return object|WooCommerce
     * @access public
     */
    public static function instance()
    {
        if (!isset(self::$instance) && !(self::$instance instanceof WooCommerce)) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}