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
		return get_option('td_helpdesk_settings') ?? [];
	}
}
