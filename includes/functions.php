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

    if (\ThriveDesk\Plugins\FluentCRM::is_plugin_active()) {
        $providers[] = 'Fluent Form';
    }
    if (is_contact_form_plugin_active()) {
        $providers[] = 'Contact Form 7';
    }
    return $providers;
}

function is_contact_form_plugin_active(): bool
{
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    if (is_plugin_active('contact-form-7/wp-contact-form-7.php')) {
        return true;
    }

    return false;
}