<?php


namespace ThriveDesk;


class FluentCrmHooks
{
    /**
     * The single instance of this class
     */
    private static $instance = null;

    function __construct()
    {
        $this->plugin_load();
    }

    /**
     * @return FluentCrmHooks|null
     * @since 0.7.0
     */
    public static function instance(): ?FluentCrmHooks
    {
        if (!isset(self::$instance) && !(self::$instance instanceof FluentCrmHooks)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * filters for ThriveDesk conversation with fluentCrm
     *
     * @since 0.7.0
     */
    public function plugin_load()
    {
        add_action('fluentcrm_loaded', function () {

            $app = FluentCrm();

            $app->addCustomFilter(
                'support_tickets_providers',
                function ($providers) {
                    $providers['thrivedesk'] = [
                        'title' => __('Support Tickets by ThriveDesk', 'fluent-crm'),
                        'name'  => __('ThriveDesk', 'fluent-crm')
                    ];
                    return $providers;
                }
            );

            $app->addCustomFilter(
                'get_support_tickets_thrivedesk',
                function ($data, $subscriber) {
                    global $wpdb;
                    $table_name = $wpdb->prefix . THRIVEDESK_DB_TABLE_CONVERSATION;

                    $td_conversations = $wpdb->get_results(
                        "SELECT * FROM $table_name WHERE contact = '$subscriber->email' AND deleted_at IS NULL"
                    );

                    $formattedTickets = [];

                    foreach ($td_conversations as $td_conversation) {
                        $conversation_url = THRIVEDESK_APP_URL . '/conversations/' . $td_conversation->id;

                        $actionHTML         = '<a target="_blank" href="' . $conversation_url . '">View conversation</a>';
                        $formattedTickets[] = [
                            'id'           => '#' . $td_conversation->ticket_id,
                            'title'        => $td_conversation->title,
                            'status'       => $td_conversation->status,
                            'Submitted at' => date($td_conversation->created_at),
                            'action'       => $actionHTML
                        ];
                    }

                    return [
                        'total' => sizeof($td_conversations),
                        'data'  => $formattedTickets
                    ];
                },
                10,
                2
            );
        });
    }
}