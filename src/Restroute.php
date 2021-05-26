<?php 
namespace ThriveDesk;
use \ThriveDesk\Plugins\PostSync;

if(!defined('ABSPATH')){
    exit;
}
class Restroute {
    /**
     * The single instance of this class
     */
    private static $instance;
    /**
     * The single instance of postsync
     */
    private $postsync;
    /**
     * Main RestRoute Instance.
     * @return PostSync|null
     * @access public
     * @since 0.7.0
     */
    public static function instance(){
        if(null === self::$instance ){
            self::$instance = new self;
        }
        return self::$instance;
    }
    
    private function __construct(){
        $this->postsync = PostSync::instance();
        if($this->postsync->get_plugin_data('connected')):
            add_action( 'rest_api_init', array($this, 'post_query_routes') );
        endif;
    }
    /**
     * @since 0.6.2
     * @access public 
     * @return void
     */
    public function post_query_routes(){
        register_rest_route('thrivedesk', '/search', array(
            'methods' => 'get',
            'callback' => array($this, 'get_post_search_data')
        ));
    }
    /**
     * @since 0.6.2
     * @param string $request for search among all post type
     * @access public 
     * @return JSON
     */
    public function get_post_search_data($request){
        $query_string_param = $request->get_params('query');
        
        $search_result = $this->postsync->get_post_search_result($query_string_param['query']);
        
        $site_data = [
            'search_result' => $search_result,
        ];

        return new \WP_REST_Response($site_data, 200);

    }
}

?>