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
                // EXTR_SKIP so an extracted key can never clobber $file, the
                // path about to be required. No caller passes $data today -
                // this is hygiene, not a live hole.
                extract($data, EXTR_SKIP);
            }

            /*
             * require, not require_once. A view is a template, and a template
             * has to be renderable more than once per request - the same
             * partial in a loop, or a view that renders another view, which
             * silently produced nothing the second time. No view declares a
             * function or class, so there is nothing double-inclusion can
             * collide with.
             */
            require $file;
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

if (!function_exists('thrivedesk_service_ips')) {
    /**
     * The IP addresses ThriveDesk calls this site from.
     *
     * One list, two readers: the setup screen prints them for the site owner to
     * allowlist, and TDApiService repeats them when a request never lands. They
     * used to be typed out separately in each place, so a change on the
     * ThriveDesk side could leave the two telling the user different things.
     *
     * @since 2.6.0
     * @access public
     * @return string[]
     */
    function thrivedesk_service_ips(): array
    {
        return ['20.68.187.32', '20.68.186.235', '20.117.184.59'];
    }
}

if (!function_exists('thrivedesk_integrations')) {
    /**
     * The integrations shown on the settings screen.
     *
     * Plain data, not markup: the same list feeds the React admin app and can
     * be asserted in tests without parsing HTML. Image paths are resolved to
     * URLs here because no caller should need to know the assets layout.
     *
     * `installed` is whether the partner plugin is active on this site;
     * `connected` is whether ThriveDesk holds a token for it. A row with an
     * `external` URL is handed off to the ThriveDesk app instead of being
     * connected from here - SureCart and Freemius authorize on their side.
     *
     * `description` is one sentence on what the integration puts in front of an
     * agent, not on what the partner plugin does - the reader already runs the
     * store and is deciding whether this is worth connecting.
     *
     * @since 2.6.0
     * @access public
     * @return array<int,array<string,mixed>>
     */
    function thrivedesk_integrations(): array
    {
        $plugins = [
            [
                'slug'        => 'woocommerce',
                'name'        => __('WooCommerce', 'thrivedesk'),
                'category'    => 'ecommerce',
                'description' => __('Show orders, subscriptions and shipping details beside every conversation.', 'thrivedesk'),
                'image'       => 'woo.svg',
                'class'       => \ThriveDesk\Plugins\WooCommerce::class,
            ],
            [
                'slug'        => 'edd',
                'name'        => __('Easy Digital Downloads', 'thrivedesk'),
                'category'    => 'ecommerce',
                'description' => __('Pull up a customer’s purchases, licenses and order history while you reply.', 'thrivedesk'),
                'image'       => 'edd.png',
                'class'       => \ThriveDesk\Plugins\EDD::class,
            ],
            [
                'slug'        => 'fluentcrm',
                'name'        => __('FluentCRM', 'thrivedesk'),
                'category'    => 'crm',
                'description' => __('See the contact’s lists, tags and lifetime value without leaving the inbox.', 'thrivedesk'),
                'image'       => 'fluentcrm.png',
                'class'       => \ThriveDesk\Plugins\FluentCRM::class,
            ],
            [
                'slug'        => 'wppostsync',
                'name'        => __('WordPress Post Sync', 'thrivedesk'),
                'category'    => 'core',
                'description' => __('Search your published posts and drop a link to one straight into a reply.', 'thrivedesk'),
                'image'       => 'wppostsync.png',
                'class'       => \ThriveDesk\Plugins\WPPostSync::class,
            ],
            [
                'slug'        => 'autonami',
                'name'        => __('FunnelKit', 'thrivedesk'),
                'category'    => 'crm',
                'description' => __('Bring FunnelKit Automations contacts, lists and tags into the conversation sidebar.', 'thrivedesk'),
                'image'       => 'autonami.png',
                'class'       => \ThriveDesk\Plugins\Autonami::class,
            ],
        ];

        $integrations = [];

        foreach ($plugins as $plugin) {
            $instance = call_user_func([$plugin['class'], 'instance']);

            $integrations[] = [
                'slug'        => $plugin['slug'],
                'name'        => $plugin['name'],
                'category'    => $plugin['category'],
                'description' => $plugin['description'],
                'image'       => THRIVEDESK_PLUGIN_ASSETS . '/images/' . sanitize_file_name($plugin['image']),
                'installed'   => (bool) $instance->is_plugin_active(),
                'connected'   => (bool) $instance->get_plugin_data('connected'),
                'external'    => null,
            ];
        }

        $partners = [
            ['surecart', 'SureCart', __('View SureCart orders, subscriptions and refunds against the customer you are helping.', 'thrivedesk')],
            ['freemius', 'Freemius', __('Look up Freemius licenses, payments and plan changes right from a ticket.', 'thrivedesk')],
        ];

        foreach ($partners as $partner) {
            list($slug, $name, $description) = $partner;

            $integrations[] = [
                'slug'        => $slug,
                'name'        => $name,
                'category'    => 'ecommerce',
                'description' => $description,
                'image'       => THRIVEDESK_PLUGIN_ASSETS . '/images/' . $slug . '.png',
                'installed'   => true,
                'connected'   => false,
                'external'    => THRIVEDESK_APP_URL . '/apps/' . $slug,
            ];
        }

        return $integrations;
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

if (!function_exists('td_conversation_status')) {
	/**
	 * Constrain a conversation status to something that is actually a status.
	 *
	 * `status` crosses the SaaS trust boundary: sanitize_text_field() on write
	 * strips tags but leaves quotes, ampersands and entities intact, and the
	 * value is then re-emitted into markup.
	 *
	 * The vocabulary is the SaaS's to grow - the plugin's own UI already knows
	 * active, open, pending, closed, resolved, on-hold and archived - so this
	 * constrains the *shape* rather than pinning a value list that would
	 * silently relabel a newly added status as "unknown". A bare status token
	 * has no room for a quote, an angle bracket or an entity, which is the
	 * whole injection surface.
	 *
	 * @param mixed $status Raw status value.
	 * @return string
	 */
	function td_conversation_status($status): string {
		$status = trim((string) $status);

		return preg_match('/^[A-Za-z][A-Za-z0-9 _-]{0,29}$/', $status) ? $status : 'unknown';
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

/**
 * Name of the daily cleanup event.
 */
if (!defined('THRIVEDESK_CLEANUP_CRON_HOOK')) {
	define('THRIVEDESK_CLEANUP_CRON_HOOK', 'thrivedesk_cleanup_expired_transients');
}

if (!function_exists('thrivedesk_delete_expired_transients')) {
	/**
	 * Delete ThriveDesk transients whose timeout has already passed.
	 *
	 * This is a self-joined DELETE over wp_options, the hottest table in
	 * WordPress. It has no business running per page view; WordPress core
	 * does the equivalent sweep once a day, so this one runs on cron too.
	 *
	 * @return void
	 */
	function thrivedesk_delete_expired_transients() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
				WHERE a.option_name LIKE %s
				AND a.option_name NOT LIKE %s
				AND b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) )
				AND b.option_value < %d",
				$wpdb->esc_like('_transient_thrivedesk_') . '%',
				$wpdb->esc_like('_transient_timeout_') . '%',
				time()
			)
		);
	}
}

