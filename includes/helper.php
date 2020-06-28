<?php

function tdesk_view(string $file, array $data = [])
{
    $file = TDESK_DIR . '/includes/views/' . $file . '.php';
    if (file_exists($file)) {
        if (is_array($data)) {
            extract($data);
        }

        require_once $file;
    } else {
        wp_die('View not found');
    }
}