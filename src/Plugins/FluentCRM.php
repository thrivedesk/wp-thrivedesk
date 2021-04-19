<?php


namespace ThriveDesk\Plugins;

use ThriveDesk\Plugin;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

final class FluentCRM extends Plugin
{
    /**
     * The single instance of this class
     */
    private static $instance = null;

    public function accepted_statuses(): array
    {
        return [];
    }

    /**
     * Check if plugin active or not
     *
     * @return boolean
     */
    public static function is_plugin_active(): bool
    {
        if (is_plugin_active('fluent-crm/fluent-crm.php')){
            return true;
        }

        return false;
    }

    /**
     * Check if customer exist or not
     *
     * @return boolean
     */
    public function is_customer_exist(): bool
    {

        return true;
    }

    /**
     * Main FluentCRM Instance.
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
        if (!isset(self::$instance) && !(self::$instance instanceof FluentCRM)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function connect()
    {
        $thrivedesk_options = get_option('thrivedesk_options', []);
        $thrivedesk_options['fluentcrm'] = $thrivedesk_options['fluentcrm'] ?? [];

        $thrivedesk_options['fluentcrm']['connected'] = true;

        update_option('thrivedesk_options', $thrivedesk_options);
    }

    public function disconnect()
    {
        $thrivedesk_options = get_option('thrivedesk_options', []);
        $thrivedesk_options['fluentcrm'] = $thrivedesk_options['fluentcrm'] ?? [];

        $thrivedesk_options['fluentcrm'] = [
            'api_token' => '',
            'connected' => false,
        ];

        update_option('thrivedesk_options', $thrivedesk_options);
    }

    /**
     * Get the customer orders
     *
     * @return array
     */
    public function get_orders(): array
    {
        $orders = [];


        return $orders;
    }

    public function get_plugin_data(string $key = '')
    {
        $thrivedesk_options = thrivedesk_options();

        $options = $thrivedesk_options['fluentcrm'] ?? [];

        return $key ? ($options[$key] ?? '') : $options;
    }

    /**
     * Get the customer data
     *
     * @return array
     */
    public function get_customer(): array
    {

        return [
        ];
    }
}