<?php
namespace ThriveDesk\Conversations;

// Exit if accessed directly.
use DOMDocument;

if (!defined('ABSPATH')) exit;
class Conversation {

	private static $instance = null;

	public static function instance(): Conversation {
		if (!isset(self::$instance) && !(self::$instance instanceof Conversation)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action('init', [$this, 'add_td_conversation_shortcode']);
		add_action('wp_enqueue_scripts', [$this, 'load_scripts']);
		add_action('wp_ajax_td_reply_conversation', [$this, 'td_send_reply']);
	}

	public function add_td_conversation_shortcode(): void {
		add_shortcode('thrivedesk_conversation', [$this, 'conversation_page']);
	}

	public function load_scripts() {
		wp_enqueue_style('thrivedesk-frontend-style', THRIVEDESK_PLUGIN_ASSETS . '/css/admin.css', '', THRIVEDESK_VERSION);

		wp_register_script( 'thrivedesk-conversation-script', THRIVEDESK_PLUGIN_ASSETS . '/js/conversation.js', array('jquery'), THRIVEDESK_VERSION);


		wp_localize_script('thrivedesk-conversation-script',
			'td_objects', array(
				'ajax_url' => admin_url('admin-ajax.php')
			));
		wp_enqueue_script('thrivedesk-conversation-script');
	}

	public function conversation_page($atts): bool|string {
		ob_start();
		if (isset($_GET['conversation_id'])) {
			include THRIVEDESK_DIR. '/includes/views/shortcode/conversation-details.php';
		} else {
			include THRIVEDESK_DIR. '/includes/views/shortcode/conversations.php';
		}
		return ob_get_clean();
	}

	public function td_send_reply() {

		if (!isset($_POST['data']['nonce']) ||
		    !isset($_POST['data']['conversation_id']) ||
		    !isset($_POST['data']['reply_text']) || !wp_verify_nonce($_POST['data']['nonce'],
				'td-reply-conversation-action')) {
			die;
		}

		$token    = get_option('td_helpdesk_settings')['td_helpdesk_api_key'];
		$url      = THRIVEDESK_API_URL . '/public/v1/conversations/'.$_POST['data']['conversation_id'].'/customer/reply';
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

	public static function get_conversation($conversation_id) {
		if (!$conversation_id) {
			return null;
		}
		$token    = get_option('td_helpdesk_settings')['td_helpdesk_api_key'];
		$url      = THRIVEDESK_API_URL . '/public/v1/conversations/'.$conversation_id;
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

	public static function validate_conversation_body($content){
		$dom = new DOMDocument('1.0', 'UTF-8');
		@$dom->loadHTML($content);
		return $dom->saveHTML();
	}

	public function getSelectedHelpdeskOptions() {
		return get_option('td_helpdesk_settings') ?? [];
	}

	public static function get_conversations() {
		$page = $_GET['cv_page'] ?? 1;
//	dd($_GET);
		$token    = get_option('td_helpdesk_settings')['td_helpdesk_api_key'];
		$url      = THRIVEDESK_API_URL . '/public/v1/conversations?page='.$page;
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
