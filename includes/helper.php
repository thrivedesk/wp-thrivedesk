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
		$weeks = (int) floor($diff->d / 7);
		$days = $diff->d - ($weeks * 7);

		$parts = array();
		if ($diff->y) { $parts[] = sprintf(_n('%d year', '%d years', $diff->y, 'thrivedesk'), $diff->y); }
		if ($diff->m) { $parts[] = sprintf(_n('%d month', '%d months', $diff->m, 'thrivedesk'), $diff->m); }
		if ($weeks)   { $parts[] = sprintf(_n('%d week', '%d weeks', $weeks, 'thrivedesk'), $weeks); }
		if ($days)    { $parts[] = sprintf(_n('%d day', '%d days', $days, 'thrivedesk'), $days); }
		if ($diff->h) { $parts[] = sprintf(_n('%d hour', '%d hours', $diff->h, 'thrivedesk'), $diff->h); }
		if ($diff->i) { $parts[] = sprintf(_n('%d minute', '%d minutes', $diff->i, 'thrivedesk'), $diff->i); }
		if ($diff->s) { $parts[] = sprintf(_n('%d second', '%d seconds', $diff->s, 'thrivedesk'), $diff->s); }

		if (!$full) $parts = array_slice($parts, 0, 1);

		if (empty($parts)) {
			return __('just now', 'thrivedesk');
		}

		/* translators: %s: human-readable time difference, e.g. "3 months" */
		return sprintf(__('%s ago', 'thrivedesk'), implode(', ', $parts));
	}
}

if (!function_exists('td_conversation_status_label')) {
	/**
	 * Translatable display label for a conversation status.
	 *
	 * The status value comes from the API as a slug (active, pending, closed);
	 * map it to a localized label so the portal badge reads in the site language
	 * instead of the raw English slug. The default keeps any future/unexpected
	 * slug readable rather than blank.
	 */
	function td_conversation_status_label($status): string {
		switch (strtolower(trim((string) $status))) {
			case 'active':
				return __('Active', 'thrivedesk');
			case 'pending':
				return __('Pending', 'thrivedesk');
			case 'closed':
				return __('Closed', 'thrivedesk');
			case '':
			case 'unknown':
				return __('Unknown', 'thrivedesk');
			default:
				return ucfirst((string) $status);
		}
	}
}

if (!function_exists('td_paginator_label')) {
	/**
	 * Display label for a paginator link.
	 *
	 * The API sends Laravel paginator labels: page numbers, an ellipsis, and
	 * "&laquo; Previous" / "Next &raquo;" with the chevrons as HTML entities.
	 * Decode the entities so esc_html() at render time shows a real « / »
	 * instead of the raw "&laquo;" text, and run the Previous/Next words
	 * through the text domain so they follow the site language.
	 */
	function td_paginator_label($label): string {
		$label = html_entity_decode((string) $label, ENT_QUOTES);

		return str_ireplace(
			['Previous', 'Next'],
			[__('Previous', 'thrivedesk'), __('Next', 'thrivedesk')],
			$label
		);
	}
}

if (!function_exists('td_portal_back_to_list_url')) {
	/**
	 * URL back to the ticket list from a single conversation.
	 *
	 * The detail view is just the list with td_conversation_id in the query,
	 * so dropping it lands back on the list. On the WooCommerce "Support" tab
	 * (/my-account/td-support/) get_permalink() would resolve to the bare
	 * /my-account/ page and bounce the customer out of the tab, hence the
	 * remove_query_arg() instead. Other query args (status filter, page) get
	 * kept so the user lands back where they were.
	 */
	function td_portal_back_to_list_url(): string {
		return remove_query_arg('td_conversation_id');
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
 * Get the main helpdesk settings (new format)
 * This function returns the consolidated settings from td_helpdesk_settings
 * Automatically migrates old options if needed
 */
if (!function_exists('get_td_helpdesk_settings')) {
    function get_td_helpdesk_settings()
    {
        // Automatically migrate old options to new format if needed
        return migrate_td_options_to_settings();
    }
}



/**
 * Migrate all required data from old options to new settings format
 * This ensures all components have access to the data they need
 */
if (!function_exists('migrate_td_options_to_settings')) {
    function migrate_td_options_to_settings()
    {
        $old_options = get_option('td_helpdesk_options', []);
        $new_settings = get_option('td_helpdesk_settings', []);
        
        // If new settings already exist and have data, no migration needed
        if (!empty($new_settings) && isset($new_settings['td_helpdesk_api_key'])) {
            return $new_settings;
        }
        
        // If old options exist, migrate them to new format
        if (!empty($old_options)) {
            // Merge old options with new settings, prioritizing new settings
            $merged_settings = array_merge($old_options, $new_settings);
            
            // Ensure all required fields are present
            $required_fields = [
                'td_helpdesk_api_key',
                'td_helpdesk_assistant_id',
                'td_helpdesk_inbox_id',
                'td_helpdesk_page_id',
                'td_knowledgebase_slug',
                'td_helpdesk_post_types',
                'td_helpdesk_post_sync',
                'td_user_account_pages',
                'td_assistant_route_list'
            ];
            
            foreach ($required_fields as $field) {
                if (!isset($merged_settings[$field])) {
                    $merged_settings[$field] = $old_options[$field] ?? '';
                }
            }
            
            // Update the new settings option
            update_option('td_helpdesk_settings', $merged_settings);
            
            return $merged_settings;
        }
        
        return $new_settings;
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

/*
 * Clear cache from ajax call
 */
add_action('wp_ajax_thrivedesk_clear_cache', function () {
	if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'thrivedesk-nonce' ) ) {
		wp_send_json_error( [ 'message' => __( 'Invalid nonce', 'thrivedesk' ) ] );
	}

    if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'Unauthorized', 'thrivedesk' ) ] );
	} 


	remove_thrivedesk_all_cache();
	wp_send_json_success();
});

/*
 * Make a gravatar url from the current user email.
 *
 * d=404 makes Gravatar 404 for unknown addresses instead of returning its
 * generic silhouette, so the <img> onerror handler can swap in coloured
 * initials. Without it everyone with no Gravatar account renders the same
 * grey blob.
 */
if (!function_exists('get_gravatar_url')) {
	function get_gravatar_url($email, $size = 80): string {
		$hash = md5(strtolower(trim($email)));
		return "https://www.gravatar.com/avatar/$hash?s=$size&d=404";
	}
}