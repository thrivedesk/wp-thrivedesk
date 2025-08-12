<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (!function_exists('thrivedesk_view')) {
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
}

if (!function_exists('thrivedesk_options')) {
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
}

if (!function_exists('diff_for_humans')) {
	/**
	 * format timestamp for the conversation
	 * @throws \Exception
	 */
	function diff_for_humans($datetime, $full = false): string {
		$now = new DateTime;
		$ago = new DateTime($datetime);
		$diff = $now->diff($ago);

		$diff->w = floor($diff->d / 7);
		$diff->d -= $diff->w * 7;

		$periods = array(
			'y' => ['year', 'years'],
			'm' => ['month', 'months'],
			'w' => ['week', 'weeks'],
			'd' => ['day', 'days'],
			'h' => ['hour', 'hours'],
			'i' => ['minute', 'minutes'],
			's' => ['second', 'seconds']
		);

		$parts = array();
		foreach ($periods as $k => &$v) {
			if ($diff->$k) {
				$parts[] = $diff->$k . ' ' . $v[$diff->$k > 1];
			}
		}

		if (!$full) $parts = array_slice($parts, 0, 1);
		return $parts ? implode(', ', $parts) . ' ago' : 'just now';
	}
}

/**
 * helpdesk options
 */
if (!function_exists('get_td_helpdesk_options')) {
	function get_td_helpdesk_options() {
		return get_option('td_helpdesk_settings', []);
	}
}

if (!function_exists('remove_thrivedesk_cache_by_key')) {
	function remove_thrivedesk_cache_by_key(string $key) {
		delete_transient($key);
	}
}

if (!function_exists('remove_thrivedesk_all_cache')) {
	function remove_thrivedesk_all_cache() {
		global $wpdb;
		$wpdb->query($wpdb->prepare(
			"DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
			'_transient_thrivedesk_%', '_transient_timeout_thrivedesk_%'));
	}
}

if (!function_exists('remove_thrivedesk_conversation_cache')) {
	function remove_thrivedesk_conversation_cache() {
		global $wpdb;
		$wpdb->query($wpdb->prepare(
			"DELETE FROM $wpdb->options WHERE option_name LIKE %s", 
			'_transient_thrivedesk_conversation%'));
	}
}

/*
 * Clear cache from ajax call
 */
add_action('wp_ajax_thrivedesk_clear_cache', function () {
	remove_thrivedesk_all_cache();
	remove_thrivedesk_conversation_cache();
	wp_send_json_success();
});

/*
 * Make a gravatar url from the current user email
 */
if (!function_exists('get_gravatar_url')) {
	function get_gravatar_url($email, $size = 80): string {
		$hash = md5(strtolower(trim($email)));
		return "https://www.gravatar.com/avatar/$hash?s=$size";
	}
}