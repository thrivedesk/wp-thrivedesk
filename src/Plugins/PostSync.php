<?php

namespace ThriveDesk\Plugins;

use ThriveDesk\Plugin;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

final class PostSync extends Plugin
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
    public static function is_plugin_active(): bool{
        return true;
    }
    /**
     * Check if customer exist or not
     *
     * @return boolean
     */
    public function is_customer_exist(): bool{
        return true;
    }
    /**
     * Get the accepted payment statuses of this plugin
     *
     * @return array
     */
    public function accepted_statuses(): array{
        return [];
    }
    /**
     * Get the customer data
     *
     * @return array
     */
    public function get_customer(): array{
        return [];
    }
    /**
     * Get the customer orders
     *
     * @return array
     */
    public function get_orders(): array{
        return [];
    }
    /**
     * Main PostSync Instance.
     *
     *
     * @return PostSync|null
     * @access public
     * @since 0.7.0
     */
    public static function instance(): ?PostSync
    {
        if (!isset(self::$instance) && !(self::$instance instanceof PostSync)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function connect()
    {
        $thrivedesk_options = get_option('thrivedesk_options', []);
        $thrivedesk_options['postsync'] = $thrivedesk_options['postsync'] ?? [];

        $thrivedesk_options['postsync']['connected'] = true;

        update_option('thrivedesk_options', $thrivedesk_options);
    }

    public function disconnect()
    {
        $thrivedesk_options = get_option('thrivedesk_options', []);
        $thrivedesk_options['postsync'] = $thrivedesk_options['postsync'] ?? [];

        $thrivedesk_options['postsync'] = [
            'api_token' => '',
            'connected' => false,
        ];

        update_option('thrivedesk_options', $thrivedesk_options);
    }

    public function get_plugin_data(string $key = '')
    {
        $thrivedesk_options = thrivedesk_options();

        $options = $thrivedesk_options['postsync'] ?? [];

        return $key ? ($options[$key] ?? '') : $options;
    }

    /**
     * get all post types array
     * @since 0.6.2
     * @access public
     * @return array
     */
    public function get_all_post_types_arr(){
        $args = array(
            'public'   => true,
            'show_in_rest' => true
        );
        
        $output = 'names'; 
        $operator = 'and'; 
        $post_types = get_post_types( $args, $output, $operator );
    
        return $post_types;
    }
    
}