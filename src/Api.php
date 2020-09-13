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
     * Available pulings
     *
     * @since 0.0.1
     * @return array
     */
    private function _available_plugins(): array
    {
        return ['woocommerce' => 'WooCommerce', 'edd' => 'EDD', 'smartpay' => 'SmartPay'];
    }

    /**
     * Api listener
     *
     * @since 0.0.1
     * @return void
     */
    public function api_listener()
    {
        $listener = sanitize_key($_GET['listener'] ?? '');
        if (!isset($listener) || 'thrivedesk' !== $listener) return;

        $token  = sanitize_key($_REQUEST['token'] ?? '');
        $plugin = strtolower(sanitize_key($_REQUEST['plugin'] ?? 'woo'));
        $email  = sanitize_email($_REQUEST['email'] ?? '');

        $thrivedesk_options = thrivedesk_options();

        // Plugin settings token
        $api_token = $thrivedesk_options['api_token'] ?? '';

        $apiResponse = new Response();

        // API token not configured response
        if (empty($api_token))
            $apiResponse->error(401, 'The API token isn\'t configured on wp plugin yet.');

        // Token invalid response
        if ($api_token !== $token)
            $apiResponse->error(401, 'Token is invalid.');

        // Plugin invalid response
        if (!in_array($plugin, array_keys($this->_available_plugins())))
            $apiResponse->error(401, 'Plugin is invalid or not available now.');

        // Email invalid token
        if (!is_email($email))
            $apiResponse->error(401, 'Email is invalid.');

        try {
            $plugin_name = $this->_available_plugins()[$plugin] ?? 'WooCommerce';
            $plugin_class_name = 'ThriveDesk\\Plugins\\' .  $plugin_name;

            if (!class_exists($plugin_class_name))
                $apiResponse->error(500, "Class not found for the '{$plugin_name}' plugin");

            $pluginObj = $plugin_class_name::instance();

            if (!method_exists($plugin_class_name, 'is_plugin_active'))
                $apiResponse->error(500, "Method 'prepare_data' not exist in class '{$plugin_class_name}'");

            if (!$pluginObj->is_plugin_active())
                $apiResponse->error(500, "The plugin '{$plugin_name}' isn't installed or active.");

            if (!method_exists($plugin_class_name, 'prepare_data'))
                $apiResponse->error(500, "Method 'prepare_data' not exist in class '{$plugin_class_name}'");

            $pluginObj->customer_email = $email;

            if (!$pluginObj->is_customer_exist())
                $apiResponse->error(404, "No customer found for the email '{$email}'.");

            $data = $pluginObj->prepare_data();

            $apiResponse->success(200, $data, 'Success');
        } catch (\Exception $e) {
            $apiResponse->error(500, 'Can\'t not prepare data');
        }
        wp_die();
    }
}