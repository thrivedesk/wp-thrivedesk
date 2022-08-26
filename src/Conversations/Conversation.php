<?php
namespace ThriveDesk\Conversations;

// Exit if accessed directly.
use DateTime;
use DOMDocument;

if (!defined('ABSPATH')) exit;

/**
 * Conversation class
 * Conversations list and conversation body
 */
class Conversation {

	/**
	 * single instance
	 * @var null $instance
	 */
	private static $instance = null;

	/**
	 *  middle common url text
	 */
	const TD_CONVERSATION_URL = '/public/v1/customer/conversations/';

	/**
	 * singleton class
	 * @return \ThriveDesk\Conversations\Conversation
	 */
	public static function instance(): Conversation {
		if (!isset(self::$instance) && !(self::$instance instanceof Conversation)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * class constructor
	 * it will be called when class instance initialized
	 */
	public function __construct() {
		// add shortcode for the frontend when init action called
		add_action('init', [$this, 'add_td_conversation_shortcode']);

		// load the necessary scripts
		add_action('wp_enqueue_scripts', [$this, 'load_scripts']);

		// ajax call for sending reply
		add_action('wp_ajax_td_reply_conversation', [$this, 'td_send_reply']);
	}

	/**
	 * add shortcode for the conversation
	 * @return void
	 */
	public function add_td_conversation_shortcode(): void {
		add_shortcode('thrivedesk_portal', [$this, 'conversation_page']);
	}

	/**
	 * load the necessary scripts
	 * style and script
	 * @return void
	 */
	public function load_scripts(): void {
		wp_enqueue_style('thrivedesk-frontend-style', THRIVEDESK_PLUGIN_ASSETS . '/css/admin.css', '', THRIVEDESK_VERSION);

		wp_register_script( 'thrivedesk-conversation-script', THRIVEDESK_PLUGIN_ASSETS . '/js/conversation.js', array('jquery'), THRIVEDESK_VERSION);


		wp_localize_script('thrivedesk-conversation-script',
			'td_objects', array(
				'wp_json_url' => site_url('wp-json'),
				'ajax_url' => admin_url('admin-ajax.php')
			));
		wp_enqueue_script('thrivedesk-conversation-script');
	}

	/**
	 * redirect to the conversation page
	 * if conversation id then redirect to the conversation details page
	 *
	 */
	public function conversation_page($atts, $content = null) {
		if (is_user_logged_in() && !is_null( $content ) && !is_feed()) {
			ob_start();
			if (isset($_GET['conversation_id'])) {
				thrivedesk_view('shortcode/conversation-details');
			} else {
				thrivedesk_view('shortcode/conversations');
			}
			return ob_get_clean();
		}
		global $wp;
        $redirect = home_url($wp->request);
		return '<p>'. __('You must be logged in to view the ticket or conversation', 'thrivedesk'). '. Click <a class="text-blue-600" href="'. esc_url(wp_login_url
			($redirect)) .'"> here</a> to login.
			</p>';
	}

	/**
	 * send reply to the conversation
	 * by ajax call
	 * @return void
	 */
	public function td_send_reply() {

		if (!isset($_POST['data']['nonce']) ||
		    !isset($_POST['data']['conversation_id']) ||
		    !isset($_POST['data']['reply_text']) || !wp_verify_nonce($_POST['data']['nonce'],
				'td-reply-conversation-action')) {
			die;
		}

		$token    = get_option('td_helpdesk_settings')['td_helpdesk_api_key'];
		$url      = THRIVEDESK_API_URL . self::TD_CONVERSATION_URL .$_POST['data']['conversation_id'].'/reply';
		$args     = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
			),
			'body' => array(
				'message'   => $_POST['data']['reply_text']
			),
		);
		$response = wp_remote_post( $url, $args );
		header('Content-Type: application/json');
		if (!is_wp_error($response)) {
			echo json_encode([
				'status' => 'success',
				'message' => 'Reply sent successfully'
			]);
		} else {
			echo json_encode([
				'status' => 'error',
				'message' => 'Reply could not send successfully'
			]);
		}
		die;
	}

	/**
	 * get single conversation
	 * @param $conversation_id
	 *
	 * @return mixed|null
	 */
	public static function get_conversation($conversation_id): mixed {
		if (!$conversation_id) {
			return null;
		}
		$token    = get_option('td_helpdesk_settings')['td_helpdesk_api_key'];
		$url      = THRIVEDESK_API_URL . self::TD_CONVERSATION_URL .$conversation_id;
		$args     = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
			),
		);
		$response = wp_remote_get( $url, $args );
		$body     = wp_remote_retrieve_body( $response );
		$body     = json_decode( $body, true );
		return $body['data'];
	}

	/**
	 * validate html body of the conversation
	 * it will help to remove style breaking issue on event body
	 * @return false|string
	 */
	public static function validate_conversation_body($content){
		$dom = new DOMDocument('1.0', 'UTF-8');
		@$dom->loadHTML($content);
		return $dom->saveHTML();
	}

	/**
	 * get all conversations
	 * @return mixed|null
	 */
	public static function get_conversations() {
		$page = $_GET['cv_page'] ?? 1;
		$current_user_email = wp_get_current_user()->user_email;
		$token    = get_option('td_helpdesk_settings')['td_helpdesk_api_key'];
		$state = hash_hmac('SHA1', $current_user_email, $token);
		$url      = THRIVEDESK_API_URL . self::TD_CONVERSATION_URL .'?customer_email='. $current_user_email .'&state='. $state .'&page='.$page.'&per-page=10';
		$args     = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
			),
		);
		$response = wp_remote_get( $url, $args );
		$body     = wp_remote_retrieve_body( $response );
		$body     = json_decode( $body, true );
		return $body ?? [];
	}
}
