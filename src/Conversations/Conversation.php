<?php

namespace ThriveDesk\Conversations;

// Exit if accessed directly.
use DOMDocument;
use ThriveDesk\Admin;
use ThriveDesk\Portal\UserAccountPages;
use ThriveDesk\Services\TDApiService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Conversation class
 * Conversations list and conversation body
 */
class Conversation
{

    /**
     * single instance
     *
     * @var null $instance
     */
    private static $instance = null;

    /**
     *  middle common url text
     */
    const TD_CONVERSATION_URL = '/v1/customer/conversations/';

    /**
     * singleton class
     *
     * @return Conversation
     */
    public static function instance(): Conversation
    {
        if (!isset(self::$instance) && !(self::$instance instanceof Conversation)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * class constructor
     * it will be called when class instance initialized
     */
    public function __construct()
    {
        // add shortcode for the frontend when init action called
        add_action('init', [$this, 'add_td_conversation_shortcode']);

		// ajax call for sending reply
		add_action('wp_ajax_td_reply_conversation', [$this, 'td_send_reply']);

		// ajax call for verifying the helpdesk setting
		add_action('wp_ajax_thrivedesk_api_key_verify', [$this, 'td_verify_helpdesk_api_key']);

		// ajax call for saving the helpdesk setting
		add_action('wp_ajax_thrivedesk_helpdesk_form', [$this, 'td_save_helpdesk_form']);

        add_action('wp_ajax_thrivedesk_system_info', [$this, 'thrivedesk_system_info']);

        // ajax call for reloading tickets
        add_action('wp_ajax_td_reload_tickets', [$this, 'td_reload_tickets']);
	}


    public function thrivedesk_system_info(): void
    {
        // Settings action: the 'thrivedesk-nonce' is also handed to any
        // logged-in portal visitor, so the capability check is the real guard.
        if (
            ! isset( $_POST['nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'thrivedesk-nonce' )
            || ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'thrivedesk' ) ], 403 );
        }

        $apiKey = $_POST['data']['td_helpdesk_api_key'] ?? '';

        if (empty($apiKey)) {
            error_log('ThriveDesk: API Key is required for verification');

            echo wp_json_encode(['status' => 'false', 'data' => []]);
            die();
        }

        $systemInfo = $this->get_system_info($apiKey);

        if (!empty($systemInfo)) {
            echo wp_json_encode(['status' => 'true', 'data' => $systemInfo]);
        } else {
            echo wp_json_encode(['status' => 'false', 'data' => []]);
        }
        die();
    }

    /**
     * Handle reload tickets AJAX request
     *
     * @return void
     */
    public function td_reload_tickets(): void
    {
        // Require authenticated user
        if ( ! is_user_logged_in() ) {
            wp_send_json_error(['message' => __('Unauthorized', 'thrivedesk')], 401);
        }

        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'thrivedesk-nonce')) {
            wp_send_json_error(['message' => __('Security check failed', 'thrivedesk')]);
            die();
        }

        try {
            // Only the caller's own cached list is dropped. This runs for any
            // logged-in portal user, so it must not evict other customers.
            $conversations = self::get_conversations(true, self::referred_page());

            wp_send_json_success([
                'message' => __('Tickets reloaded successfully', 'thrivedesk'),
                'data' => $conversations
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Failed to reload tickets', 'thrivedesk'),
                'error' => $e->getMessage()
            ]);
        }
        
