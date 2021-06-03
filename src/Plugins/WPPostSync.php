<?php

namespace ThriveDesk\Plugins;

use ThriveDesk\Plugin;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

final class WPPostSync extends Plugin
{
    /**
     * The single instance of this class
     */
    private static $instance = null;

    /**
     * Check if plugin active or not
     *
     * @return boolean
     */
    public static function is_plugin_active(): bool
    {
        return true;
    }

    /**
     * Check if customer exist or not
     *
     * @return boolean
     */
    public function is_customer_exist(): bool
    {
        return true;
    }

    /**
     * Get the accepted payment statuses of this plugin
     *
     * @return array
     */
    public function accepted_statuses(): array
    {
        return [];
    }

    /**
     * Get the customer data
     *
     * @return array
     */
    public function get_customer(): array
    {
        return [];
    }

    /**
     * Get the customer orders
     *
     * @return array
     */
    public function get_orders(): array
    {
        return [];
    }

    /**
     * Main WPPostSync Instance.
     *
     *
     * @return WPPostSync|null
     * @access public
     * @since 0.8.0
     */
    public static function instance(): ?WPPostSync
    {
        if (!isset(self::$instance) && !(self::$instance instanceof WPPostSync)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function connect()
    {
        $thrivedesk_options = get_option('thrivedesk_options', []);
        $thrivedesk_options['wppostsync'] = $thrivedesk_options['wppostsync'] ?? [];

        $thrivedesk_options['wppostsync']['connected'] = true;

        update_option('thrivedesk_options', $thrivedesk_options);
    }

    public function disconnect()
    {
        $thrivedesk_options = get_option('thrivedesk_options', []);
        $thrivedesk_options['wppostsync'] = $thrivedesk_options['wppostsync'] ?? [];

        $thrivedesk_options['wppostsync'] = [
            'api_token' => '',
            'connected' => false,
        ];

        update_option('thrivedesk_options', $thrivedesk_options);
    }

    public function get_plugin_data(string $key = '')
    {
        $thrivedesk_options = thrivedesk_options();

        $options = $thrivedesk_options['wppostsync'] ?? [];

        return $key ? ($options[$key] ?? '') : $options;
    }

    /**
     * get all post types array
     * @return array
     * @since 0.8.0
     * @access public
     */
    public function get_all_post_types_arr()
    {
        $args = array(
            'public'        => true,
            'show_in_rest'  => true
        );

        $output = 'names';
        $operator = 'and';
        $post_types = get_post_types($args, $output, $operator);

        return $post_types;
    }

    /**
     * @param string $query_string for search among all post type
     * @access public
     * @return JSON
     * @since 0.8.0
     */
    public function get_post_search_result($query_string = '')
    {
        $x_query = new \WP_Query(
            array(
                's'         => $query_string,
                'post_type' => get_option('thrivedesk_post_type_sync_option')
            )
        );
        $search_posts = [];
        while ($x_query->have_posts()):
            $x_query->the_post();
            $search_posts[get_the_ID()] = [
                'id'     => get_the_ID(),
                'text'   => html_entity_decode(get_the_title(), ENT_NOQUOTES, 'UTF-8'),
                'link'   => get_the_permalink(),
                'value'  => html_entity_decode(get_the_title(), ENT_NOQUOTES, 'UTF-8') . '|_td_|' . get_the_permalink(),
            ];

        endwhile;
        if (empty($search_posts)) {
            return [
                'data' => []
            ];
        } else {
            return [
                'count' => count($search_posts) . ' result found',
                'data'  => $search_posts
            ];
        }
        wp_reset_query();
    }
}