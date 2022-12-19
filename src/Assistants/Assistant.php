<?php

namespace ThriveDesk\Assistants;

use ThriveDesk\Services\TDApiService;

if (!defined('ABSPATH')) {
    exit;
}

class Assistant {

    private static $instance = null;

    public function __construct()
    {
        add_action('wp_head', [$this, 'load_assistant_script']);
    }

    public static function instance(): Assistant
    {
        if (!isset(self::$instance) && !(self::$instance instanceof Assistant)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function load_assistant_script()
    {
        $assistant_id = get_td_helpdesk_options()['td_helpdesk_assistant_id'];
        if (empty($assistant_id)) {
            return;
        }
        $assistant_script = '
        <script>
            !function(t,e,n){function s(){
                var t=e.getElementsByTagName("script")[0],n=e.createElement("script");
                n.type="text/javascript",n.async=!0,n.src="https://assistant.thrivedesk.io/bootloader.js?"+Date.now(),
                t.parentNode.insertBefore(n,t)}if(t.Assistant=n=function(e,n,s){t.Assistant.readyQueue.push({method:e,options:n,data:s})},
                n.readyQueue=[],"complete"===e.readyState)return s();
            t.attachEvent?t.attachEvent("onload",s):t.addEventListener("load",s,!1)}
            (window,document,window.Assistant||function(){}),window.Assistant("init","'.$assistant_id.'");

            Assistant("identify", {
                name: "'.wp_get_current_user()->display_name.'",
                email: "'.wp_get_current_user()->user_email.'"})
        </script>
        ';

        echo $assistant_script;
    }


    /**
     * retrieve the assistant list
     * @return array|mixed|object
     */
    public static function get_assistants()
    {
        $url = THRIVEDESK_API_URL .'/v1/assistants';

        $response_body = ( new TDApiService() )->getRequest($url);

        return $response_body['assistants'] ?? [];
    }

    public static function get_assistant_settings()
    {
        $assistant_settings = get_option('td_assistant_settings');

        return $assistant_settings ?? [];
    }

    /** get the organization list
     * @return mixed
     */
    public static function organizations()
    {
        $token              = get_option('td_helpdesk_settings')['td_helpdesk_api_key'];
        $url = THRIVEDESK_API_URL .'/v1/me';

        $args               = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
        ];

        $response = wp_remote_get($url, $args);
        $body     = wp_remote_retrieve_body($response);
        $body     = json_decode($body, true);

        return $body['organizations'];
    }
}
