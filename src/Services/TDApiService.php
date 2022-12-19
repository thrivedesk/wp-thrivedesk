<?php

namespace ThriveDesk\Services;

if (!defined('ABSPATH')) {
    exit;
}
class TDApiService {
    private $api_token;
    private $org_slug;

    public function __construct()
    {
        $this->api_token = get_option('td_helpdesk_settings')['td_helpdesk_api_key'];
        $this->org_slug = get_option('td_helpdesk_settings')['td_helpdesk_organization_slug'];
    }

    public function postRequest(string $url, array $data = []){

        $args     = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_token,
                'X-Td-Organization-Slug' => $this->org_slug,
            ],
            'body'    => $data,
        ];

        $response           = wp_remote_post($url, $args);
        $body               = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    public function getRequest(string $url)
    {
        $args               = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'X-Td-Organization-Slug' => $this->org_slug,
            ],
        ];

        $response           = wp_remote_get($url, $args);
        $body               = wp_remote_retrieve_body($response);
        $body               = json_decode($body, true);
        return $body ?? [];
    }
}