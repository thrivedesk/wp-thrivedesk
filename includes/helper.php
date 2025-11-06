<?php

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

		$weeks = floor($diff->d / 7);
		$days = $diff->d - ($weeks * 7);

		$periods = array(
			'y' => ['year', 'years', $diff->y],
			'm' => ['month', 'months', $diff->m],
			'w' => ['week', 'weeks', $weeks],
			'd' => ['day', 'days', $days],
			'h' => ['hour', 'hours', $diff->h],
			'i' => ['minute', 'minutes', $diff->i],
			's' => ['second', 'seconds', $diff->s]
		);

		$parts = array();
		foreach ($periods as $k => $v) {
			$value = $v[2];
			if ($value) {
				$parts[] = $value . ' ' . $v[$value > 1 ? 1 : 0];
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
		$wpdb->query(
			"DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_thrivedesk_%' 
                          OR option_name LIKE '_transient_timeout_thrivedesk_%'");
	}
}

if (!function_exists('remove_thrivedesk_conversation_cache')) {
	function remove_thrivedesk_conversation_cache() {
		global $wpdb;
		$wpdb->query(
			"DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_thrivedesk_conversation%' ");
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