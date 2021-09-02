<?php


namespace ThriveDesk\Plugins;

use ThriveDesk\Plugin;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

final class Autonami extends Plugin
{
    /** The single instance of this class */
    private static $instance = null;

    public function accepted_statuses(): array
    {
        return [];
    }

    /**
     * Check if plugin active or not
     *
     * @return boolean
     * @since 0.9.0
     */
    public static function is_plugin_active(): bool
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        if (is_plugin_active('wp-marketing-automations-pro/wp-marketing-automations-pro.php')) {
            return true;
        }

        return false;
    }

    /**
     * Check if customer exist or not
     *
     * @return boolean
     * @since 0.7.0
     */
    public function is_customer_exist(): bool
    {
        if (!$this->customer_email) return false;

        if (!$this->customer && class_exists('WooFunnels_Contact')) {
            $woo_funnels_contact = new \WooFunnels_Contact();
            $this->customer     = $woo_funnels_contact->get_contact_by_email($this->customer_email);
        }

        if (!$this->customer) {
            return false;
        }
        $c = new \BWFCRM_Contact($this->customer_email);
        dd($c);

        return true;
    }

    /**
     * Main FluentCRM Instance.
     *
     * Ensures that only one instance of WooCommerce exists in memory at any one
     * time. Also prevents needing to define globals all over the place.
     *
     * @return FluentCRM|null
     * @access public
     * @since  0.7.0
     */
    public static function instance(): ?Autonami
    {
        if (!isset(self::$instance) && !(self::$instance instanceof FluentCRM)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function connect()
    {
        $thrivedesk_options                          = get_option('thrivedesk_options', []);
        $thrivedesk_options['autonami']              = $thrivedesk_options['autonami'] ?? [];
        $thrivedesk_options['autonami']['connected'] = true;

        update_option('thrivedesk_options', $thrivedesk_options);
    }

    public function disconnect()
    {
        $thrivedesk_options             = get_option('thrivedesk_options', []);
        $thrivedesk_options['autonami'] = $thrivedesk_options['autonami'] ?? [];
        $thrivedesk_options['autonami'] = [
            'api_token' => '',
            'connected' => false,
        ];

        update_option('thrivedesk_options', $thrivedesk_options);
    }

    public function prepare_data(): array
    {
        return (array)$this->customer;
        return [
            'id'             => $this->customer->id ?? '',
            'wpid'             => $this->customer->id ?? '',
            'uid'             => $this->customer->id ?? '',
            'email'          => $this->customer->email ?? '',
            'first_name'     => $this->customer->first_name ?? '',
            'last_name'      => $this->customer->last_name ?? '',
            'phone'          => $this->customer->phone ?? '',
            'country'        => $customer_formatted_country ?? '',
            'state'          => $this->customer->state ?? '',
            'timezone'          => $this->customer->state ?? '',
            'contact_type'   => $this->customer->contact_type ? ucfirst($this->customer->contact_type) : '',
            'source'          => $this->customer->photo ?? '',
            'points'          => $this->customer->photo ?? '',
            'tags'           => $this->get_customer_tags(),
            'lists'          => $this->get_customer_lists(),
            'created_at'     => $this->customer->created_at ? date('d M Y', strtotime($this->customer->created_at)) : '',
            'last_modified'  => $this->customer->last_activity ? date('d M Y', strtotime($this->customer->last_activity)) : '',
            'status'         => $this->customer->status ? ucfirst($this->customer->status) : ''
        ];
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
        $options            = $thrivedesk_options['autonami'] ?? [];

        return $key ? ($options[$key] ?? '') : $options;
    }

    /**
     * get customer tags
     *
     * @return array
     * @since 0.9.0
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
     * @since 0.9.0
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
     * @since 0.9.0
     */
    public function get_customer(): array
    {
        if (!$this->customer_email) return [];

        if (!$this->customer && function_exists('FluentCrmApi')) {
            $contactApi     = FluentCrmApi('contacts');
            $this->customer = $contactApi->getContact($this->customer_email);
        }

        $customer_formatted_country = $this->customer->country ?? '';

        if (function_exists('FluentCrm')) {
            $app       = FluentCrm();
            $countries = $app->applyFilters('fluentcrm-countries', []);

            foreach ($countries as $country) {
                if ($country['code'] == $this->customer->country) {
                    $customer_formatted_country = $country['title'];
                    break;
                }
            }
        }

        if (!$this->customer->id) return [];

        return [
            'id'             => $this->customer->id ?? '',
            'first_name'     => $this->customer->first_name ?? '',
            'last_name'      => $this->customer->last_name ?? '',
            'email'          => $this->customer->email ?? '',
            'phone'          => $this->customer->phone ?? '',
            'status'         => $this->customer->status ? ucfirst($this->customer->status) : '',
            'contact_type'   => $this->customer->contact_type ? ucfirst($this->customer->contact_type) : '',
            'tags'           => $this->getCustomerLists(),
            'lists'          => $this->getCustomerTags(),
            'photo'          => $this->customer->photo ?? '',
            'address_line_1' => $this->customer->address_line_1 ?? '',
            'address_line_2' => $this->customer->address_line_2 ?? '',
            'city'           => $this->customer->city ?? '',
            'state'          => $this->customer->state ?? '',
            'postal_code'    => $this->customer->postal_code ?? '',
            'country'        => $customer_formatted_country ?? '',
            'date_of_birth'  => $this->customer->date_of_birth ? date('d M Y', strtotime($this->customer->date_of_birth)) : '',
            'last_activity'  => $this->customer->last_activity ? date('d M Y', strtotime($this->customer->last_activity)) : '',
            'updated_at'     => $this->customer->updated_at ? date('d M Y', strtotime($this->customer->updated_at)) : '',
            'created_at'     => $this->customer->created_at ? date('d M Y', strtotime($this->customer->created_at)) : ''
        ];
    }

    /**
     * create new contact
     *
     * @param string $contactName
     *
     * @return bool
     * @since 0.9.0
     */
    public function create_new_contact(string $contactName): bool
    {
        if (function_exists('FluentCrmApi')) {
            $contactApi = FluentCrmApi('contacts');

            $contact = $contactApi->getContact($this->customer_email);

            if ($contact) {
                return true;
            }

            $first_name = '';
            $last_name  = '';

            $name_array = explode(" ", trim($contactName));
            if (sizeof($name_array) < 2) {
                $first_name = trim($contactName);
            } else {
                $last_name  = array_pop($name_array);
                $first_name = implode(" ", $name_array);
            }

            $data = [
                'email'      => $this->customer_email,
                'first_name' => $first_name,
                'last_name'  => $last_name,
            ];

            return $contactApi->createOrUpdate($data) ? true : false;
        }
        return false;
    }
}