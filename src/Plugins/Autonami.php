<?php


namespace ThriveDesk\Plugins;

use ThriveDesk\Plugin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Autonami extends Plugin {
	/** The single instance of this class */
	private static $instance = null;

	public const TYPE_CREATE_CONVERSATION        = 'create_conversation';
	public const TYPE_DELETE_CONVERSATION        = 'delete_conversation';
	public const TYPE_FORCE_DELETE_CONVERSATION  = 'force_delete_conversation';
	public const TYPE_RESTORE_CONVERSATION       = 'restore_conversation';
	public const TYPE_UPDATE_CONVERSATION_STATUS = 'update_conversation_status';

	public const DB_TABLE_TD_CONVERSATION = 'td_conversations';

	public function accepted_statuses(): array {
		return array();
	}

	/**
	 * Check if plugin active or not
	 *
	 * @return boolean
	 * @since 0.9.0
	 */
	public static function is_plugin_active(): bool {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( class_exists( 'WooFunnel_Loader' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if customer exist or not
	 *
	 * @return boolean
	 * @since 0.9.0
	 */
	public function is_customer_exist(): bool {
		if ( ! $this->customer_email ) {
			return false;
		}

		/**
		 * Autonami Pro plugin is required
		 */
		if ( ! class_exists( 'BWF_Contacts' ) ) {
			return false;
		}

		/**
		 * Contact class object
		 */
		$contact_obj = \BWF_Contacts::get_instance();

		$contact = $contact_obj->get_contact_by( 'email', $this->customer_email );

		if ( abs( $contact->get_id() ) === 0 ) {
			return false;
		}

		$this->customer = $contact;

		return true;
	}

	/**
	 * Main Autonami Instance.
	 *
	 * Ensures that only one instance of Autonami exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @return Autonami|null
	 * @access public
	 * @since  0.9.0
	 */
	public static function instance(): ?Autonami {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Autonami ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function connect() {
		$thrivedesk_options                          = get_option( 'thrivedesk_options', array() );
		$thrivedesk_options['autonami']              = $thrivedesk_options['autonami'] ?? array();
		$thrivedesk_options['autonami']['connected'] = true;

		update_option( 'thrivedesk_options', $thrivedesk_options );
	}

	public function disconnect() {
		$thrivedesk_options             = get_option( 'thrivedesk_options', array() );
		$thrivedesk_options['autonami'] = $thrivedesk_options['autonami'] ?? array();
		$thrivedesk_options['autonami'] = array(
			'api_token' => '',
			'connected' => false,
		);

		update_option( 'thrivedesk_options', $thrivedesk_options );
	}

	/**
	 * prepare Autonami customer data
	 *
	 * @return array
	 */
	public function prepare_data(): array {
		return $this->get_customer();
	}

	/**
	 * Get the customer orders
	 *
	 * @return array
	 */
	public function get_orders(): array {
		return array();
	}

	public function get_plugin_data( string $key = '' ) {
		$thrivedesk_options = thrivedesk_options();
		$options            = $thrivedesk_options['autonami'] ?? array();

		return $key ? ( $options[ $key ] ?? '' ) : $options;
	}

	/**
	 * get customer tags
	 *
	 * @param $crm_contact
	 *
	 * @return array
	 *
	 * @since 0.9.0
	 */
	public function get_customer_tags( $crm_contact ): array {
		$tags = array();

		if ( ! class_exists( 'BWFCRM_Lists' ) ) {
			return $tags;
		}

		$tag_object = \BWFCRM_Tag::get_tags( $crm_contact->get_tags() );

		foreach ( $tag_object as $tag ) {
			array_push( $tags, $tag['name'] );
		}

		return $tags;
	}

	/**
	 * get customer lists
	 *
	 * @param $crm_contact
	 *
	 * @return array
	 * @since 0.9.0
	 */
	public function get_customer_lists( $crm_contact ): array {
		$lists = array();

		if ( ! class_exists( 'BWFCRM_Lists' ) ) {
			return $lists;
		}

		$list_object = \BWFCRM_Lists::get_lists( $crm_contact->get_lists() );

		foreach ( $list_object as $list ) {
			array_push( $lists, $list['name'] );
		}

		return $lists;
	}

	/**
	 * Get the customer data
	 *
	 * @return array
	 * @since 0.9.0
	 */
	public function get_customer(): array {
		$tags           = array();
		$lists          = array();
		$date_of_birth  = '';
		$address_line_1 = '';
		$address_line_2 = '';

		if ( class_exists( 'BWFCRM_Contact' ) ) {
			/** Passing Contact object as argument */
			$crm_contact = new \BWFCRM_Contact( $this->customer );

			$crm_contact->get_dob();

			$tags = $this->get_customer_tags( $crm_contact );

			$lists = $this->get_customer_lists( $crm_contact );

			$date_of_birth = $crm_contact->get_dob() ?? '';

			$address_line_1 = $crm_contact->get_address_1() ?? '';

			$address_line_2 = $crm_contact->get_address_2() ?? '';
		}

		return array(
			'id'             => abs( $this->customer->get_id() ) ?? 0,
			'wpid'           => $this->customer->get_wpid(),
			'email'          => $this->customer->get_email() ?? '',
			'first_name'     => $this->customer->get_f_name() ?? '',
			'last_name'      => $this->customer->get_l_name() ?? '',
			'phone'          => $this->customer->get_contact_no() ?? '',
			'address_line_1' => $address_line_1,
			'address_line_2' => $address_line_2,
			'country'        => $this->customer->get_country() ?? '',
			'state'          => $this->customer->get_state() ?? '',
			'timezone'       => $this->customer->get_timezone() ?? '',
			'created_at'     => ! empty( $this->customer->get_creation_date() ) ? get_date_from_gmt( $this->customer->get_creation_date() ) : '',
			'last_modified'  => ! empty( $this->customer->get_last_modified() ) ? get_date_from_gmt( $this->customer->get_last_modified() ) : '',
			'source'         => $this->customer->get_source(),
			'contact_type'   => $this->customer->get_type(),
			'date_of_birth'  => $date_of_birth ? date( 'd M Y', strtotime( $date_of_birth ) ) : '',
			'status'         => $this->customer->get_status(),
			'lists'          => $lists,
			'tags'           => $tags,
		);
	}

	/**
	 * create new contact
	 *
	 * @param  string $contactName
	 *
	 * @return bool
	 * @since 0.9.0
	 */
	public function create_new_contact( string $contactName ): bool {
		if ( ! $this->customer_email ) {
			return false;
		}

		/** Autonami Pro plugin is required */
		if ( ! class_exists( 'BWFCRM_Contact' ) ) {
			return false;
		}

		/** Contact class object */
		$contact_obj = \BWF_Contacts::get_instance();

		$contact = $contact_obj->get_contact_by( 'email', $this->customer_email );

		if ( abs( $contact->get_id() ) ) {
			return false;
		}

		! empty( $this->customer_email ) && $contact->set_email( $this->customer_email );

		$first_name = '';
		$last_name  = '';

		$name_array = explode( ' ', trim( $contactName ) );
		if ( sizeof( $name_array ) < 2 ) {
			$first_name = trim( $contactName );
		} else {
			$last_name  = array_pop( $name_array );
			$first_name = implode( ' ', $name_array );
		}

		! empty( $first_name ) && $contact->set_f_name( $first_name );
		! empty( $last_name ) && $contact->set_f_name( $last_name );

		$contact->save();

		return false;
	}

	/**
	 * Sync ThriveDesk conversation with Autonami
	 *
	 * @param  string $syncType
	 * @param  array  $extra
	 *
	 * @since 0.9.0
	 */
	public function sync_conversation_with_autonami( string $syncType, array $extra = array() ): void {
		global $wpdb;
		$table_name = $wpdb->prefix . self::DB_TABLE_TD_CONVERSATION;

		switch ( $syncType ) {
			case self::TYPE_CREATE_CONVERSATION:
				$extra['conversation'] && (
				$wpdb->replace( $table_name, $extra['conversation'] )
				);

				$extra['create_new_contact'] && (
				$this->create_new_contact( $extra['contact_name'] ?? '' )
				);
				break;
			case self::TYPE_DELETE_CONVERSATION:
				if ( isset( $extra['conversation_ids'] ) && count( $extra['conversation_ids'] ) ) {
					foreach ( $extra['conversation_ids'] as $conversationId ) {
						$wpdb->update(
							$table_name,
							array(
								'deleted_at' => current_time( 'mysql' ),
							),
							array(
								'id'       => $conversationId,
								'inbox_id' => $extra['inbox_id'] ?? '',
							)
						);
					}
				}
				break;
			case self::TYPE_FORCE_DELETE_CONVERSATION:
				if ( isset( $extra['conversation_ids'] ) && count( $extra['conversation_ids'] ) ) {
					foreach ( $extra['conversation_ids'] as $conversationId ) {
						$wpdb->delete(
							$table_name,
							array(
								'id'       => $conversationId,
								'inbox_id' => $extra['inbox_id'] ?? '',
							)
						);
					}
				}
				break;
			case self::TYPE_RESTORE_CONVERSATION:
				if ( isset( $extra['conversation_ids'] ) && count( $extra['conversation_ids'] ) ) {
					foreach ( $extra['conversation_ids'] as $conversationId ) {
						$wpdb->update(
							$table_name,
							array(
								'deleted_at' => null,
							),
							array(
								'id'       => $conversationId,
								'inbox_id' => $extra['inbox_id'] ?? '',
							)
						);
					}
				}
				break;
			case self::TYPE_UPDATE_CONVERSATION_STATUS:
				$extra['status'] && $extra['conversation_id'] && (
				$wpdb->update(
					$table_name,
					array(
						'status'     => $extra['status'],
						'updated_at' => current_time( 'mysql' ),
					),
					array(
						'id'       => $extra['conversation_id'],
						'inbox_id' => $extra['inbox_id'],
					)
				)
				);
				break;
		}
	}
}
