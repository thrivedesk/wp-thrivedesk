<?php


namespace ThriveDesk\Plugins;

use ThriveDesk\Plugin;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

final class FluentCRM extends Plugin {
	/** The single instance of this class */
	private static $instance = null;

	public const TYPE_CREATE_CONVERSATION        = 'create_conversation';
	public const TYPE_DELETE_CONVERSATION        = 'delete_conversation';
	public const TYPE_FORCE_DELETE_CONVERSATION  = 'force_delete_conversation';
	public const TYPE_RESTORE_CONVERSATION       = 'restore_conversation';
	public const TYPE_UPDATE_CONVERSATION_STATUS = 'update_conversation_status';

	public const DB_TABLE_TD_CONVERSATION = 'td_conversations';

	public function accepted_statuses(): array {
		return [];
	}

	/**
	 * Check if plugin active or not
	 *
	 * @return boolean
	 * @since 0.7.0
	 */
	public static function is_plugin_active(): bool {
		include_once(ABSPATH . 'wp-admin/includes/plugin.php');
		if (is_plugin_active('fluent-crm/fluent-crm.php')) {
			return true;
		}

		return false;
	}

	/**
	 * Check if customer exist or not
	 *
	 * @return boolean
	 * @since 0.7.0
	 */
	public function is_customer_exist(): bool {
		if (!$this->customer_email) {
			return false;
		}

		if (!$this->customer && function_exists('FluentCrmApi')) {
			$contactApi     = FluentCrmApi('contacts');
			$this->customer = $contactApi->getContact($this->customer_email);
		}

		if (!$this->customer) {
			return false;
		}

		return true;
	}

