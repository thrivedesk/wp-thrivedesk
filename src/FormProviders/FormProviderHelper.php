<?php

namespace ThriveDesk\FormProviders;

class FormProviderHelper {

    /**
     * Fluent Form
     * @return bool
     */
    public static function is_fluent_form_active(): bool
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        if (is_plugin_active('fluentform/fluentform.php')) {
            return true;
        }

        return false;
    }

    /**
     * Contact Form 7
     * @return bool
     */
    public static function is_contact_form_plugin_active(): bool
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        if (is_plugin_active('contact-form-7/wp-contact-form-7.php')) {
            return true;
        }

        return false;
    }

    /**
     * WP FORM
     * @return bool
     */
    public static function is_wp_form_plugin_active(): bool
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        if (is_plugin_active('wpforms-lite/wpforms.php')) {
            return true;
        }

        return false;
    }

    /**
     * Forminator Form
     * @return bool
     */
    public static function is_forminator_form_plugin_active(): bool
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        if (is_plugin_active('forminator/forminator.php')) {
            return true;
        }

        return false;
    }

    public static function is_ninja_form_plugin_active(): bool
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        if (is_plugin_active('ninja-forms/ninja-forms.php')) {
            return true;
        }

        return false;
    }

}