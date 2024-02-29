<?php

namespace ThriveDesk\KnowledgeBase;

use ThriveDesk\Services\TDApiService;

class KnowledgeBase
{
    private static $instance = null;

    public function __construct(){}

    public static function instance(): KnowledgeBase
    {
        if (!isset(self::$instance) && !(self::$instance instanceof KnowledgeBase)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function get_knowledgebase($apiKey = '')
    {
        $apiService = new TDApiService();
        if(empty($apiKey)) {
            $apiService->setApiKey($apiKey);
        }

        return $apiService->getRequest( THRIVEDESK_API_URL . '/v1/knowledgebases' );
    }


    public static function knowledgebase(){
        $api_key = get_td_helpdesk_options()['td_helpdesk_api_key'] ?? '';
        if (empty($api_key)) {
            return [];
        }

        $knowledgebase = ( new KnowledgeBase )->get_knowledgebase($api_key);

        if ($knowledgebase['data']) {
            set_transient('thrivedesk_knowledgebase', $knowledgebase['data'], 60 * 30);

            return $knowledgebase['data'];
        } else {
            return [];
        }
    }


}
