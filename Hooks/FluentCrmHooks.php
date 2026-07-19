<?php
/**
 * FluentCRM integration hooks.
 *
 * @package ThriveDesk
 */

namespace ThriveDesk;

/**
 * Registers ThriveDesk as a support-ticket provider inside FluentCRM.
 */
class FluentCrmHooks {

	/**
	 * The single instance of this class.
	 *
	 * @var FluentCrmHooks|null
	 */
	private static $instance = null;

	/**
	 * Load the FluentCRM hooks.
	 */
	public function __construct() {
		$this->plugin_load();
	}

	/**
	 * Retrieve the singleton instance.
	 *
	 * @return FluentCrmHooks|null
	 * @since 0.7.0
	 */
	public static function instance(): ?FluentCrmHooks {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof FluentCrmHooks ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Filters for ThriveDesk conversation with fluentCrm
	 *
	 * @since 0.7.0
	 */
	public function plugin_load() {
		add_action(
			'fluentcrm_loaded',
			function () {

				$app = FluentCrm();

				$app->addCustomFilter(
					'support_tickets_providers',
					function ( $providers ) {
						$providers['thrivedesk'] = array(
							'title' => __( 'Support Tickets by ThriveDesk', 'thrivedesk' ),
							'name'  => __( 'ThriveDesk', 'thrivedesk' ),
						);
						return $providers;
					}
				);

				$app->addCustomFilter(
					'get_support_tickets_thrivedesk',
					function ( $data, $subscriber ) {
						global $wpdb;
						$table_name = $wpdb->prefix . THRIVEDESK_DB_TABLE_CONVERSATION;

						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
						$row = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

						if ( ! $row ) {
							return array();
						}

						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
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

						// Try to get from cache first.
						$cache_key        = 'td_conversations_' . md5( $subscriber->email );
						$td_conversations = wp_cache_get( $cache_key, 'thrivedesk' );

						if ( false === $td_conversations ) {
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
							$td_conversations = $wpdb->get_results(
								$wpdb->prepare(
									// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is a trusted prefixed identifier, not user input.
									"SELECT * FROM $table_name WHERE contact = %s AND deleted_at IS NULL",
									$subscriber->email
								)
							);

							// Cache for 5 minutes.
							wp_cache_set( $cache_key, $td_conversations, 'thrivedesk', 300 );
						}

						$formatted_tickets = array();

						foreach ( $td_conversations as $td_conversation ) {
							$conversation_url = THRIVEDESK_APP_URL . '/conversations/' . $td_conversation->id;

							$action_html         = '<a target="_blank" href="' . $conversation_url . '">View conversation</a>';
							$formatted_tickets[] = array(
								'id'           => '#' . $td_conversation->ticket_id,
								'title'        => $td_conversation->title,
								'status'       => $td_conversation->status,
								'Submitted at' => gmdate( $td_conversation->created_at ),
								'action'       => $action_html,
							);
						}

						return array(
							'total' => count( $td_conversations ),
							'data'  => $formatted_tickets,
						);
					},
					10,
					2
				);
			}
		);
	}
}
