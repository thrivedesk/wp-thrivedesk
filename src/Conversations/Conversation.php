<?php

namespace ThriveDesk\Conversations;

// Exit if accessed directly.
use DOMDocument;
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
	}

	public function td_verify_helpdesk_api_key(  ): void {
		$apiKey = $_POST['data']['td_helpdesk_api_key'] ?? '';
		if ( empty( $apiKey ) ) {
			echo json_encode( [
				'code' => 422,
				'status' => 'error',
				'data' => [
					'message' => 'API Key is required'
				]
			] );
			die();
		}

		$data = get_transient( 'thrivedesk_me' );
		if ( $data ) {
			echo json_encode( [ 'status' => 'true', 'data' => $data ] );
			die();
		}

		$apiService = new TDApiService();
		$apiService->setApiKey( $apiKey );
		$data = $apiService->getRequest( THRIVEDESK_API_URL . '/v1/me' );

		if ( isset( $data['wp_error'] ) && $data['wp_error'] ) {
			echo json_encode( [
				'code' => 422,
				'status' => 'error',
				'data' => [
					'message' => $data['message']
				]
			] );
			die();
		}

		if ( $data ) {
			set_transient( 'thrivedesk_me', $data, 60 * 60 * 6 );
			echo json_encode( [ 'status' => 'true', 'data' => $data ] );
			die();
		}
	}

    public function td_save_helpdesk_form()
    {
        header('Content-Type: application/json');
        $data = $_POST['data'];
        if (isset($data['td_helpdesk_api_key'])) {
            // add option to database
            $td_helpdesk_settings = [
                'td_helpdesk_api_key'                   => trim($data['td_helpdesk_api_key']),
                'td_helpdesk_assistant_id'              => $data['td_helpdesk_assistant'],
                'td_helpdesk_page_id'                   => $data['td_helpdesk_page_id'],
                'td_helpdesk_post_types'                => $data['td_helpdesk_post_types'],
                'td_helpdesk_post_sync'                 => $data['td_helpdesk_post_sync'],
            ];

            if (get_option('td_helpdesk_settings')) {
                update_option('td_helpdesk_settings', $td_helpdesk_settings);
            } else {
                add_option('td_helpdesk_settings', $td_helpdesk_settings);
            }
            echo json_encode(['status' => 'success', 'message' => 'Settings saved successfully']);
            die();
        }
        echo json_encode(['status' => 'error', 'message' => 'Something went wrong']);
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

    /**
     * load the necessary scripts
     * style and script
     *
     * @return void
     */
    public function load_scripts(): void
    {
        wp_enqueue_style('thrivedesk', THRIVEDESK_PLUGIN_ASSETS . '/css/thrivedesk.css', '', THRIVEDESK_VERSION);

        wp_register_script('thrivedesk-conversations', THRIVEDESK_PLUGIN_ASSETS . '/js/conversation.js', ['jquery'], THRIVEDESK_VERSION);


        wp_localize_script('thrivedesk-conversations',
            'td_objects', [
                'wp_json_url' => site_url('wp-json'),
                'ajax_url'    => admin_url('admin-ajax.php'),
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

		if (is_user_logged_in() && !is_null($content) && !is_feed()) {
			ob_start();
			if (isset($_GET['td_conversation_id']) && !empty($_GET['td_conversation_id'])) {
				thrivedesk_view('shortcode/conversation-details');
			} else {
				thrivedesk_view('shortcode/conversations');
			}

            return ob_get_clean();
        }
        global $wp;
        $redirect = home_url($wp->request);

        return '<p>' . __('You must be logged in to view the ticket or conversation',
                'thrivedesk') . '. Click <a class="text-blue-600" href="' . esc_url(wp_login_url
            ($redirect)) . '"> here</a> to login.
			</p>';
    }


    /**
     * validate html body of the conversation
     * it will help to remove style breaking issue on event body
     *
     * @return false|string
     */
    public static function validate_conversation_body($content)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML($content);

        return $dom->saveHTML();
    }


	/**
	 * get all conversations
	 *
	 * @return mixed|null
	 */
	public static function get_conversations()
	{
		$page               = $_GET['cv_page'] ?? 1;
		$current_user_email = wp_get_current_user()->user_email;
		// get data from cache
		$cache_key = 'thrivedesk_conversations_' . $page;
		$data = get_transient($cache_key);


		if (!$data) {
			$url = THRIVEDESK_API_URL . self::TD_CONVERSATION_URL . '?customer_email=' . $current_user_email . '&page=' . $page . '&per-page=15';

			$response =( new TDApiService() )->getRequest($url);

			if ($response['data'] && count($response['data']) > 0){
				$data = $response;
				set_transient($cache_key, $response, 60 * 10);
				set_transient('thrivedesk_conversations_total_pages', $response['meta']['last_page'], 60 * 10);
			}
		}

		return $data ?? [];
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
		if (!$conversation_id) {
			return null;
		}

		$response = get_transient('thrivedesk_conversation_' . $conversation_id);

		if (!$response) {
			$current_user_email = wp_get_current_user()->user_email;
			$url      = THRIVEDESK_API_URL . self::TD_CONVERSATION_URL . $conversation_id .'?customer_email=' . $current_user_email;
			$response =( new TDApiService() )->getRequest($url);

			if ($response['data'] && count($response['data']) > 0) {
				set_transient('thrivedesk_conversation_' . $conversation_id, $response, 60 * 10);
			}
		}

		return $response['data'] ?? [];
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
            || !wp_verify_nonce($_POST['data']['nonce'], 'td-reply-conversation-action')) {
            die;
        }

		$current_user_email = wp_get_current_user()->user_email;

        $url      = THRIVEDESK_API_URL . self::TD_CONVERSATION_URL . $_POST['data']['conversation_id'] . '/reply?customer_email=' . $current_user_email;

        $data = [
            'message' => stripslashes($_POST['data']['reply_text']),
        ];

        header('Content-Type: application/json');

        try {
            $response_body =( new TDApiService() )->postRequest($url, $data);

	        remove_thrivedesk_conversation_cache();

            echo json_encode([
                'status'  => 'success',
                'message' => $response_body['message'],
            ]);
        }catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        die;
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
