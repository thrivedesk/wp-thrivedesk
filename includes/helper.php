<?php

/**
 * Render a view file
 *
 * @since 0.0.1
 * @access public
 * @param string $file view file name to render
 * @param array $data data to use on view file
 * @return void
 */
function thrivedesk_view(string $file, array $data = [])
{
    $file = THRIVEDESK_DIR . '/includes/views/' . $file . '.php';
    if (file_exists($file)) {
        if (is_array($data)) {
            extract($data);
        }

        require_once $file;
    } else {
        wp_die('View not found');
    }
}

/**
 * Thrivedesk options
 *
 * @since 0.0.1
 * @access public
 * @return void
 */
function thrivedesk_options()
{
    $options = get_option('thrivedesk_options', []);

    return is_array($options) ? $options : [];
}
