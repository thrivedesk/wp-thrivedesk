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
	 * define post limit when searching
	 */
	public const POST_TITLE_LIMIT = 20;

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
		register_rest_route('thrivedesk/v1', '/conversations/contact/(?P<id>\d+)', array(
			'methods'             => 'get',
			'callback'            => array($this, 'get_thrivedesk_conversations'),
			'permission_callback' => function () {
				return current_user_can('manage_options');
			}
		));

		// doc search result route
		register_rest_route('td-search-query', '/docs', array(
			'methods'             => 'post',
			'callback'            => array($this, 'get_search_data'),
			'permission_callback' => function () {
				return true;
			}
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

		foreach ($td_conversations as $td_conversation) {
			$formattedTickets[] = [
				'id'           => '#' . $td_conversation->ticket_id,
				'title'        => $td_conversation->title,
				'status'       => $td_conversation->status,
				'submitted_at' => date($td_conversation->created_at),
				'action'       => THRIVEDESK_APP_URL . '/conversations/' . $td_conversation->id,
			];
		}

		return new \WP_REST_Response($formattedTickets, 200);
	}

	/**
	 * doc search on new ticket modal
	 * @return array
	 */
	public function get_search_data(): array {
		$query_string = $_POST['query_string'] ?? '';
		$select_post_types = get_option('td_helpdesk_settings')['td_helpdesk_post_types'];

		if (empty($select_post_types)) {
			return [
				'data' => []
			];
		}

		$x_query = new \WP_Query(
			array(
				's'         => $query_string,
				'post_type' => $select_post_types
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

		wp_reset_query();

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
