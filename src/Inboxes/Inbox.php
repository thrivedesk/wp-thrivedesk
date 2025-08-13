<?php

namespace ThriveDesk\Inboxes;

use ThriveDesk\Services\TDApiService;

if (!defined('ABSPATH')) {
    exit;
}

class Inbox {

    private static $instance = null;

    public function __construct()
    {
        // ajax call for loading inboxes
        add_action('wp_ajax_thrivedesk_load_inboxes', [$this, 'thrivedesk_load_inboxes']);
    }

    public function thrivedesk_load_inboxes(): void {
        $apiKey = $_POST['data']['td_helpdesk_api_key'] ?? '';

        if (empty($apiKey)) {
            echo wp_json_encode(['status' => 'false', 'data' => []]);
            die();
        }

        $key = 'thrivedesk_inboxes_' . md5($apiKey);
        $inboxes = get_transient($key);
        
        if ($inboxes) {
            echo wp_json_encode(['status' => 'true', 'data' => $inboxes]);
            die();
        }

        $inboxes = $this->get_inboxes($apiKey);

        if (isset($inboxes) and $inboxes['inboxes']) {
            set_transient($key, $inboxes, 60 * 30);
            echo wp_json_encode(['status' => 'true', 'data' => $inboxes]);
        } else {
            echo wp_json_encode(['status' => 'false', 'data' => []]);
        }
        die();
    }

    public static function instance(): Inbox
    {
        if (self::$instance === null) {
            self::$instance = new Inbox();
        }

        return self::$instance;
    }

    public function get_inboxes($api_key = null): array
    {
        // Try different possible inbox endpoints
        $possible_endpoints = [
            '/v1/inboxes',
            '/v1/customer/inboxes',
            '/v1/me/inboxes',
            '/v1/admin/inboxes'
        ];

        $apiService = new TDApiService();
        if ($api_key) {
            $apiService->setApiKey($api_key);
        }
        
        foreach ($possible_endpoints as $endpoint) {
            $url = THRIVEDESK_API_URL . $endpoint;
            $response = $apiService->getRequest($url);

            // Debug: Log the API response
            if (WP_DEBUG) {
                error_log('ThriveDesk Debug - Trying Inbox API URL: ' . $url);
                error_log('ThriveDesk Debug - Inbox API Response: ' . print_r($response, true));
            }

            // If we get a successful response (not an error), return it
            if (!isset($response['wp_error'])) {
                // Check if response has data that looks like inboxes
                if (isset($response['data']) || isset($response['inboxes']) || (is_array($response) && !empty($response))) {
                    if (WP_DEBUG) {
                        error_log('ThriveDesk Debug - Found working inbox endpoint: ' . $url);
                    }
                    return $response;
                }
            }
        }

        // If all endpoints fail, return empty array
        if (WP_DEBUG) {
            error_log('ThriveDesk Debug - All inbox endpoints failed');
        }
        return [];
    }

    /**
     * get all inboxes
     * @return array
     */
    public static function inboxes()
    {
        $api_key = get_option('td_helpdesk_settings')['td_helpdesk_api_key'] ?? '';
        if (empty($api_key)) {
            return [];
        }

        $key = 'thrivedesk_inboxes_' . md5($api_key);
        $inboxes = get_transient($key);

        if ($inboxes) {
            return $inboxes['inboxes'] ?? [];
        }

        $inboxes = (new Inbox)->get_inboxes();

        if (isset($inboxes['inboxes'])) {
            set_transient($key, $inboxes, 60 * 30);
        }

        return $inboxes['inboxes'] ?? [];
    }

    public static function get_inbox_settings()
    {
        $inbox_settings = get_option('td_inbox_settings');

        return $inbox_settings ?? [];
    }
}