        die();
    }

    /**
     * The page the caller is looking at. admin-ajax carries none of the portal
     * page's query string, so it comes from the URL they clicked Reload on.
     * wp_get_referer() rejects anything off-site, and an absent one leaves the
     * first page rather than a guess.
     */
    private static function referred_page(): int
    {
        $referer = wp_get_referer();

        if (!$referer) {
            return 1;
        }

        parse_str((string) wp_parse_url($referer, PHP_URL_QUERY), $query);

        return max(1, absint($query['cv_page'] ?? 1));
    }

    public static function get_system_info($apiKey): array
    {
        $apiService = new TDApiService();

        if ( empty( $apiKey ) ) {
			echo wp_json_encode( [
				'code' => 422,
				'status' => 'error',
				'data' => [
					'message' => 'API Key is required'
				]
			] );
			die();
		}

		$apiService->setApiKey( $apiKey );

        $url = THRIVEDESK_API_URL . '/v1/me';
    
        $response = $apiService->getRequest($url);

        if (isset($response['company'])) {
            $company = $response['company'];
            update_option('td_helpdesk_system_info', $company);
            // update api key status
            Admin::set_api_verification_status(true);

            return $response;
        }

        return [];
    }


	public function td_verify_helpdesk_api_key(  ): void {
        // verify the nonce
        if (
            ! isset( $_POST['nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'thrivedesk-nonce' )
            || ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'thrivedesk' ) ], 403 );
        }
		$apiKey = $_POST['data']['td_helpdesk_api_key'] ?? '';
        
		if ( empty( $apiKey ) ) {
            error_log('ThriveDesk: API Key is required for verification');

            echo wp_json_encode( [
				'code' => 422,
				'status' => 'error',
				'data' => [
					'message' => 'API Key is required'
				]
			] );
			die();
		}

        // save the api key to the database
        $this->reset_td_settings($apiKey);

		$apiService = new TDApiService();
		$apiService->setApiKey( $apiKey );

		$data = $apiService->getRequest( THRIVEDESK_API_URL . '/v1/me' );

        if ( isset( $data['wp_error'] ) && $data['wp_error'] ) {

            Admin::set_api_verification_status();

            error_log('ThriveDesk: API verification failed - ' . $data['message']);

            echo wp_json_encode( [
                'code' => 422,
                'status' => 'error',
                'data' => [
                    'message' => $data['message']
                ]
            ] );
            die();
        }

        if(!isset($data['company'])){

            Admin::set_api_verification_status();

            error_log('ThriveDesk: API verification failed - company data not found');

            echo wp_json_encode( [
				'code' => 401,
				'status' => 'error',
				'data' => [
					'message' =>  'Something went wrong: ' . $data['message']
				]
			] );

			die();
        }

        Admin::set_api_verification_status(true);

        echo wp_json_encode( [
            'code' => 200,
            'status' => 'success',
            'data' => [
                'message' => 'API Key verified successfully'
            ]
        ] );

        die();
	}

    /**
     * Update the helpdesk settings
     *
     * @return void
     */
    public function reset_td_settings($apiKey): void
    {
        if (get_option('td_helpdesk_settings')) {
            // update option to database with new api key
            $td_helpdesk_settings = get_option('td_helpdesk_settings');
            $td_helpdesk_settings['td_helpdesk_api_key'] = $apiKey;
            $td_helpdesk_settings['td_helpdesk_assistant_id'] = '';
            $td_helpdesk_settings['td_helpdesk_inbox_id'] = '';
            $td_helpdesk_settings['td_knowledgebase_slug'] = '';

            update_option('td_helpdesk_settings', $td_helpdesk_settings);
            update_option('td_helpdesk_system_info', []);
        } else {
            add_option('td_helpdesk_settings', [
                'td_helpdesk_api_key' => $apiKey
            ]);
        }
    }

    public function td_save_helpdesk_form()
    {
        header('Content-Type: application/json');
        
        if (
            ! isset($_POST['nonce'])
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'thrivedesk-nonce' )
            || ! current_user_can('manage_options')
        ) {
            error_log('ThriveDesk: Unauthorized access attempt to helpdesk form');
            echo wp_json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            die();
        }
        
        // Process data properly - handle arrays and strings separately
        $raw_data = isset($_POST['data']) ? wp_unslash($_POST['data']) : [];
        $data = [];
        
        foreach ($raw_data as $key => $value) {
            if (is_array($value)) {
                // For arrays (like checkboxes), sanitize each element
                $data[$key] = array_map('sanitize_text_field', array_values($value));

            } else {
                // For single values, sanitize directly
                $data[$key] = sanitize_text_field($value);
            }
        }

        if (isset($data['td_helpdesk_api_key'])) {
            // add option to database
            $td_helpdesk_settings = [
                'td_helpdesk_api_key'                   => trim($data['td_helpdesk_api_key']),
                'td_helpdesk_assistant_id'              => $data['td_helpdesk_assistant'] ?? '',
                'td_helpdesk_inbox_id'                  => $data['td_helpdesk_inbox_id'] ?? '',
                'td_helpdesk_page_id'                   => $data['td_helpdesk_page_id'] ?? '',
                'td_knowledgebase_slug'                 => $data['td_knowledgebase_slug'] ?? '',
                'td_helpdesk_post_types'                => $data['td_helpdesk_post_types'] ?? [],
                'td_helpdesk_post_sync'                 => $data['td_helpdesk_post_sync'] ?? [],
                'td_user_account_pages'                 => $data['td_user_account_pages'] ?? [],
                'td_assistant_route_list'               => $data['td_assistant_route_list'] ?? [],
            ];
            
            $existing_settings = get_option('td_helpdesk_settings');

            if ($existing_settings) {
                update_option('td_helpdesk_settings', $td_helpdesk_settings);
            } else {
                add_option('td_helpdesk_settings', $td_helpdesk_settings);
            }

            // /my-account/td-support/ 404s until rewrite rules are refreshed. Tell
            // UserAccountPages to flush on the next init if the WC tab toggle changed.
            $old_pages = is_array($existing_settings) ? (array) ($existing_settings['td_user_account_pages'] ?? []) : [];
            UserAccountPages::instance()->maybe_queue_rewrite_flush(
                $old_pages,
                (array) $td_helpdesk_settings['td_user_account_pages']
            );
            
            // Clear all caches to ensure fresh data
            if (function_exists('remove_thrivedesk_all_cache')) {
                remove_thrivedesk_all_cache();
            }
            
            // Clear WordPress options cache for this specific option
            wp_cache_delete('td_helpdesk_settings', 'options');
            
            echo wp_json_encode(['status' => 'success', 'message' => 'Settings saved successfully']);
            die();
        }

        echo wp_json_encode(['status' => 'error', 'message' => 'Something went wrong']);
        die();
    }

    /**
     * add shortcode for the conversation
     *
     * @return void
     */
    public function add_td_conversation_shortcode(): void
    {
        add_shortcode('thrivedesk_portal', [$this, 'conversation_page']);
    }

    public function getKnowledgeBaseUrl(){
        $options = get_td_helpdesk_settings();
        $knowledgebaseSlug = $options['td_knowledgebase_slug'] ?? null;
        $url = null;

        if ($knowledgebaseSlug != '') {
            $kbApiEndpoint = parse_url(THRIVEDESK_KB_API_ENDPOINT);
            $url = $kbApiEndpoint['scheme'] . '://' . $knowledgebaseSlug . '.' . $kbApiEndpoint['host'];
        }

        return $url;
    }



    /**
     * Load scripts and styles for the conversation shortcode
     *
     * @return void
     */
    public function load_scripts(): void
    {
        $css_version = thrivedesk_get_asset_version('/css/thrivedesk.css');
        $js_version = thrivedesk_get_asset_version('/js/conversation.js');
        
        wp_enqueue_style('thrivedesk', THRIVEDESK_PLUGIN_ASSETS . '/css/thrivedesk.css', '', $css_version);

        wp_register_script('thrivedesk-conversations', THRIVEDESK_PLUGIN_ASSETS . '/js/conversation.js', ['jquery', 'wp-i18n'], $js_version);
        wp_set_script_translations('thrivedesk-conversations', 'thrivedesk');


        // Toggle the local WP docs search. off unless the admin picked at
        // least one post type, otherwise the REST handler would just
        // hand back an empty list anyway.
        $wp_search_post_types = get_option('td_helpdesk_settings')['td_helpdesk_post_types'] ?? [];
        $wp_search_enabled    = ! empty($wp_search_post_types);

        // ?rest_route= form works regardless of permalink settings.
        // /wp-json/... only works when pretty permalinks are on; with
        // the default plain setting that path 404s even though the
        // REST API itself is fine.
        $wp_rest_url = site_url( '/?rest_route=' );

        wp_localize_script('thrivedesk-conversations',
            'td_objects', [
                'wp_json_url'        => $wp_rest_url,
                'ajax_url'           => admin_url('admin-ajax.php'),
                'kb_url'             => $this->getKnowledgeBaseUrl(),
                'nonce'              => wp_create_nonce('thrivedesk-nonce'),
                'wp_search_enabled'  => $wp_search_enabled,
            ]
        );
        wp_enqueue_script('thrivedesk-conversations');
    }

    

	/**
	 * redirect to the conversation page
	 * if conversation id then redirect to the conversation details page
	 *
	 */
    public function conversation_page($atts, $content = null)
    {
        $this->load_scripts();
    
        $url_parts = add_query_arg(NULL, NULL);
        $parts = parse_url($url_parts, PHP_URL_QUERY);
    
        // Initialize query_params as an empty array
        $query_params = [];
        
        if ($parts !== null) {
            parse_str($parts, $query_params);
        }
    
        if (is_user_logged_in()) {
            ob_start();
            if (isset($query_params['td_conversation_id'])) {
                thrivedesk_view('shortcode/conversation-details');
            } else {
                thrivedesk_view('shortcode/conversations');
            }
    
            return ob_get_clean();
        }
        global $wp;
        $redirect = home_url($wp->request);
    
        return '<p>' . __('You must be logged in to view the ticket or conversation', 'thrivedesk') . 
            '. Click <a class="text-blue-600" href="' . esc_url(wp_login_url($redirect)) . '">here</a> to login.</p>';
    }
    


    /**
     * validate html body of the conversation
     * it will help to remove style breaking issue on event body
     *
     * @return false|string
     */
    public static function validate_conversation_body($content)
    {
		return $content;

        /*$dom = new DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML($content);

        return $dom->saveHTML();*/
    }

    public static function delete_thrivedesk_expired_transients(){
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
                WHERE a.option_name LIKE %s
                AND a.option_name NOT LIKE %s
                AND b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) )
                AND b.option_value < %d",
                $wpdb->esc_like( '_transient_thrivedesk_' ) . '%',
                $wpdb->esc_like( '_transient_timeout_' ) . '%',
                time()
            )
        );
    }

	/**
	 * get all conversations
	 *
	 * @param bool     $force_refresh Drop this caller's cached page before fetching.
	 * @param int|null $page          Page to fetch. Defaults to the one in the URL.
	 *
	 * @return mixed|null
	 */
	public static function get_conversations(bool $force_refresh = false, ?int $page = null)
	{
        self::delete_thrivedesk_expired_transients();
		$page               = max(1, $page ?? absint($_GET['cv_page'] ?? 1));
		$current_user_email = wp_get_current_user()->user_email;
		$inbox_id           = get_option('td_helpdesk_settings')['td_helpdesk_inbox_id'] ?? '';

		// get data from cache - include inbox_id in cache key for proper filtering
		$cache_key = 'thrivedesk_conversations_' . $page . '_' . $current_user_email . '_' . $inbox_id;

		if ($force_refresh) {
			delete_transient($cache_key);
		}

		$data = get_transient($cache_key);

		if (!$data) {
			$query = [
				'customer_email' => $current_user_email,
				'page'           => $page,
				'per-page'       => 15,
			];

			// Add inbox filtering if inbox is selected
			if (!empty($inbox_id)) {
				$query['inbox_id'] = $inbox_id;
			}

			// http_build_query encodes every value, so nothing carried in on the
			// request can close one parameter and open a customer_email of its own.
			$url = THRIVEDESK_API_URL . self::TD_CONVERSATION_URL . '?' . http_build_query($query);

			$response =( new TDApiService() )->getRequest($url);

			if (isset($response['data']) && count($response['data']) > 0){
				$data = $response;
				// 30s TTL: same rationale as get_conversation(), ThriveDesk
				// doesn't notify WP on agent activity, so the cache exists only
				// to absorb rapid reloads, not to "hide" updates.
				set_transient($cache_key, $response, 30);
			}
		}

        return $data ?? [];
	}

	/**
	 * A conversation id lands in the path of an authenticated API call, so a
	 * value carrying a slash or a query separator would repoint that call.
	 * Anything outside the id alphabet is rejected rather than escaped.
	 *
	 * @param mixed $raw
	 *
	 * @return string Empty when the id is unusable.
	 */
	public static function sanitize_conversation_id($raw): string
	{
		$id = is_scalar($raw) ? (string) $raw : '';

		return preg_match('/^[A-Za-z0-9-]{1,64}$/', $id) ? $id : '';
	}

	/**
	 * Nothing here decides who may read a conversation - the API does, from the
	 * customer_email on the request. A cache hit returns before that request is
	 * made, so the reader belongs in the key just as it does for the list
	 * above; without it the next reader is served whatever the last one was
	 * allowed to see.
	 */
	private static function conversation_cache_key(string $conversation_id): string
	{
		return 'thrivedesk_conversation_' . $conversation_id . '_' . wp_get_current_user()->user_email;
	}

	/**
	 * get single conversation
	 *
	 * @param $conversation_id
	 *
	 * @return mixed|null
	 */
	public static function get_conversation($conversation_id)
	{
		$conversation_id = self::sanitize_conversation_id($conversation_id);

		if ('' === $conversation_id) {
			return null;
		}

		$cache_key = self::conversation_cache_key($conversation_id);
		$response  = get_transient($cache_key);

		if (!$response) {
			$current_user_email = wp_get_current_user()->user_email;
			$url      = THRIVEDESK_API_URL . self::TD_CONVERSATION_URL . $conversation_id .'?customer_email=' . rawurlencode($current_user_email);
			$response =( new TDApiService() )->getRequest($url);

			// 30s TTL: the cache exists only to absorb rapid page reloads on
			// the same conversation. A longer window hides agent replies
			// (ThriveDesk doesn't notify WP when an agent sends a message,
			// and the only explicit invalidation is the customer's own reply
			// in td_send_reply).
			if (isset($response['data'])) {
				set_transient($cache_key, $response, 30);
			} elseif (is_array($response) && !isset($response['wp_error'])) {
				// If API returns data directly (not wrapped in 'data' key)
				set_transient($cache_key, $response, 30);
			}
		}

		// Handle different response structures
		if (isset($response['wp_error'])) {
			// Return error response for proper error handling
			return $response;
		} elseif (isset($response['data'])) {
			return $response['data'];
		} elseif (is_array($response)) {
			return $response;
		}
		
		return [];
	}

    /**
     * send reply to the conversation
     * by ajax call
     *
     * @return void
     */
    public function td_send_reply()
    {
        if (!isset($_POST['data']['nonce'])
            || !isset($_POST['data']['conversation_id'])
            || !isset($_POST['data']['reply_text'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['data']['nonce'])), 'td-reply-conversation-action')) {
            wp_die();
        }

        $conversation_id = self::sanitize_conversation_id($_POST['data']['conversation_id']);

        if ('' === $conversation_id) {
            wp_die();
        }

		$current_user_email = wp_get_current_user()->user_email;

        $url      = THRIVEDESK_API_URL . self::TD_CONVERSATION_URL . $conversation_id . '/reply?customer_email=' . rawurlencode($current_user_email);

        $data = [
            'message' => stripslashes($_POST['data']['reply_text']),
        ];

        header('Content-Type: application/json');

        try {
            $response_body =( new TDApiService() )->postRequest($url, $data);

            // The reply invalidates this customer's view of this conversation
            // and nothing else. Anyone else's cached copy is theirs to keep.
            delete_transient(self::conversation_cache_key($conversation_id));

            echo wp_json_encode([
                'status'  => 'success',
                'message' => $response_body['message'],
            ]);
        }catch (\Exception $e) {
            echo wp_json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        wp_die();
    }

    public static function td_conversation_sort_by_status($data)
    {
        usort($data, function($first, $second) {
            // sort by status as active, pending, closed
            $status = [
                'Active'  => 1,
                'Pending' => 2,
                'Closed'  => 3,
            ];

            $first = $status[$first['status']];
            $second = $status[$second['status']];
            if ($first == $second) {
                return 0;
            }
            return ($first < $second) ? -1 : 1;
        });

        return $data;
    }
}
