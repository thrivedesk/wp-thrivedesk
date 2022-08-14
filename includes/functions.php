<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

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
//    dd($providers);
    return $providers;
}

function getSelectedTdSettings() {
    return get_option('td_helpdesk_settings') ?? null;
}
