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
		if (empty($datetime)) {
			return __('Unknown time', 'thrivedesk');
		}
		
		$now = new DateTime;
		$ago = new DateTime($datetime);
		$diff = $now->diff($ago);

		// Calculate weeks manually without creating dynamic property
		$weeks = floor($diff->d / 7);
		$days = $diff->d - ($weeks * 7);

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
		$values = array(
			'y' => $diff->y,
			'm' => $diff->m,
			'w' => $weeks,
			'd' => $days,
			'h' => $diff->h,
			'i' => $diff->i,
			's' => $diff->s
		);
		
		foreach ($periods as $k => &$v) {
			if ($values[$k]) {
				$parts[] = $values[$k] . ' ' . $v[$values[$k] > 1];
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
    function get_td_helpdesk_options()
    {
        $options = get_option('td_helpdesk_options', []);

        return is_array($options) ? $options : [];
    }
}

/**
 * Get asset version from mix-manifest.json
 *
 * @param string $file_path
 * @return string
 */
if (!function_exists('thrivedesk_get_asset_version')) {
    function thrivedesk_get_asset_version($file_path)
    {
        $manifest_path = THRIVEDESK_PLUGIN_ASSETS_PATH . '/mix-manifest.json';
        
        if (file_exists($manifest_path)) {
            $manifest = json_decode(file_get_contents($manifest_path), true);
            if ($manifest && isset($manifest[$file_path])) {
                // Extract version hash from the manifest
                $manifest_value = $manifest[$file_path];
                
                // Parse the query string to get just the id parameter
                $parsed_url = parse_url($manifest_value);
                if (isset($parsed_url['query'])) {
                    parse_str($parsed_url['query'], $query_params);
                    if (isset($query_params['id'])) {
                        return $query_params['id'];
                    }
                }
                
                // Fallback: if no id parameter, return the full query string
                if (isset($parsed_url['query'])) {
                    return $parsed_url['query'];
                }
            }
        }
        
        // Fallback to plugin version if manifest not found
        return defined('THRIVEDESK_VERSION') ? THRIVEDESK_VERSION : '1.0.0';
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