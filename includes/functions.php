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
        $providers[] = 'Fluent Form';
    }
    if (\ThriveDesk\FormProviders\FormProviderHelper::is_contact_form_plugin_active()) {
        $providers[] = 'Contact Form 7';
    }

    if (\ThriveDesk\FormProviders\FormProviderHelper::is_wp_form_plugin_active()) {
        $providers[] = 'WPForms';
    }

    if (\ThriveDesk\FormProviders\FormProviderHelper::is_ninja_form_plugin_active()) {
        $providers[] = 'Ninja Forms';
    }
    return $providers;
}
