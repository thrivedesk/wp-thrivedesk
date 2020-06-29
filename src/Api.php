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
        return ['woo' => 'WooCommerce', 'edd' => 'EDD', 'smartpay' => 'SmartPay'];
    }

    /**
     * Api listener
     *
     * @since 0.0.1
     * @return void
     */
    public function api_listener()
    {
        if (!isset($_GET['listener']) || 'thrivedesk' !== $_GET['listener']) return;

        $token  = $_REQUEST['token'] ?? '';
        $plugin = $_REQUEST['plugin'] ?? 'woo';
        $email  = $_REQUEST['email'] ?? '';

        $thrivedesk_options = thrivedesk_options();

        // Plugin settings token
        $api_token = $thrivedesk_options['api_token'] ?? '';

        $apiResponse = new Response();

        // API token not configured response
        if (empty($api_token))
            $apiResponse->error(401, 'The API token isn\'t configured on wp plugin yet.');

        // Token invalid response
        else if ($api_token !== $token)
            $apiResponse->error(401, 'Token isn\'t valid.');

        // Plugin invalid response
        else if (!in_array(strtolower($plugin), array_keys($this->_available_plugins())))
            $apiResponse->error(401, 'Plugin isn\'t valid or not available now.');

        // Email invalid token
        else if (!is_email($email))
            $apiResponse->error(401, 'Email isn\'t valid.');

        $data = [];

        try {
            $plugin_class_name = 'ThriveDesk\\Plugins\\' . $this->_available_plugins()[$plugin] ?? 'WooCommerce';

            if (!class_exists($plugin_class_name))
                $apiResponse->error(500, "Class not found for the '{$plugin}' plugin");

            else if (!method_exists($plugin_class_name, 'prepare_data'))
                $apiResponse->error(500, "Method 'prepare_data' not exist in class '{$plugin_class_name}'");

            $data = ($plugin_class_name::instance())->prepare_data();
        } catch (\Exception $e) {
            $apiResponse->error(500, 'Can\'t not prepare data');
        }

        $apiResponse->success(200, $data, 'Success');
        wp_die();
    }
}