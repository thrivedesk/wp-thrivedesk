<?php

namespace ThriveDesk;

use WpFluent\Exception;

if (!defined('ABSPATH')) {
    exit;
}

class RestRoute
{
    /**
     * @var $instance
     * The single instance of this class
     * @since 0.9.0
     */
    private static $instance;

    /** Main RestRoute
     *
     * @return RestRoute
     * @since 0.9.0
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('rest_api_init', array($this, 'td_routes'));
        add_action('rest_api_init', array($this, 'form_routes'));
    }

    /**
     * ThriveDesk conversation rest route
     *
     * @since 0.9.0
     */
    public function td_routes()
    {
        register_rest_route('thrivedesk/v1', '/conversations/contact/(?P<id>\d+)', array(
            'methods'             => 'get',
            'callback'            => array($this, 'get_thrivedesk_conversations'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ));
    }

    public function form_routes()
    {
        register_rest_route('/td-settings', '/fluent-forms/(?P<form_id>\d+)', array(
            'methods'             => 'get',
            'callback'            => array($this, 'get_fluent_forms'),
        ));

    }

    /**
     * @param $data
     *
     * @return array|\WP_REST_Response
     *
     * @since 0.9.0
     */
    public function get_thrivedesk_conversations($data)
    {
        if (!isset($data['id'])) {
            return new \WP_REST_Response(['message' => 'Invalid request format'], 401);
        }

        if (!class_exists('BWF_Contacts')) {
            return new \WP_REST_Response(['message' => 'Class BWF_Contacts does not exists'], 401);
        }

        $contact_obj = \BWF_Contacts::get_instance();

        $contact = $contact_obj->get_contact_by('id', $data['id']);

        if (!absint($contact->get_id()) > 0) {
            return new \WP_REST_Response(['message' => 'Contact does not exists'], 401);
        }

        $contact_email = $contact->get_email();

        global $wpdb;
        $table_name = $wpdb->prefix . THRIVEDESK_DB_TABLE_CONVERSATION;

        $row = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

        if (!$row) {
            return [];
        }

        $column = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME = %s",
            $table_name,
            'deleted_at'
        ));

        if (!$column) {
            return [];
        }

        $td_conversations = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE contact='$contact_email' AND deleted_at IS NULL"
        );

        $formattedTickets = [];

        foreach ($td_conversations as $td_conversation) {
            $formattedTickets[] = [
                'id'           => '#' . $td_conversation->ticket_id,
                'title'        => $td_conversation->title,
                'status'       => $td_conversation->status,
                'submitted_at' => date($td_conversation->created_at),
                'action'       => THRIVEDESK_APP_URL . '/conversations/' . $td_conversation->id,
            ];
        }

        return new \WP_REST_Response($formattedTickets, 200);
    }

    /**
     * @param $data
     * @return \WP_REST_Response
     * @throws Exception
     */
    public static function get_fluent_forms($data): \WP_REST_Response
    {
        // add for only authenticate user
//        if (!is_user_admin()) {
//            return new \WP_REST_Response(['message' => 'No form does not exists'], 401);
//        }
        if ($data['form_id'] == 1) {
            $query = wpFluent()->table('fluentform_forms')
                ->orderBy('id', 'desc');

            $forms = $query->select('*')->get();
            return new \WP_REST_Response($forms, 200);
        } else {
            return new \WP_REST_Response([], 200);
        }

    }
}