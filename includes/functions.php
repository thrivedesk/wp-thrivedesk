<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;


function load_scripts() {

    wp_enqueue_style('thrivedesk-frontend-style', THRIVEDESK_PLUGIN_ASSETS . '/css/admin.css', '', THRIVEDESK_VERSION);

	wp_register_script( 'thrivedesk-conversation-script', THRIVEDESK_PLUGIN_ASSETS . '/js/conversation.js', array('jquery'), THRIVEDESK_VERSION);


	wp_localize_script('thrivedesk-conversation-script', 'TdObjects', array(
		'helpdesk_api_url' => THRIVEDESK_API_URL,
		'token' => getSelectedHelpdeskOptions()['td_helpdesk_api_key'],
		'home_url' => home_url(),
		'conversation_id'  => $_GET['conversation_id'] ?? '',
	));
	wp_enqueue_script('thrivedesk-conversation-script');
}

add_action('wp_enqueue_scripts', 'load_scripts');


/**
 * @return array
 */
function getFormProviders(): array
{
    // store the activated form providers
    $providers = [];

    if (\ThriveDesk\FormProviders\FormProviderHelper::is_fluent_form_active()) {
        $providers['fluent-form'] = 'Fluent Form';
    }
    if (\ThriveDesk\FormProviders\FormProviderHelper::is_contact_form_plugin_active()) {
        $providers['contact-form-7'] = 'Contact Form 7';
    }

    if (\ThriveDesk\FormProviders\FormProviderHelper::is_wp_form_plugin_active()) {
        $providers['wp-forms'] = 'WPForms';
    }

    if (\ThriveDesk\FormProviders\FormProviderHelper::is_ninja_form_plugin_active()) {
        $providers['ninja-form'] = 'Ninja Forms';
    }

    if (\ThriveDesk\FormProviders\FormProviderHelper::is_forminator_form_plugin_active()) {
        $providers['forminator-form'] = 'Forminator Forms';
    }

    if (\ThriveDesk\FormProviders\FormProviderHelper::is_formidable_form_plugin_active()) {
        $providers['formidable-form'] = 'Formidable Forms';
    }
	return $providers;
}


function getSelectedHelpdeskOptions() {
    return get_option('td_helpdesk_settings') ?? [];
}

function conversation_page($atts) {
    ob_start();
	if (isset($_GET['conversation_id'])) {
		include THRIVEDESK_DIR. '/includes/views/shortcode/conversation-details.php';
	} else {
		include THRIVEDESK_DIR. '/includes/views/shortcode/conversations.php';
	}
    return ob_get_clean();
}
// add shortcode
add_shortcode('thrivedesk_conversation', 'conversation_page');




/**
 * Conversation api
 */

/**
 * conversation list
 * @return mixed
 */
function get_conversations() {
	if (!isset($_GET['page'])) {
		$page = 1;
	} else {
		$page = $_GET['page'];
	}
//	dd($_GET);
	$token    = getSelectedHelpdeskOptions()['td_helpdesk_api_key'];
	$url      = THRIVEDESK_API_URL . '/public/v1/conversations?page='.$page;
	$args     = array(
		'headers' => array(
			'Authorization' => 'Bearer ' . $token,
		),
	);
	$response = wp_remote_get( $url, $args );
	$body     = wp_remote_retrieve_body( $response );
	$body     = json_decode( $body, true );
	return $body;
}

function get_conversation($conversation_id) {
	if (!$conversation_id) {
		return null;
	}
	$token    = getSelectedHelpdeskOptions()['td_helpdesk_api_key'];
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
