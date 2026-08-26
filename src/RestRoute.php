<?php

namespace ThriveDesk;

use WpFluent\Exception;

if (!defined('ABSPATH')) {
	exit;
}

class RestRoute
{
	/**
	 * @var $instance
	 * The single instance of this class
	 * @since 0.9.0
	 */
	private static $instance;

	/**
	 * REST namespace for every route this plugin registers.
	 *
	 * A bare top-level namespace with no vendor prefix and no version (the doc
	 * search used to register under `td-search-query`) is a wordpress.org
	 * review flag and collides freely with other plugins.
	 */
	public const REST_NAMESPACE = 'thrivedesk/v1';

	/**
	 * define post limit when searching
	 */
	public const POST_TITLE_LIMIT = 20;

	/**
	 * Cap the doc-search result set so the endpoint can't be driven into an
	 * unbounded query on stores with a large posts_per_page.
	 */
	public const SEARCH_RESULT_LIMIT = 20;

	/** Main RestRoute
	 *
	 * @return RestRoute
	 * @since 0.9.0
	 */
	public static function instance()
	{
		if (null === self::$instance) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	private function __construct()
	{
		add_action('rest_api_init', array($this, 'td_routes'));
	}

	/**
	 * ThriveDesk conversation rest route
	 *
	 * @since 0.9.0
	 */
	public function td_routes()
	{
		register_rest_route(self::REST_NAMESPACE, '/conversations/contact/(?P<id>\d+)', array(
			'methods'             => 'get',
			'callback'            => array($this, 'get_thrivedesk_conversations'),
			'permission_callback' => function () {
				return current_user_can('manage_options');
			}
		));

		// Doc search for the portal ticket modal.
		//
		// The modal only ever renders for a logged-in customer, so this route
		// has no anonymous consumer. Leaving it open handed the world an
		// unauthenticated, unthrottled, uncached LIKE '%...%' scan over the
		// posts table.
		//
		// The caller must send X-WP-Nonce: without it WordPress's
		// rest_cookie_check_errors() calls wp_set_current_user(0) on a
		// cookie-authenticated REST request, and is_user_logged_in() below
		// would then be false even for a signed-in customer.
		register_rest_route(self::REST_NAMESPACE, '/docs', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array($this, 'get_search_data'),
			'permission_callback' => 'is_user_logged_in',
			'args'                => array(
				'query_string' => array(
					'type'              => 'string',
					'required'          => false,
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		));
	}


	/**
	 * @param $data
	 *
	 * @return array|\WP_REST_Response
	 *
	 * @since 0.9.0
	 */
	public function get_thrivedesk_conversations($data)
	{
		if (!isset($data['id'])) {
			return new \WP_REST_Response(['message' => 'Invalid request format'], 401);
		}

		if (!class_exists('BWF_Contacts')) {
			return new \WP_REST_Response(['message' => 'Class BWF_Contacts does not exists'], 401);
		}

		$contact_obj = \BWF_Contacts::get_instance();

		$contact = $contact_obj->get_contact_by('id', $data['id']);

		if (!absint($contact->get_id()) > 0) {
			return new \WP_REST_Response(['message' => 'Contact does not exists'], 401);
		}

		$contact_email = $contact->get_email();

		global $wpdb;
		$table_name = $wpdb->prefix . THRIVEDESK_DB_TABLE_CONVERSATION;

		$row = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

		if (!$row) {
			return [];
		}

		$column = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME = %s",
			$table_name,
			'deleted_at'
		));

		if (!$column) {
			return [];
		}

		// Try to get from cache first
		$cache_key = 'td_conversations_' . md5($contact_email);
		$td_conversations = wp_cache_get($cache_key, 'thrivedesk');
		
		if (false === $td_conversations) {
			$td_conversations = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table_name WHERE contact = %s AND deleted_at IS NULL",
					$contact_email
				)
			);
			
			// Cache for 5 minutes
			wp_cache_set($cache_key, $td_conversations, 'thrivedesk', 300);
		}

		$formattedTickets = [];

		// Every field here came off the inbound sync payload, so it crosses the
		// SaaS trust boundary. sanitize_text_field() on write strips tags but
		// leaves quotes and entities, and the consumer renders these, so
		// escape them all rather than only the id inside the URL.
		foreach ($td_conversations as $td_conversation) {
			$formattedTickets[] = [
				'id'           => '#' . esc_html($td_conversation->ticket_id),
				'title'        => esc_html($td_conversation->title),
				'status'       => esc_html(td_conversation_status($td_conversation->status)),
				'submitted_at' => esc_html($td_conversation->created_at),
				'action'       => esc_url(THRIVEDESK_APP_URL . '/conversations/' . $td_conversation->id),
			];
		}

		return new \WP_REST_Response($formattedTickets, 200);
	}

	/**
	 * Doc search used by the new ticket modal.
	 *
	 * @param \WP_REST_Request $request
	 * @return array
	 */
	public function get_search_data( $request ): array {
		// get_param() picks up the query whether the body came in as JSON,
		// form-encoded, or a query string, and the route's registered args
		// have already run it through sanitize_text_field. Sanitizing again
		// here keeps the callback safe when it is invoked directly.
		$query_string = sanitize_text_field( (string) $request->get_param( 'query_string' ) );

		$settings = get_option( 'td_helpdesk_settings', [] );
		$select_post_types = $settings['td_helpdesk_post_types'] ?? '';

		if (empty($select_post_types)) {
			return [
				'data' => []
			];
		}

		$x_query = new \WP_Query(
			array(
				's'              => $query_string,
				'post_type'      => $select_post_types,
				'post_status'    => 'publish',
				'posts_per_page' => self::SEARCH_RESULT_LIMIT,
				'no_found_rows'  => true,
			)
		);

		$search_posts = [];
		while ($x_query->have_posts()) :
			$x_query->the_post();
			$post_categories_array = get_the_category(get_the_ID());
			$post_title = html_entity_decode(get_the_title(), ENT_NOQUOTES, 'UTF-8');
			$search_posts[] = [
				'id'            => get_the_ID(),
				'title'         => $post_title,
				'excerpt'       => strip_tags(get_the_excerpt()),
				'categories'    => count($post_categories_array) ? implode(' - ', wp_list_pluck($post_categories_array, 'name')) : 'Category not available',
				'link'          => get_the_permalink(),
			];

		endwhile;

		wp_reset_postdata();

		if (empty($search_posts)) {
			return [
				'data' => []
			];
		} else {
			return [
				'count' => count($search_posts) . ' result found',
				'data'  => $search_posts
			];
		}
	}

	/**
	 * @param $title
	 *
	 * @return string
	 */
	public function get_truncated_post_title($title): string
	{
		if (mb_strwidth($title, 'UTF-8') > self::POST_TITLE_LIMIT) {
			return rtrim(mb_strimwidth($title, 0, self::POST_TITLE_LIMIT, '', 'UTF-8')) . '...';
		}
		return $title;
	}
}
