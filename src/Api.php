<?php

namespace ThriveDesk;

use ThriveDesk\Api\ApiResponse;

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

    private $apiResponse;

    private $plugin = null;

    /**
     * Construct Api class.
     *
     * @since 0.0.1
     * @access private
     */
    private function __construct()
    {
        add_action('init', [$this, 'api_listener']);

        $this->apiResponse = new ApiResponse();
    }


    /**
     * Main Api Instance.
     *
     * Ensures that only one instance of Api exists in memory at any one
     * time. Also prevents needing to define globals all over the place.
     *
     * @return object|Api
     * @access public
     * @since 0.0.1
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
     * @return array
     * @since 0.0.1
     */
    private function _available_plugins(): array
    {
        return [
            'edd'           => 'EDD',
            'woocommerce'   => 'WooCommerce',
            'fluentcrm'     => 'FluentCRM',
        ];
    }

    /**
     * Api listener
     *
     * @return void
     * @since 0.0.1
     */
    public function api_listener(): void
    {
        $listener = sanitize_key($_GET['listener'] ?? '');
        if (!isset($listener) || 'thrivedesk' !== $listener) {
            return;
        }

        try {
            $action = strtolower(sanitize_key($_GET['action'] ?? ''));
            $plugin = strtolower(sanitize_key($_GET['plugin'] ?? 'edd'));

            // Plugin invalid response
            if (!in_array($plugin, array_keys($this->_available_plugins()))) {
                $this->apiResponse->error(401, 'Plugin is invalid or not available now.');
            }

            $plugin_name = $this->_available_plugins()[$plugin] ?? 'EDD';
            $plugin_class_name = 'ThriveDesk\\Plugins\\' . $plugin_name;

            if (!class_exists($plugin_class_name)) {
                $this->apiResponse->error(500, "Class not found for the '{$plugin_name}' plugin");
            }

            $this->plugin = $plugin_class_name::instance();

            if (!method_exists($this->plugin, 'is_plugin_active')) {
                $this->apiResponse->error(500, "Method 'prepare_data' not exist in class '{$plugin_class_name}'");
            }

            if (!$this->plugin->is_plugin_active()) {
                $this->apiResponse->error(500, "The plugin '{$plugin_name}' isn't installed or active.");
            }

            if (!$this->verify_token()) {
                $this->apiResponse->error(401, 'Request unauthorized');
            }

            if (isset($action) && 'connect' === $action) {
                $this->connect_action_handler();
            } else if (isset($action) && 'disconnect' === $action) {
                $this->disconnect_action_handler();
            } else if (isset($action) && 'get_fluentcrm_data' === $action) {
                $this->fluentcrm_data_handler();
            } else {
                $this->plugin_data_action_handler();
            }
        } catch (\Exception $e) {
            $this->apiResponse->error(500, 'Can\'t not prepare data');
        }

        wp_die();
    }

    /**
     * data handler for FluentCRM
     *
     * @return void
     */
    public function fluentcrm_data_handler(): void
    {
        $sync_type = strtolower(sanitize_key($_GET['sync_type'] ?? ''));

        $td_conversation = [
            'id'            => $_GET['td_conversation_id'] ?? '',
            'title'         => $_GET['td_conversation_subject'] ?? '',
            'ticket_id'     => $_GET['td_conversation_ticket_id'] ?? '',
            'inbox_id'      => $_GET['td_conversation_inbox_id'] ?? '',
            'contact'       => $_GET['td_conversation_contact'] ?? '',
            'status'        => $_GET['td_conversation_status'] ?? '',
        ];

        if (!method_exists($this->plugin, 'prepare_fluentcrm_data')) {
            $this->apiResponse->error(500, "Method 'prepare_fluentcrm_data' not exist in plugin");
        }

        $this->plugin->customer_email = sanitize_email($_REQUEST['email'] ?? '');
        $this->plugin->create_new_contact = strtolower(sanitize_key($_GET['create_new_contact'] ?? false));
        $this->plugin->contact_name = strtolower(sanitize_key($_GET['contact_name'] ?? ''));

        if (!$this->plugin->is_customer_exist())
            $this->apiResponse->error(404, "Customer not found.");

        $data = $this->plugin->prepare_fluentcrm_data();

        if(!$sync_type)
        {
            $this->plugin->td_conversation = $td_conversation;
            $this->plugin->syncTypeHandler('delete_conversation');
        }
        $this->apiResponse->success(200, $data, 'Success');

    }

    /**
     * Handle plugin connect request
     *
     * @return void
     * @since 0.0.4
     */
    public function connect_action_handler(): void
    {
        $this->plugin->connect();

        $this->apiResponse->success(200, [], 'Site connected successfully');
    }

    /**
     * Handle plugin disconnect request
     *
     * @return void
     * @since 0.0.4
     */
    public function disconnect_action_handler(): void
    {
        $this->plugin->disconnect();

        $this->apiResponse->success(200, [], 'Site has been disconnected');
    }

    /**
     * Handle plugin data request
     *
     * @return void
     * @since 0.0.4
     */
    public function plugin_data_action_handler()
    {
        $email = sanitize_email($_REQUEST['email'] ?? '');

        if (!method_exists($this->plugin, 'prepare_data')) {
            $this->apiResponse->error(500, "Method 'prepare_data' not exist in plugin");
        }

        $this->plugin->customer_email = $email;

        if (!$this->plugin->is_customer_exist())
            $this->apiResponse->error(404, "Customer not found.");

        $data = $this->plugin->prepare_data();

        $this->apiResponse->success(200, $data, 'Success');
    }

    /**
     * Verify api request token
     *
     * @return boolean
     * @since 0.0.4
     */
    private function verify_token(): bool
    {
        $payload = [];
        foreach ($_REQUEST as $key => $value) {
            switch (strtolower($value)) {
                case 'true':
                case 'false':
                case '0':
                case '1':
                $payload[$key] = (bool)$value;
                break;
                default:
                    $payload[$key] = $value;
            }
        }
        $api_token = $this->plugin->get_plugin_data('api_token');

        $signature = $_SERVER['HTTP_X_TD_SIGNATURE'];

        return hash_equals($signature, hash_hmac('SHA1', json_encode($payload), $api_token));
    }
}