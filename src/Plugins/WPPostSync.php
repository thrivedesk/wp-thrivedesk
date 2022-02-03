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

    public const POST_TITLE_LIMIT = 56;

    /**
     * Check if plugin active or not
     *
     * @return boolean
     * @since 0.8.0
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

    /**
     * @param string $key
     * @return array|mixed|string
     * @since 0.8.0
     */
    public function get_plugin_data(string $key = '')
    {
        $thrivedesk_options = thrivedesk_options();

        $options = $thrivedesk_options['wppostsync'] ?? [];

        return $key ? ($options[$key] ?? '') : $options;
    }

    /**
     * @param string $query_string for search among all post type
     * @access public
     * @since 0.8.0
     */
    public function get_post_search_result(string $query_string = ''): array
    {
        $x_query = new \WP_Query(
            array(
                's'         => $query_string,
                'post_type' => get_option('thrivedesk_post_type_sync_option')
            )
        );
        $search_posts = [];
        while ($x_query->have_posts()) :
            $x_query->the_post();
            $post_categories_array = get_the_category(get_the_ID());
            $post_title = $this->get_truncated_post_title(html_entity_decode(get_the_title(), ENT_NOQUOTES, 'UTF-8'));
            $search_posts[get_the_ID()] = [
                'id'            => get_the_ID(),
                'title'         => $post_title,
                'categories'    => count($post_categories_array) ? implode(', ', wp_list_pluck($post_categories_array, 'name')) : 'Category not available',
                'link'          => get_the_permalink(),
            ];

        endwhile;

        wp_reset_query();

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
    }

    /**
     * Truncate post title and add ending character if necessary
     *
     * @param $title
     * @return string
     * @since 0.8.0
     */
    public function get_truncated_post_title($title): string
    {
        if (mb_strwidth($title, 'UTF-8') > self::POST_TITLE_LIMIT) {
            return rtrim(mb_strimwidth($title, 0, self::POST_TITLE_LIMIT, '', 'UTF-8')) . '...';
        }
        return $title;
    }
}
