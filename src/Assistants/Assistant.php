<?php

namespace ThriveDesk\Assistants;

if (!defined('ABSPATH')) {
    exit;
}

class Assistant {

    private static $instance = null;

    public function __construct()
    {
        add_action('wp_head', [$this, 'load_assistant_script']);
        add_action('rest_api_init', array($this, 'assistant_routes'));
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
                name: ' . wp_get_current_user()->display_name . ',
                email: '. wp_get_current_user()->user_email .'})
        </script>';

        echo $assistant_script;
    }

    public function assistant_routes()
    {
        register_rest_route('thrivedesk/v1', '/assistant/submit', array(
            'methods'             => 'post',
            'callback'            => array($this, 'save_assistant_settings'),
            'permission_callback' => function () {
                return true;
            }
        ));
    }

    public function save_assistant_settings()
    {
        if (isset($_POST['selected_assistant_id'])) {
            $selected_assistant_id = $_POST['selected_assistant_id'];

            // later must be deleted, it will be fetched from api
            $assistant_script = '<script>!function(t,e,n){function s(){
      var t=e.getElementsByTagName("script")[0],n=e.createElement("script");
      n.type="text/javascript",n.async=!0,n.src="https://assistant.thrivedesk.io/bootloader.js?"+Date.now(),
      t.parentNode.insertBefore(n,t)}if(t.Assistant=n=function(e,n,s){t.Assistant.readyQueue.push({method:e,options:n,data:s})},
      n.readyQueue=[],"complete"===e.readyState)return s();
    t.attachEvent?t.attachEvent("onload",s):t.addEventListener("load",s,!1)}
    (window,document,window.Assistant||function(){}),window.Assistant("init","'. $selected_assistant_id .'");
  </script>';

            $assistant_settings = [
                'id'       => $_POST['status'] == 'true' ? $selected_assistant_id : '',
                'status'    => $_POST['status'] ?? false,
                'script'   => $_POST['status'] == 'true' ? $assistant_script : '',
            ];

            if (get_option('td_assistant_settings')) {
                update_option('td_assistant_settings', $assistant_settings);
            } else {
                add_option('td_assistant_settings', $assistant_settings);
            }
            return new \WP_REST_Response('Setting saved successfully', 200);
        }
        return new \WP_REST_Response('Something went wrong', 400);
    }


    public static function get_assistants()
    {
        $token = get_option('td_helpdesk_settings')['td_helpdesk_api_key'];
        $url = THRIVEDESK_API_URL .'/v1/assistants';

        $args               = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'X-Td-Organization-Slug' => get_option('td_helpdesk_settings')['td_helpdesk_organization_slug'] ?? '',
            ],
        ];
        $response           = wp_remote_get($url, $args);
        $body               = wp_remote_retrieve_body($response);
        $body               = json_decode($body, true);

        return $body['assistants'] ?? [];
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
