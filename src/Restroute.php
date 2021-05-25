<?php 
namespace ThriveDesk;

if(!defined('ABSPATH')){
    exit;
}
class Restroute {
    private static $instance;

    public static function instance(){
        if(null === self::$instance ){
            self::$instance = new self;
        }
        return self::$instance;
    }
    private function __construct(){

        add_action( 'rest_api_init', array($this, 'td_routes') );
    }
    public function td_routes(){
        register_rest_route('thrivedesk', '/search', array(
            'methods' => 'get',
            'callback' => array($this, 'td_pull_site_search_data')
        ));
    }
    public function td_pull_site_search_data($request){
        $query_string_param = $request->get_params('query');
        
        $search_result = td_get_search_result($query_string_param['query']);
        
        $site_data = [
            'search_result' => $search_result,
        ];

        return new \WP_REST_Response($site_data, 200);

    }
}

?>