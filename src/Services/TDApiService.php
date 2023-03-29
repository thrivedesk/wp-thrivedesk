<?php

namespace ThriveDesk\Services;

if (!defined('ABSPATH')) {
    exit;
}
class TDApiService {
    private $api_token;

    public function __construct()
    {
        $this->api_token = get_option('td_helpdesk_settings')['td_helpdesk_api_key'] ?? '';
    }

    public function postRequest(string $url, array $data = []){

        $args     = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_token,
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
            ],
	        'timeout' => 120,
        ];

        $response           = wp_remote_get($url, $args);

		if ( is_wp_error( $response ) ) {
			return ['wp_error' => true, 'message' => $response->get_error_message()];
		}

        $body               = wp_remote_retrieve_body($response);
        $body               = json_decode($body, true);

        return $body ?? [];
    }

	public function setApiKey( $apiKey ): void {
		$this->api_token = $apiKey;
	}
}
