<?php


namespace ThriveDesk\Plugins;

use ThriveDesk\Plugin;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

final class FluentCRM extends Plugin
{
    /**
     * The single instance of this class
     */
    private static $instance = null;

    public function accepted_statuses(): array
    {
        return [];
    }

    /**
     * Check if plugin active or not
     *
     * @return boolean
     */
    public static function is_plugin_active(): bool
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        if (is_plugin_active('fluent-crm/fluent-crm.php')) {
            return true;
        }

        return false;
    }

    /**
     * Check if customer exist or not
     *
     * @return boolean
     */
    public function is_customer_exist(): bool
    {
        if (!$this->customer_email) return false;

        if (!$this->customer && function_exists('FluentCrmApi')) {
            $contactApi = FluentCrmApi('contacts');
            $this->customer = $contactApi->getContact($this->customer_email);
        }

        if (!$this->customer) return false;

        return true;
    }

    /**
     * Main FluentCRM Instance.
     *
     * Ensures that only one instance of WooCommerce exists in memory at any one
     * time. Also prevents needing to define globals all over the place.
     *
     * @return object|WooCommerce
     * @access public
     * @since 0.0.1
     */
    public static function instance()
    {
        if (!isset(self::$instance) && !(self::$instance instanceof FluentCRM)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function connect()
    {
        $thrivedesk_options = get_option('thrivedesk_options', []);
        $thrivedesk_options['fluentcrm'] = $thrivedesk_options['fluentcrm'] ?: [];

        $thrivedesk_options['fluentcrm']['connected'] = true;

        update_option('thrivedesk_options', $thrivedesk_options);
    }

    public function disconnect()
    {
        $thrivedesk_options = get_option('thrivedesk_options', []);
        $thrivedesk_options['fluentcrm'] = $thrivedesk_options['fluentcrm'] ?: [];

        $thrivedesk_options['fluentcrm'] = [
            'api_token' => '',
            'connected' => false,
        ];

        update_option('thrivedesk_options', $thrivedesk_options);
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

    public function get_plugin_data(string $key = '')
    {
        $thrivedesk_options = thrivedesk_options();

        $options = $thrivedesk_options['fluentcrm'] ?: [];

        return $key ? ($options[$key] ?: '') : $options;
    }

    /**
     * get customer tags
     *
     * @return array
     */
    public function get_customer_tags(): array
    {
        $tags = [];
        foreach ($this->customer->tags as $tag) {
            array_push($tags, $tag->title);
        }
        return $tags;
    }

    /**
     * get customer lists
     *
     * @return array
     */
    public function get_customer_lists(): array
    {
        $lists = [];
        foreach ($this->customer->lists as $list) {
            array_push($lists, $list->title);
        }
        return $lists;
    }

    /**
     * Get the customer data
     *
     * @return array
     */
    public function get_customer(): array
    {
        if (!$this->customer_email) return [];

        if (!$this->customer && function_exists('FluentCrmApi')) {
            $contactApi = FluentCrmApi('contacts');
            $this->customer = $contactApi->getContact($this->customer_email);
        }

        if (!$this->customer->id) return [];

        return [
            'first_name'    => $this->customer->first_name ?: '',
            'last_name'     => $this->customer->last_name ?: '',
            'email'         => $this->customer->email ?: '',
            'phone'         => $this->customer->phone ?: '',
            'status'        => $this->customer->status ?: '',
            'contact_type'  => $this->customer->contact_type ?: '',
            'tags'          => $this->get_customer_tags(),
            'lists'         => $this->get_customer_lists(),
            'photo'         => $this->customer->photo ?: '',
            'last_activity' => date('d M Y', strtotime($this->customer->last_activity)) ?: '',
            'updated_at'    => date('d M Y', strtotime($this->customer->updated_at)) ?: '',
            'created_at'    => date('d M Y', strtotime($this->customer->created_at)) ?: ''
        ];
    }
}