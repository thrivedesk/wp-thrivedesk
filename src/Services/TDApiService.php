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
            'timeout' => 90,
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
	        'timeout' => 90,
        ];

        $response           = wp_remote_get($url, $args);

        $response_code      = wp_remote_retrieve_response_code( $response );
        $instruction_ip_whitelist = 'Please try to white list these IP addresses: 20.68.187.32, 20.68.186.235, 20.117.184.59';


		if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();

            error_log( 'ThriveDesk - API call error: ' . $error_message ); // Log the error
            return ['wp_error' => true, 'message' => 'ThriveDesk - API call error:' . $error_message];
		} else {
            // Check the response code
            $body               = wp_remote_retrieve_body($response);

            if ( 200 === $response_code ) {
                // Success: Process the response body
                return json_decode($body, true);
            } else {
                error_log( 'ThriveDesk - API Request Failed. Response Code: ' . $response_code .'.  '. print_r($response, true)); // Log the error

                if ( 403 === $response_code || 402 === $response_code ) {
                    $body               = wp_remote_retrieve_body($response);

                    if (str_contains($body, 'Cloudflare')) {
                        return ['wp_error' => true, 'message' => 'ThriveDesk - API blocked by Cloudflare. ' . $instruction_ip_whitelist];
                    }
                }

                $body = json_decode($body, true);

                return ['wp_error' => true, 'message' => 'ThriveDesk - API request failed. Response Code:' . $response_code. '. Message: '. $body['message'] ?? ''];
            }
        }

        error_log( 'ThriveDesk - API Request Failed. Unknown error: ' . $response_code ); // Log the error
        return ['wp_error' => true, 'message' => 'ThriveDesk - Unknown API request error. Response Code:' . $response_code];
    }

    public function clearAllTransients()
    {
        delete_transient('thrivedesk_assistants');
        delete_transient('thrivedesk_conversations_total_pages');
        delete_transient('thrivedesk_portal_access');
    }

	public function setApiKey( $apiKey ): void {
        $this->clearAllTransients();
		$this->api_token = $apiKey;
	}
}
