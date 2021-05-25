<?php

/**
 * Render a view file
 *
 * @since 0.0.1
 * @access public
 * @param string $file view file name to render
 * @param array $data data to use on view file
 * @return void
 */
function thrivedesk_view(string $file, array $data = [])
{
    $file = THRIVEDESK_DIR . '/includes/views/' . $file . '.php';
    if (file_exists($file)) {
        if (is_array($data)) {
            extract($data);
        }

        require_once $file;
    } else {
        wp_die('View not found');
    }
}

/**
 * Thrivedesk options
 *
 * @since 0.0.1
 * @access public
 * @return void
 */
function thrivedesk_options()
{
    $options = get_option('thrivedesk_options', []);

    return is_array($options) ? $options : [];
}

/**
 * get all post types array
 * @since 0.6.2
 * @access public
 * @return array
 */
if(!function_exists('td_get_all_post_types_arr')){
    function td_get_all_post_types_arr(){
        $args = array(
            'public'   => true,
            'show_in_rest' => true
         );
           
        $output = 'names'; 
        $operator = 'and'; 
        $post_types = get_post_types( $args, $output, $operator );
    
        return $post_types;
    }
}

/**
 * @since 0.6.2
 * @param string $query_string for search among all post type
 * @access public 
 * @return JSON
 */
if(!function_exists('td_get_search_result')){
    function td_get_search_result($query_string){
        $x_query = new WP_Query( 
            array( 
                's' => $query_string,
                'post_type' => get_option('thrivedesk_post_type_sync_option')
            ) 
        );
        $search_posts = [];
        while($x_query->have_posts()):
            $x_query->the_post();
            $search_posts[get_the_ID()] = [
                'id'    => get_the_ID(),
                'title' => get_the_title(),
                'link'  => get_the_permalink(),
                'excerpt' => get_the_excerpt(),
            ];
            
        endwhile;
        if(empty($search_posts)){
            return [
                'data' => 'nothing found'
            ];
        }else{
            return [
                'count' => count($search_posts). ' result found',
                'data'  =>  $search_posts 
            ];
        }
        wp_reset_query();
    }
}