/*
 * The workspace card caches what ThriveDesk says about this site for six hours.
 * A new API key can point the plugin at a different workspace on a different
 * plan, so the cached answer has to go when the settings change - otherwise the
 * sidebar keeps describing the account you just disconnected from.
 */
add_action('update_option_td_helpdesk_settings', ['ThriveDesk\\Services\\WorkspaceService', 'flush']);
add_action('add_option_td_helpdesk_settings', ['ThriveDesk\\Services\\WorkspaceService', 'flush']);
add_action('delete_option_td_helpdesk_settings', ['ThriveDesk\\Services\\WorkspaceService', 'flush']);
add_action(\ThriveDesk\Services\WorkspaceService::REFRESH_HOOK, ['ThriveDesk\\Services\\WorkspaceService', 'refresh']);

add_action(THRIVEDESK_CLEANUP_CRON_HOOK, 'thrivedesk_delete_expired_transients');

if (!function_exists('thrivedesk_schedule_cleanup_cron')) {
	/**
	 * Ensure the daily sweep is scheduled.
	 *
	 * Hooked to init rather than to the activation hook so stores that update
	 * the plugin without ever re-activating it also get the event.
	 * wp_next_scheduled() reads the already-autoloaded `cron` option, so the
	 * guard costs nothing once the event exists.
	 *
	 * @return void
	 */
	function thrivedesk_schedule_cleanup_cron() {
		if (!wp_next_scheduled(THRIVEDESK_CLEANUP_CRON_HOOK)) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', THRIVEDESK_CLEANUP_CRON_HOOK);
		}
	}
}

add_action('init', 'thrivedesk_schedule_cleanup_cron');

if (!function_exists('td_user_action_throttled')) {
	/**
	 * Per-user rate gate for portal actions that cost an upstream API call.
	 *
	 * The portal AJAX actions are open to every logged-in user, a bare
	 * Subscriber included, and some of them drop a cache before re-fetching -
	 * so each click is a guaranteed outbound request holding a PHP worker.
	 * Without a gate, one account can pin the pool.
	 *
	 * Returns true when this user already ran the action inside the window and
	 * should be refused.
	 *
	 * @param string $action Action slug, unique per handler.
	 * @param int    $window Seconds a caller must wait between calls.
	 * @return bool
	 */
	function td_user_action_throttled(string $action, int $window = 10): bool {
		$user_id = get_current_user_id();

		// Nothing to key a per-user gate on. Handlers using this are
		// logged-in-only anyway and reject the request before reaching here.
		if (!$user_id) {
			return false;
		}

		$key = 'td_throttle_' . $action . '_' . $user_id;

		if (false !== get_transient($key)) {
			return true;
		}

		// The transient *is* the gate: it exists for $window seconds and its
		// value is never read.
		set_transient($key, 1, $window);

		return false;
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