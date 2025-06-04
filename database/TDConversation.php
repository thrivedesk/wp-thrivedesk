<?php

/**
 * ThriveDesk Conversation Database Handler.
 *
 * @package ThriveDesk
 * @since 0.0.1
 */

/**
 * Class TDConversation for handling conversation database operations.
 *
 * @since 0.0.1
 */
class TDConversation {

	/**
	 * Initialize conversation database operations.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public static function init() {
		global $wpdb;

		// Create table if not exists.
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . THRIVEDESK_DB_TABLE_CONVERSATION ) );

		if ( $result !== $wpdb->prefix . THRIVEDESK_DB_TABLE_CONVERSATION ) {
			self::create_conversation_table();
		}
	}

	/**
	 * Create conversation table.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	private static function create_conversation_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . THRIVEDESK_DB_TABLE_CONVERSATION;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			conversation_id varchar(255) NOT NULL,
			customer_email varchar(255) NOT NULL,
			customer_name varchar(255) DEFAULT '' NOT NULL,
			subject text DEFAULT '' NOT NULL,
			message longtext DEFAULT '' NOT NULL,
			status varchar(50) DEFAULT 'open' NOT NULL,
			priority varchar(20) DEFAULT 'normal' NOT NULL,
			tags text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY conversation_id (conversation_id),
			KEY customer_email (customer_email),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Update database version.
		update_option( OPTION_THRIVEDESK_DB_VERSION, THRIVEDESK_DB_VERSION );
	}

	/**
	 * Insert a new conversation.
	 *
	 * @param array $data Conversation data.
	 * @return int|false The conversation ID on success, false on failure.
	 * @since 0.0.1
	 */
	public static function insert( $data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . THRIVEDESK_DB_TABLE_CONVERSATION;

		$defaults = array(
			'conversation_id' => '',
			'customer_email'  => '',
			'customer_name'   => '',
			'subject'         => '',
			'message'         => '',
			'status'          => 'open',
			'priority'        => 'normal',
			'tags'            => '',
		);

		$data = wp_parse_args( $data, $defaults );

		// Validate required fields.
		if ( empty( $data['conversation_id'] ) || empty( $data['customer_email'] ) ) {
			return false;
		}

		$result = $wpdb->insert(
			$table_name,
			array(
				'conversation_id' => sanitize_text_field( $data['conversation_id'] ),
				'customer_email'  => sanitize_email( $data['customer_email'] ),
				'customer_name'   => sanitize_text_field( $data['customer_name'] ),
				'subject'         => sanitize_text_field( $data['subject'] ),
				'message'         => wp_kses_post( $data['message'] ),
				'status'          => sanitize_text_field( $data['status'] ),
				'priority'        => sanitize_text_field( $data['priority'] ),
				'tags'            => sanitize_text_field( $data['tags'] ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get conversations by customer email.
	 *
	 * @param string $email Customer email.
	 * @param int    $limit Number of conversations to retrieve.
	 * @return array Array of conversations.
	 * @since 0.0.1
	 */
	public static function get_by_email( $email, $limit = 10 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . THRIVEDESK_DB_TABLE_CONVERSATION;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE customer_email = %s ORDER BY created_at DESC LIMIT %d",
				sanitize_email( $email ),
				absint( $limit )
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Update conversation status.
	 *
	 * @param string $conversation_id Conversation ID.
	 * @param string $status          New status.
	 * @return bool True on success, false on failure.
	 * @since 0.0.1
	 */
	public static function update_status( $conversation_id, $status ) {
		global $wpdb;

		$table_name = $wpdb->prefix . THRIVEDESK_DB_TABLE_CONVERSATION;

		$result = $wpdb->update(
			$table_name,
			array( 'status' => sanitize_text_field( $status ) ),
			array( 'conversation_id' => sanitize_text_field( $conversation_id ) ),
			array( '%s' ),
			array( '%s' )
		);

		return false !== $result;
	}
}
