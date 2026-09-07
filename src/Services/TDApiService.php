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
        $this->api_token = self::stored_api_key();
    }

    /**
     * The helpdesk key as it is stored.
     *
     * Read the same way thrivedesk_is_connected() reads it, which is the
     * predicate deciding whether this site counts as connected at all: a plain
     * option read, no migration of the legacy td_helpdesk_options shape. Going
     * through get_td_helpdesk_settings() here would let the service hold a key
     * on an install that every other caller still reads as unconnected, and
     * would put an option write on a front-end portal render.
     */
    private static function stored_api_key(): string
    {
        $settings = get_option('td_helpdesk_settings');

        return is_array($settings) ? trim((string) ($settings['td_helpdesk_api_key'] ?? '')) : '';
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
                // Load-bearing. The API only renders its JSON 401 for a request
                // that asks for JSON; without this header an unauthenticated
                // call gets a 302 to the login page instead, wp_remote_post()
                // follows it as a GET, and the 200 HTML that comes back reads
                // here as an empty success - so a dead key went undetected and
                // the customer was told their reply had been sent. No
                // Content-Type: $data goes out form-encoded, not as JSON.
                'Accept'        => 'application/json',
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
        $instruction_ip_whitelist = 'Please try to white list these IP addresses: ' . implode(', ', thrivedesk_service_ips());

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

                // Returned for callers to branch on, though none currently do
                // beyond checking wp_error - see the shape documented on
                // postRequest(). 401 and 403 are bucketed together here because
                // both concern authorization; only 401 means the credential
                // itself was refused, which is what the flag below turns on.
                $error_type = in_array($response_code, [401, 403], true) ? 'auth' : 'server';

                // 401 only, not the whole 'auth' bucket. ThriveDesk answers 401
                // when it refuses the credential itself - a revoked or expired
                // token. A 403 is a key it accepted being turned away from one
                // endpoint: a restricted key without that capability, or a
                // scope the token was never granted. The key still works, so
                // disconnecting the site over it would take a working install
                // offline and then flap, because the /v1/me re-verification on
                // the settings screen would pass.
                //
                // Not every dead key reaches here as a 401 - a deleted
                // organization currently faults server-side and arrives as a
                // 500. Detecting that needs a fix upstream, not a wider net
                // here, which would cost working sites their connection.
                if (401 === $response_code) {
                    $this->invalidate_stored_key_verification();
                }

                return ['wp_error' => true, 'error_type' => $error_type, 'message' => 'ThriveDesk - API request failed. Response Code:' . $response_code . '. Message: ' . $api_message];
            }
        }

        error_log( 'ThriveDesk - API Request Failed. Unknown error: ' . $response_code ); // Log the error
        return ['wp_error' => true, 'error_type' => 'server', 'message' => 'ThriveDesk - Unknown API request error. Response Code:' . $response_code];
    }

    /**
     * Record that ThriveDesk refused the key on file - revoked, expired, or an
     * org that has lost API access.
     *
     * Every caller degrades locally when a request fails, so nothing else in
     * the plugin ever revisits the verified flag once the manual check set it.
     * Without this a key that stopped working keeps reading as connected
     * indefinitely, and the only trace of the failure is error_log.
     *
     * Only the key on file can lose its flag: the verify screen checks a
     * submitted key before storing it, and that one being refused says nothing
     * about the key already in use.
     *
     * @return void
     */
    private function invalidate_stored_key_verification(): void
    {
        $stored_key = self::stored_api_key();

        if ('' === $stored_key || $stored_key !== $this->api_token) {
            return;
        }

        ConnectionState::set(false);
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
