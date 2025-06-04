/**
 * RestRoute class for handling ThriveDesk REST API endpoints.
 *
 * @package ThriveDesk
 * @since 0.9.0
 */

namespace ThriveDesk;

use WpFluent\Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RestRoute {

	/**
	 * The single instance of this class.
	 *
	 * @var RestRoute
	 * @since 0.9.0
	 */
	private static $instance;

	/**
	 * Define post limit when searching.
	 *
	 * @var int
	 */
	public const POST_TITLE_LIMIT = 20;

	/**
	 * Main RestRoute instance.
	 *
	 * @return RestRoute
	 * @since 0.9.0
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.9.0
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'td_routes' ) );
	}

	/**
	 * Register ThriveDesk REST API routes.
	 *
	 * @since 0.9.0
	 */
	public function td_routes() {
		register_rest_route(
			'thrivedesk/v1',
			'/conversations/contact/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_thrivedesk_conversations' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'td-search-query',
			'/docs',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'get_search_data' ),
				'permission_callback' => function () {
					return true;
				},
			)
		);
	}

	/**
	 * Get ThriveDesk conversations for a contact.
	 *
	 * @param \WP_REST_Request $data The request data.
	 * @return \WP_REST_Response
	 * @since 0.9.0
	 */
	public function get_thrivedesk_conversations( $data ) {
		if ( ! isset( $data['id'] ) ) {
			return new \WP_REST_Response( array( 'message' => 'Invalid request format' ), 401 );
		}

		if ( ! class_exists( 'BWF_Contacts' ) ) {
			return new \WP_REST_Response( array( 'message' => 'Class BWF_Contacts does not exists' ), 401 );
		}

		$contact_obj = \BWF_Contacts::get_instance();
		$contact = $contact_obj->get_contact_by( 'id', absint( $data['id'] ) );

		if ( ! absint( $contact->get_id() ) > 0 ) {
			return new \WP_REST_Response( array( 'message' => 'Contact does not exists' ), 401 );
		}

		$contact_email = sanitize_email( $contact->get_email() );

		global $wpdb;
		$table_name = $wpdb->prefix . THRIVEDESK_DB_TABLE_CONVERSATION;

		// Check if table exists using prepared statement
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		if ( ! $table_exists ) {
			return array();
		}

		// Check if deleted_at column exists
		$column = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME = %s',
				$table_name,
				'deleted_at'
			)
		);

		if ( ! $column ) {
			return array();
		}

		// Get conversations using prepared statement
		$td_conversations = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE contact = %s AND deleted_at IS NULL',
				$table_name,
				$contact_email
			)
		);

		$formatted_tickets = array();

		foreach ( $td_conversations as $td_conversation ) {
			$formatted_tickets[] = array(
				'id'           => '#' . absint( $td_conversation->ticket_id ),
				'title'        => sanitize_text_field( $td_conversation->title ),
				'status'       => sanitize_text_field( $td_conversation->status ),
				'submitted_at' => gmdate( 'Y-m-d H:i:s', strtotime( $td_conversation->created_at ) ),
				'action'       => esc_url( THRIVEDESK_APP_URL . '/conversations/' . absint( $td_conversation->id ) ),
			);
		}

		return new \WP_REST_Response( $formatted_tickets, 200 );
	}

	/**
	 * Get search data for documentation.
	 *
	 * @return array
	 * @since 0.9.0
	 */
	public function get_search_data(): array {
		// Verify nonce
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'td_search_nonce' ) ) {
			return array(
				'data' => array(),
			);
		}

		$query_string = isset( $_POST['query_string'] ) ? sanitize_text_field( wp_unslash( $_POST['query_string'] ) ) : '';
		$settings = get_option( 'td_helpdesk_settings' );
		$select_post_types = isset( $settings['td_helpdesk_post_types'] ) ? $settings['td_helpdesk_post_types'] : array();

		if ( empty( $select_post_types ) ) {
			return array(
				'data' => array(),
			);
		}

		$x_query = new \WP_Query(
			array(
				's'         => $query_string,
				'post_type' => $select_post_types,
			)
		);

		$search_posts = array();
		while ( $x_query->have_posts() ) :
			$x_query->the_post();
			$post_categories_array = get_the_category( get_the_ID() );
			$post_title = html_entity_decode( get_the_title(), ENT_NOQUOTES, 'UTF-8' );
			$search_posts[] = array(
				'id'         => absint( get_the_ID() ),
				'title'      => sanitize_text_field( $post_title ),
				'excerpt'    => wp_strip_all_tags( get_the_excerpt() ),
				'categories' => count( $post_categories_array ) ? implode( ' - ', wp_list_pluck( $post_categories_array, 'name' ) ) : 'Category not available',
				'link'       => esc_url( get_the_permalink() ),
			);
		endwhile;

		wp_reset_postdata();

		if ( empty( $search_posts ) ) {
			return array(
				'data' => array(),
			);
		}

		return array(
			'count' => count( $search_posts ) . ' result found',
			'data'  => $search_posts,
		);
	}

	/**
	 * Get truncated post title.
	 *
	 * @param string $title The post title to truncate.
	 * @return string
	 * @since 0.9.0
	 */
	public function get_truncated_post_title( $title ): string {
		if ( mb_strwidth( $title, 'UTF-8' ) > self::POST_TITLE_LIMIT ) {
			return rtrim( mb_strimwidth( $title, 0, self::POST_TITLE_LIMIT, '', 'UTF-8' ) ) . '...';
		}
		return $title;
	}
}
