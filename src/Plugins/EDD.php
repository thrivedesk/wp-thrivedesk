<?php

namespace ThriveDesk\Plugins;

use ThriveDesk\Plugin;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}
final class EDD extends Plugin
{
    /**
     * The single instance of this class
     */
    private static $instance = null;

    /**
     * Construct EDD class.
     *
     * @since 0.0.1
     * @access private
     */
    private function __construct()
    {
        //
    }

    /**
     * Main EDD Instance.
     *
     * Ensures that only one instance of EDD exists in memory at any one
     * time. Also prevents needing to define globals all over the place.
     *
     * @since 0.0.1
     * @return object|EDD
     * @access public
     */
    public static function instance()
    {
        if (!isset(self::$instance) && !(self::$instance instanceof EDD)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Check if plugin active or not
     *
     * @return boolean
     */
    public static function is_plugin_active(): bool
    {
        if (!function_exists('EDD')) return false;

        return true;
    }

    /**
     * Check if customer exist or not
     *
     * @return boolean
     */
    public function is_customer_exist(): bool
    {
        if (!$this->customer_email) return false;

        if (!$this->customer)
            $this->customer = new \EDD_Customer($this->customer_email);

        if (!$this->customer->id) return false;

        return true;
    }

    /**
     * The accepted payment statuses of this plugin
     *
     * @return array
     */
    public function accepted_statuses(): array
    {
        return ['Complete'];
    }

    /**
     * Get the customer data
     *
     * @return array
     */
    public function get_customer(): array
    {
        if (!$this->customer_email) return [];

        if (!$this->customer)
            $this->customer = new \EDD_Customer($this->customer_email);

        if (!$this->customer->id) return [];

        return [
            'name' => $this->customer->name ?? '',
            'registered_at' => date('d M Y', strtotime($this->customer->date_created)) ?? ''
        ];
    }

    /**
     * Get the formated amount
     *
     * @param float $amount
     * @return string
     */
    public function get_formated_amount(float $amount): string
    {
        return edd_currency_filter(edd_format_amount($amount));
    }

    /**
     * Get the customer orders
     *
     * @return array
     */
    public function get_orders(): array
    {
        $orders = [];

        if (!$this->is_customer_exist()) return $orders;

        foreach ($this->customer->get_payments() as $order) {

            if ('live' != $order->mode) continue;

            array_push($orders, [
                'order_id'        => $order->number,
                'amount'          => (float) $order->total,
                'amount_formated' => $this->get_formated_amount($order->total),
                'date'            => date('d M Y', strtotime($order->date)),
                'order_status'    => $order->status_nicename,
                'downloads'       => $this->get_order_items($order),
            ]);
        }

        return $orders;
    }

    /**
     * Get order items
     *
     * @since 0.0.5
     * 
     * @param object $order
     * @return array
     */
    private function get_order_items($order): array
    {
        return array_map(function ($download) use ($order) {
            $edd_download = edd_get_download($download['id']);
            $option_name = edd_get_price_option_name($download['id'], $download['options']['price_id']);

            $download_item = [
                'title'       => $edd_download->get_name(),
                'option_name' => $option_name ?? '',
            ];

            if (function_exists('edd_software_licensing')) {
                $license = edd_software_licensing()->get_license_by_purchase($order->number, $download['id']) ?? [];

                $download_item = array_merge($download_item, [
                    'license' => [
                        'key'              => $license->license_key ?? '',
                        'activation_limit' => $license->activation_limit ?? '',
                        'sites'            => $license->sites ?? [],
                        'activation_count' => $license->activation_count ?? 0,
                        'date_created'     => $license->date_created ?? '',
                        'expiration'       => $license->expiration ?? '',
                        'is_lifetime'      => $license->is_lifetime,
                        'status'           => $license->status ?? '',
                    ]
                ]);
            }

            return $download_item;
        }, $order->downloads ?? []);
    }


    // TODO: Move to parent class
    public function get_plugin_data(string $key = '')
    {
        $thrivedesk_options = thrivedesk_options();

        $options = $thrivedesk_options['edd'] ?? [];

        return $key ? ($options[$key] ?? '') : $options;
    }

    public function connect()
    {
        $thrivedesk_options = get_option('thrivedesk_options', []);
        $thrivedesk_options['edd'] = $thrivedesk_options['edd'] ?? [];

        $thrivedesk_options['edd']['connected'] = true;

        update_option('thrivedesk_options', $thrivedesk_options);
    }

    public function disconnect()
    {
        $thrivedesk_options = get_option('thrivedesk_options', []);
        $thrivedesk_options['edd'] = $thrivedesk_options['edd'] ?? [];

        $thrivedesk_options['edd'] = [
            'api_token' => '',
            'connected' => false,
        ];

        update_option('thrivedesk_options', $thrivedesk_options);
    }
}