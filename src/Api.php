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
        return [
            'woo' => 'WooCommerce',
            'edd' => 'EDD',
            'smartpay' => 'SmartPay'
        ];
    }

    /**
     * Api listener
     *
     * @since 0.0.1
     * @return void
     */
    public function api_listener()
    {
        if ('thrivedesk' !== sanitize_key($_GET['listener'] ?? '')) {
            return;
        }

        $plugin     = strtolower(sanitize_key($_REQUEST['plugin'] ?? ''));
        $email      = sanitize_email($_REQUEST['email'] ?? '');
        $api_token  = $_SERVER['HTTP_X_TD_API_TOKEN'] ?? '';

        $token_verified = $this->verify_token(http_build_query(['email' => $email, 'plugin' => $plugin]), $api_token);

        $api_response = new Response();

        if (!$token_verified) {
            return $api_response->error(401, 'API token is invalid.');
        }

        // Plugin invalid
        if (!in_array($plugin, array_keys($this->_available_plugins()))) {
            return $api_response->error(401, 'Plugin is invalid or not available now.');
        }

        // Email invalid
        if (!is_email($email)) {
            return $api_response->error(401, 'Email is invalid.');
        }

        try {
            $plugin_name = $this->_available_plugins()[$plugin];
            $plugin_class_name = 'ThriveDesk\\Plugins\\' .  $plugin_name;

            if (!class_exists($plugin_class_name)) {
                return $api_response->error(500, "Class not found for the '{$plugin_name}' plugin");
            }

            $pluginObj = $plugin_class_name::instance();

            if (!method_exists($plugin_class_name, 'is_plugin_active')) {
                return $api_response->error(500, "Method 'prepare_data' not exist in class '{$plugin_class_name}'");
            }

            if (!$pluginObj->is_plugin_active()) {
                return $api_response->error(500, "The plugin '{$plugin_name}' isn't installed or active.");
            }

            if (!method_exists($plugin_class_name, 'prepare_data')) {
                return $api_response->error(500, "Method 'prepare_data' not exist in class '{$plugin_class_name}'");
            }

            $pluginObj->customer_email = $email;

            if (!$pluginObj->is_customer_exist()) {
                return $api_response->error(404, "No customer found for the email '{$email}'.");
            }

            return $api_response->success(200, $pluginObj->prepare_data(), 'Success');
        } catch (\Exception $e) {
            return $api_response->error(500, 'Can\'t not prepare data');
        }
        wp_die();
    }

    /**
     * Verify api token
     * @since 0.0.3
     * 
     * @param string $data
     * @param string $api_token
     * @return boolean
     */
    private function verify_token(string $data, string $api_token)
    {
        if (!$data || !$api_token) {
            return;
        }

        return hash_hmac('sha1', $data, 'thrivedesk') === $api_token;
    }
}