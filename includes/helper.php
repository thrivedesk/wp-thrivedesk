<?php

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

function thrivedesk_options()
{
    return get_option('thrivedesk_options', []);
}
