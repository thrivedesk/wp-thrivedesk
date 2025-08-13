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

        if (isset($inboxes) and $inboxes['data']) {
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
        try {
            $url = THRIVEDESK_API_URL . '/v1/inboxes';

            $apiService = new TDApiService();
            if ($api_key) {
                $apiService->setApiKey($api_key);
            }

            $response = $apiService->getRequest($url);

            if (isset($response['wp_error'])) {
                error_log('ThriveDesk - Inbox API Error: ' . ($response['message'] ?? 'Unknown error'));
                return [];
            }

            return $response;
            
        } catch (Exception $e) {
            error_log('ThriveDesk - Inbox API Exception: ' . $e->getMessage());
            return [];
        } catch (Error $e) {
            error_log('ThriveDesk - Inbox API Fatal Error: ' . $e->getMessage());
            return [];
        }
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
            return $inboxes['data'] ?? [];
        }

        $inboxes = (new Inbox)->get_inboxes();

        if (isset($inboxes['data'])) {
            set_transient($key, $inboxes, 60 * 30);
        }

        return $inboxes['data'] ?? [];
    }

    public static function get_inbox_settings()
    {
        $inbox_settings = get_option('td_inbox_settings');

        return $inbox_settings ?? [];
    }
}