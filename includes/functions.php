<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;


function load_scripts() {
    wp_enqueue_style('thrivedesk-frontend-style', THRIVEDESK_PLUGIN_ASSETS . '/css/admin.css', '', THRIVEDESK_VERSION);

    wp_enqueue_script('thrivedesk-frontend-script', THRIVEDESK_PLUGIN_ASSETS . '/js/conversation.js', ['jquery'],
	    THRIVEDESK_VERSION);
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

//    dd($providers);
    return $providers;
}

function getSelectedTdSettings() {
    return get_option('td_helpdesk_settings') ?? null;
}


function conversation_page($atts) {
    ob_start();
	if (isset($_GET['conversation_id']) && $_GET['conversation_id']) {
		include THRIVEDESK_DIR. '/includes/views/shortcode/conversation-details.php';
	} else {
		include THRIVEDESK_DIR. '/includes/views/shortcode/conversations.php';
	}
    return ob_get_clean();
}
// add shortcode
add_shortcode('thrivedesk_conversation', 'conversation_page');
