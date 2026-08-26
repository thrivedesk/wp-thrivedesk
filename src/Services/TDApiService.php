<?php

namespace ThriveDesk\Services;

if (!defined('ABSPATH')) {
    exit;
}
class TDApiService {
    /**
     * Generous enough for the endpoints that do real work upstream. Callers
     * that only need a liveness answer, and that run inside a page render,
     * should pass something far shorter.
     */
    public const DEFAULT_TIMEOUT = 90;

    private $api_token;

    public function __construct()
    {
        $this->api_token = get_option('td_helpdesk_settings')['td_helpdesk_api_key'] ?? '';
    }

    /**
     * POST to the API.
     *
     * Returns the decoded body on success, or the same
     * ['wp_error' => true, 'error_type' => ..., 'message' => ...] shape
     * getRequest() returns on failure. Callers must check for it: this backs
     * the customer's support reply, and reporting a reply that never left the
     * site as "sent" loses it silently.
     *
     * @param string $url     Endpoint.
     * @param array  $data    Request body.
     * @param int    $timeout Request timeout in seconds.
     *
     * @return array
     */
    public function postRequest(string $url, array $data = [], int $timeout = self::DEFAULT_TIMEOUT): array
    {
        $args     = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_token,
            ],
            'body'    => $data,
            'timeout' => $timeout,
        ];

        return $this->handle_response(wp_remote_post($url, $args));
    }

    public function getRequest(string $url, int $timeout = self::DEFAULT_TIMEOUT)
    {
        $args               = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
	        'timeout' => $timeout,
        ];

        return $this->handle_response(wp_remote_get($url, $args));
    }

    /**
     * Turn a wp_remote_* result into either the decoded body or a typed error.
     *
     * Shared by getRequest() and postRequest() so a failed POST cannot be
     * mistaken for a success.
     *
     * @param array|\WP_Error $response Raw wp_remote_* return value.
     *
     * @return array
     */
    private function handle_response($response): array
    {
        $response_code      = wp_remote_retrieve_response_code( $response );
        $instruction_ip_whitelist = 'Please try to white list these IP addresses: 20.68.187.32, 20.68.186.235, 20.117.184.59';

		if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            error_log( 'ThriveDesk - API call error: ' . $error_message ); // Log the error
            // The request never reached ThriveDesk (DNS/SSL/timeout/connection
            // refused) - this happens transiently right after a domain
            // migration while DNS/SSL are still settling, and says nothing
            // about whether the stored token is valid, so callers must not
            // treat it the same as a real auth rejection.
            return ['wp_error' => true, 'error_type' => 'network', 'message' => 'ThriveDesk - API call error:' . $error_message];
		} else {
            // Check the response code
            $body               = wp_remote_retrieve_body($response);

            if ( 200 === $response_code ) {
                $decoded = json_decode($body, true);

                // Every caller indexes what comes back, so a body that isn't
                // the JSON object they expect (a proxy answering with a bare
                // string, an empty body) has to arrive as "nothing useful"
                // rather than as something that fatals on the first offset.
                return is_array($decoded) ? $decoded : [];
            } else {
                error_log( 'ThriveDesk - API Request Failed. Response Code: ' . $response_code );

                if ( 403 === $response_code || 402 === $response_code ) {
                    $body               = wp_remote_retrieve_body($response);

                    if (str_contains($body, 'Cloudflare')) {
                        return ['wp_error' => true, 'error_type' => 'network', 'message' => 'ThriveDesk - API blocked by Cloudflare. ' . $instruction_ip_whitelist];
                    }
                }

                $body = json_decode($body, true);

                // An error body isn't always the JSON object we expect - a
                // proxy in front of the API can answer with a bare JSON string,
                // and indexing one of those is a fatal TypeError.
                $api_message = is_array($body) ? ($body['message'] ?? '') : '';

                // 401/403 (once the Cloudflare/WAF case above is ruled out)
                // mean the API itself rejected the token - only this is a
                // genuine auth failure. Anything else (5xx, etc.) is a
                // server-side/transient problem and must not be read as
                // "the token is invalid".
                $error_type = in_array($response_code, [401, 403], true) ? 'auth' : 'server';

                return ['wp_error' => true, 'error_type' => $error_type, 'message' => 'ThriveDesk - API request failed. Response Code:' . $response_code . '. Message: ' . $api_message];
            }
        }

        error_log( 'ThriveDesk - API Request Failed. Unknown error: ' . $response_code ); // Log the error
        return ['wp_error' => true, 'error_type' => 'server', 'message' => 'ThriveDesk - Unknown API request error. Response Code:' . $response_code];
    }

    public function clearAllTransients()
    {
        delete_transient('thrivedesk_assistants');
        delete_transient(PortalService::PORTAL_ACCESS_TRANSIENT);
    }

	public function setApiKey( $apiKey ): void {
        $this->clearAllTransients();
		$this->api_token = $apiKey;
	}
}
