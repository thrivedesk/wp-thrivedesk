<?php

namespace ThriveDesk;

use ThriveDesk\Api\ApiResponse;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

final class Api {
	/**
	 * The single instance of this class
	 */
	private static $instance = null;

	private $apiResponse;

	private $plugin = null;

	/**
	 * Construct Api class.
	 *
	 * @since  0.0.1
	 * @access private
	 */
	private function __construct() {
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
	 * @since  0.0.1
	 */
	public static function instance(): object {
		if (!isset(self::$instance) && !(self::$instance instanceof Admin)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Available plugins
	 *
	 * @return array
	 * @since 0.0.1
	 */
	private function _available_plugins(): array {
		return [
			'edd'         => 'EDD',
			'woocommerce' => 'WooCommerce',
			'fluentcrm'   => 'FluentCRM',
			'wppostsync'  => 'WPPostSync',
			'autonami'    => 'Autonami',
		];
	}

	/**
	 * Api listener
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function api_listener(): void {
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

			$plugin_name       = $this->_available_plugins()[$plugin] ?? 'EDD';
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
			} elseif (isset($action) && 'disconnect' === $action) {
				$this->disconnect_action_handler();
			} elseif (isset($action) && 'get_fluentcrm_data' === $action) {
				$this->fluentcrm_handler();
			} elseif (isset($action) && 'handle_autonami' === $action) {
				$this->autonami_handler();
			} elseif (isset($action) && 'get_wppostsync_data' === $action) {
				$remote_query_string = strtolower($_GET['query'] ?? '');
				$this->wp_postsync_data_handler($remote_query_string);
			} elseif (isset($action) && 'get_woocommerce_order_status' === $action) {
				$this->get_woocommerce_order_status();
			} else {
				$this->plugin_data_action_handler();
			}
		} catch (\Exception $e) {
			$this->apiResponse->error(500, 'Can\'t not prepare data');
		}

		wp_die();
	}

	/**
	 * handler autonami action
	 */
	public function autonami_handler() {
		$syncType                     = strtolower(sanitize_key($_REQUEST['sync_type'] ?? ''));
		$this->plugin->customer_email = sanitize_email($_GET['email'] ?? '');

		if ($syncType) {
			$this->plugin->sync_conversation_with_autonami($syncType, $_REQUEST['extra'] ?? []);
		} else {
			if (!method_exists($this->plugin, 'prepare_data')) {
				$this->apiResponse->error(500, "Method 'prepare_data' not exist in plugin");
			}

			if (!$this->plugin->is_customer_exist()) {
				$this->apiResponse->error(404, "Customer not found.");
			}

			$data = $this->plugin->prepare_data();

			$this->apiResponse->success(200, $data, 'Success');
		}
	}

	/**
	 * get woocommerce order status
	 *
	 * @since 0.9.0
	 */
	public function get_woocommerce_order_status() {
		$email    = sanitize_email($_REQUEST['email'] ?? '');
		$order_id = strtolower(sanitize_key($_REQUEST['order_id'] ?? ''));

		if (!method_exists($this->plugin, 'order_status')) {
			$this->apiResponse->error(500, "Method 'order_status' not exist in plugin");
		}

		$this->plugin->customer_email = $email;

		if (!$this->plugin->is_customer_exist()) {
			$this->apiResponse->error(404, "Customer not found.");
		}

		$data = $this->plugin->order_status($order_id);

		$this->apiResponse->success(200, $data, 'Success');
	}

	/**
	 * data handler for FluentCRM
	 *
	 * @return void
	 * @since 0.7.0
	 */
	public function fluentcrm_handler(): void {
		$syncType                     = strtolower(sanitize_key($_REQUEST['sync_type'] ?? ''));
		$this->plugin->customer_email = sanitize_email($_REQUEST['email'] ?? '');

		if ($syncType) {
			$this->plugin->sync_conversation_with_fluentcrm($syncType, $_REQUEST['extra'] ?? []);
		} else {
			if (!method_exists($this->plugin, 'prepare_fluentcrm_data')) {
				$this->apiResponse->error(500, "Method 'prepare_fluentcrm_data' not exist in plugin");
			}

			if (!$this->plugin->is_customer_exist()) {
				$this->apiResponse->error(404, "Customer not found.");
			}
			$data = $this->plugin->prepare_fluentcrm_data();

			$this->apiResponse->success(200, $data, 'Success');
		}
	}

	/**
	 * data handler for wp-post-sync
	 *
	 * @param $remote_query_string
	 *
	 * @since 0.8.0
	 */
	public function wp_postsync_data_handler($remote_query_string): void {
		$search_data = $this->plugin->get_post_search_result($remote_query_string);
		$this->apiResponse->success(200, $search_data, 'Success');
	}

	/**
	 * Handle plugin connect request
	 *
	 * @return void
	 * @since 0.0.4
	 */
	public function connect_action_handler(): void {
		$this->plugin->connect();

		$this->apiResponse->success(200, [], 'Site connected successfully');
	}

	/**
	 * Handle plugin disconnect request
	 *
	 * @return void
	 * @since 0.0.4
	 */
	public function disconnect_action_handler(): void {
		$this->plugin->disconnect();

		$this->apiResponse->success(200, [], 'Site has been disconnected');
	}

	/**
	 * Handle plugin data request
	 *
	 * @return void
	 * @since 0.0.4
	 */
	public function plugin_data_action_handler() {
		$email          = sanitize_email($_REQUEST['email'] ?? '');
		$enableShipping = $_REQUEST['shipping_param'] == 1 ? true : false;

		if (!method_exists($this->plugin, 'prepare_data')) {
			$this->apiResponse->error(500, "Method 'prepare_data' not exist in plugin");
		}

		$this->plugin->customer_email = $email;
		$this->plugin->shipping_param = $enableShipping;

		if (!$this->plugin->is_customer_exist()) {
			$this->apiResponse->error(404, "Customer not found.");
		}

		$data = $this->plugin->prepare_data();

		$this->apiResponse->success(200, $data, 'Success');
	}

	/**
	 * Verify api request token
	 *
	 * @return boolean
	 * @since 0.0.4
	 */
	private function verify_token(): bool {
		$payload = $_REQUEST;

		if ($payload) {
			foreach ($payload as $key => $value) {
				if (!is_string($value)) {
					continue;
				}
				switch (strtolower($value)) {
					case "1":
					case "true":
						$payload[$key] = true;
						break;

					case "0":
					case "false":
						$payload[$key] = false;
						break;
				}
			}
		}

		$api_token = $this->plugin->get_plugin_data('api_token');

		$signature = $_SERVER['HTTP_X_TD_SIGNATURE'];

		return hash_equals($signature, hash_hmac('SHA1', json_encode($payload), $api_token));
	}
}