	/**
	 * Main FluentCRM Instance.
	 *
	 * Ensures that only one instance of WooCommerce exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @return FluentCRM|null
	 * @access public
	 * @since  0.7.0
	 */
	public static function instance(): ?FluentCRM {
		if (!isset(self::$instance) && !(self::$instance instanceof FluentCRM)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function connect() {
		$thrivedesk_options                           = get_option('thrivedesk_options', []);
		$thrivedesk_options['fluentcrm']              = $thrivedesk_options['fluentcrm'] ?? [];
		$thrivedesk_options['fluentcrm']['connected'] = true;

		update_option('thrivedesk_options', $thrivedesk_options);
	}

	public function disconnect() {
		$thrivedesk_options              = get_option('thrivedesk_options', []);
		$thrivedesk_options['fluentcrm'] = $thrivedesk_options['fluentcrm'] ?? [];
		$thrivedesk_options['fluentcrm'] = [
			'api_token' => '',
			'connected' => false,
		];

		update_option('thrivedesk_options', $thrivedesk_options);
	}

	/**
	 * Get the customer orders
	 *
	 * @return array
	 */
	public function get_orders(): array {
		return [];
	}

	public function get_plugin_data(string $key = '') {
		$thrivedesk_options = thrivedesk_options();
		$options            = $thrivedesk_options['fluentcrm'] ?? [];

		return $key ? ($options[$key] ?? '') : $options;
	}

	/**
	 * get customer tags
	 *
	 * @return array
	 * @since 0.7.0
	 */
	public function get_customer_tags(): array {
		$tags = [];
		foreach ($this->customer->tags as $tag) {
			array_push($tags, $tag->title);
		}

		return $tags;
	}

	/**
	 * get customer lists
	 *
	 * @return array
	 * @since 0.7.0
	 */
	public function get_customer_lists(): array {
		$lists = [];
		foreach ($this->customer->lists as $list) {
			array_push($lists, $list->title);
		}

		return $lists;
	}

	/**
	 * Get the customer data
	 *
	 * @return array
	 * @since 0.7.0
	 */
	public function get_customer(): array {
		if (!$this->customer_email) {
			return [];
		}

		if (!$this->customer && function_exists('FluentCrmApi')) {
			$contactApi     = FluentCrmApi('contacts');
			$this->customer = $contactApi->getContact($this->customer_email);
		}

		$customer_formatted_country = $this->customer->country ?? '';

		if (function_exists('FluentCrm')) {
			$app       = FluentCrm();
			$countries = $app->applyFilters('fluentcrm-countries', []);

			foreach ($countries as $country) {
				if ($country['code'] == $this->customer->country) {
					$customer_formatted_country = $country['title'];
					break;
				}
			}
		}

		if (!$this->customer->id) {
			return [];
		}

		return [
			'id'             => $this->customer->id ?? '',
			'first_name'     => $this->customer->first_name ?? '',
			'last_name'      => $this->customer->last_name ?? '',
			'email'          => $this->customer->email ?? '',
			'phone'          => $this->customer->phone ?? '',
			'status'         => $this->customer->status ? ucfirst($this->customer->status) : '',
			'contact_type'   => $this->customer->contact_type ? ucfirst($this->customer->contact_type) : '',
			'tags'           => $this->get_customer_tags(),
			'lists'          => $this->get_customer_lists(),
			'photo'          => $this->customer->photo ?? '',
			'address_line_1' => $this->customer->address_line_1 ?? '',
			'address_line_2' => $this->customer->address_line_2 ?? '',
			'city'           => $this->customer->city ?? '',
			'state'          => $this->customer->state ?? '',
			'postal_code'    => $this->customer->postal_code ?? '',
			'country'        => $customer_formatted_country ?? '',
			'date_of_birth'  => $this->customer->date_of_birth ? date('d M Y',
				strtotime($this->customer->date_of_birth)) : '',
			'last_activity'  => $this->customer->last_activity ? date('d M Y',
				strtotime($this->customer->last_activity)) : '',
			'updated_at'     => $this->customer->updated_at ? date('d M Y',
				strtotime($this->customer->updated_at)) : '',
			'created_at'     => $this->customer->created_at ? date('d M Y', strtotime($this->customer->created_at)) : '',
		];
	}

	/**
	 * create new contact
	 *
	 * @param  string  $contactName
	 *
	 * @return bool
	 * @since 0.7.0
	 */
	public function create_new_contact(string $contactName): bool {
		if (function_exists('FluentCrmApi')) {
			$contactApi = FluentCrmApi('contacts');

			$contact = $contactApi->getContact($this->customer_email);

			if ($contact) {
				return true;
			}

			$first_name = '';
			$last_name  = '';

			$name_array = explode(" ", trim($contactName));
			if (sizeof($name_array) < 2) {
				$first_name = trim($contactName);
			} else {
				$last_name  = array_pop($name_array);
				$first_name = implode(" ", $name_array);
			}

			$data = [
				'email'      => $this->customer_email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
			];

			return $contactApi->createOrUpdate($data) ? true : false;
		}

		return false;
	}


	/**
	 * sync ThriveDesk conversation with FluentCrm
	 *
	 * @param  string  $syncType
	 * @param  array  $extra
	 *
	 * @since 0.8.4
	 */
	public function sync_conversation_with_fluentcrm(string $syncType, array $extra = []): void {
		global $wpdb;
		$table_name = $wpdb->prefix . self::DB_TABLE_TD_CONVERSATION;

		switch ($syncType) {
			case self::TYPE_CREATE_CONVERSATION:
				$extra['conversation'] && (
				$wpdb->replace($table_name, $extra['conversation'])
				);

				$extra['create_new_contact'] && (
				$this->create_new_contact($extra['contact_name'] ?? '')
				);
				break;
			case self::TYPE_DELETE_CONVERSATION:
				if (isset($extra['conversation_ids']) && count($extra['conversation_ids'])) {
					foreach ($extra['conversation_ids'] as $conversationId) {
						$wpdb->update(
							$table_name,
							[
								'deleted_at' => current_time('mysql'),
							],
							[
								'id'       => $conversationId,
								'inbox_id' => $extra['inbox_id'] ?? '',
							]
						);
					}
				}
				break;
			case self::TYPE_FORCE_DELETE_CONVERSATION:
				if (isset($extra['conversation_ids']) && count($extra['conversation_ids'])) {
					foreach ($extra['conversation_ids'] as $conversationId) {
						$wpdb->delete(
							$table_name,
							[
								'id'       => $conversationId,
								'inbox_id' => $extra['inbox_id'] ?? '',
							]
						);
					}
				}
				break;
			case self::TYPE_RESTORE_CONVERSATION:
				if (isset($extra['conversation_ids']) && count($extra['conversation_ids'])) {
					foreach ($extra['conversation_ids'] as $conversationId) {
						$wpdb->update(
							$table_name,
							[
								'deleted_at' => null,
							],
							[
								'id'       => $conversationId,
								'inbox_id' => $extra['inbox_id'] ?? '',
							]
						);
					}
				}
				break;
			case self::TYPE_UPDATE_CONVERSATION_STATUS:
				$extra['status'] && $extra['conversation_id'] && (
				$wpdb->update(
					$table_name,
					[
						'status'     => $extra['status'],
						'updated_at' => current_time('mysql'),
					],
					[
						'id'       => $extra['conversation_id'],
						'inbox_id' => $extra['inbox_id'],
					]
				)
				);
				break;
		}
	}

	/**
	 * Prepare data for FluentCRM api response
	 *
	 * @return array
	 * @since 0.7.0
	 */
	public function prepare_fluentcrm_data(): array {
		return $this->get_customer();
	}

	/**
	 * Truncate conversation subject and add ending character if necessary
	 *
	 * @param $title
	 *
	 * @return string
	 * @since 0.8.4
	 */
	public function truncate_string($title): string {
		if (mb_strwidth($title, 'UTF-8') > 180) {
			return rtrim(mb_strimwidth($title, 0, 180, '', 'UTF-8')) . '...';
		}

		return $title;
	}
}