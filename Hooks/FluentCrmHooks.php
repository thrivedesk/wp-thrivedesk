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

    public static function instance(): ?FluentCrmHooks
    {
        if (!isset(self::$instance) && !(self::$instance instanceof FluentCrmHooks)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function plugin_load()
    {
        add_action('fluentcrm_loaded', function () {

            $app = FluentCrm();

            $app->addCustomFilter(
                'support_tickets_providers',
                function ($providers) {
                    $providers['thrivedesk'] = [
                        'title' => __('Support Tickets by ThriveDesk', 'fluent-crm'),
                        'name' => __('ThriveDesk', 'fluent-crm')
                    ];
                    return $providers;
                }
            );


            $app->addCustomFilter(
                'get_support_tickets_thrivedesk',
                function ($data, $subscriber) {

                    $formattedTickets = [];

                    $actionHTML = '<a target="_blank" href="#">View Ticket</a>';
                    $formattedTickets[] = [
                        'id' => '#' . '10',
                        'title' => $subscriber->email,
                        'status' => 'status',
                        'Submitted at' => ' ago',
                        'action' => $actionHTML
                    ];


                    return [
                        'total' => 1,
                        'data' => $formattedTickets
                    ];
                },
                10,
                2
            );
        });
    }
}