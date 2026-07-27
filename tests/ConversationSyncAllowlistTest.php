<?php
/**
 * The inbound sync passed the raw attacker-controlled `extra` payload into
 * $wpdb writes on td_conversations; extra['conversation'] was handed whole to
 * $wpdb->replace, so an attacker controlled its column set and values. It must
 * be decoded through a typed, allowlisted DTO first.
 *
 * A create payload carrying a non-column key must still insert: before the fix
 * the unknown column errors the REPLACE (no row); after it, the allowlist drops
 * the key and the row lands.
 *
 * @package ThriveDesk\Tests
 */

class ConversationSyncAllowlistTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		require_once THRIVEDESK_DIR . '/database/DBMigrator.php';
		\ThriveDeskDBMigrator::migrate();
	}

	private function create_payload(): array {
		return array(
			'conversation' => array(
				'id'        => 'conv-allow-1',
				'title'     => 'Hello',
				'ticket_id' => '42',
				'inbox_id'  => 'inbox-1',
				'contact'   => 'a@example.com',
				'status'    => 'active',
				'evil'      => 'x', // not a td_conversations column
			),
		);
	}

	private function fetch_row() {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}td_conversations WHERE id = %s", 'conv-allow-1' ), ARRAY_A );
	}

	public function test_fluentcrm_create_drops_unknown_columns() {
		\ThriveDesk\Plugins\FluentCRM::instance()->sync_conversation_with_fluentcrm( 'create_conversation', $this->create_payload() );

		$row = $this->fetch_row();
		$this->assertNotNull( $row, 'row must insert; a non-column key would otherwise error the REPLACE' );
		$this->assertSame( 'Hello', $row['title'] );
		$this->assertSame( '42', $row['ticket_id'] );
	}

	public function test_autonami_create_drops_unknown_columns() {
		\ThriveDesk\Plugins\Autonami::instance()->sync_conversation_with_autonami( 'create_conversation', $this->create_payload() );

		$row = $this->fetch_row();
		$this->assertNotNull( $row, 'row must insert; a non-column key would otherwise error the REPLACE' );
		$this->assertSame( 'Hello', $row['title'] );
	}

	public function test_dto_drops_unknown_keys_and_types_ticket_id() {
		$data = \ThriveDesk\Data\ConversationSyncData::fromExtra(
			array(
				'conversation'     => array(
					'id'        => 'c1',
					'ticket_id' => '99',
					'inbox_id'  => 'ibx',
					'evil'      => 'x',
				),
				'conversation_ids' => array( 'a', 'b' ),
				'status'           => 'closed',
			)
		);

		$row = $data->conversationRow();
		$this->assertArrayNotHasKey( 'evil', $row, 'unknown keys must be dropped' );
		$this->assertArrayHasKey( 'id', $row );
		$this->assertSame( 99, $row['ticket_id'], 'ticket_id must be typed int' );
		$this->assertSame( array( 'a', 'b' ), $data->conversationIds() );
		$this->assertSame( 'closed', $data->status() );
	}
}
