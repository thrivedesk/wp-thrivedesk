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
	    // ajax call for verifying the helpdesk setting
	    add_action('wp_ajax_thrivedesk_load_assistants', [$this, 'thrivedesk_load_assistants']);
    }

	public function thrivedesk_load_assistants(  ): void {
		$apiKey = $_POST['data']['td_helpdesk_api_key'] ?? '';

		if (empty($apiKey)) {
			echo json_encode( [ 'status' => 'false', 'data' => [] ] );
			die();
		}

		$assistants = get_transient( 'thrivedesk_assistants' );
		if ( $assistants ) {
			echo json_encode( [ 'status' => 'true', 'data' => $assistants ] );
			die();
		}

		$assistants = $this->get_assistants( $apiKey );

		if ( $assistants['assistants'] ) {
			set_transient( 'thrivedesk_assistants', $assistants, 60 * 30 );
			echo json_encode( [ 'status' => 'true', 'data' => $assistants ] );
		} else {
			echo json_encode( [ 'status' => 'false', 'data' => [] ] );
		}
		die();
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
                n.type="text/javascript",n.async=!0,n.src="https://assistant.thrivedesk.com/bootloader.js?"+Date.now(),
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

	public function get_assistants(  $apiKey = '' ) {
		$apiService = new TDApiService();
		if ( $apiKey ) {
			$apiService->setApiKey( $apiKey );
		}

		return $apiService->getRequest( THRIVEDESK_API_URL . '/v1/assistants' );
	}


	/**
	 * retrieve the assistant list
	 * @return array|mixed|object
	 */
	public static function assistants()
	{
		$api_key = get_option('td_helpdesk_settings')['td_helpdesk_api_key'] ?? '';
		if ( empty( $api_key ) ) {
			return [];
		}
		$assistants = get_transient( 'thrivedesk_assistants' );


		if ( $assistants ) {
			return $assistants['assistants'] ?? [];
		}

		$assistants = ( new Assistant )->get_assistants();

		if ( $assistants['assistants'] ) {
			set_transient( 'thrivedesk_assistants', $assistants, 60 * 30 );
		}

		return $assistants['assistants'] ?? [];
	}

	public static function get_assistant_settings()
	{
		$assistant_settings = get_option('td_assistant_settings');

		return $assistant_settings ?? [];
	}
}
