<?php
/**
 * FluentCrmHooks::format_ticket shapes a stored conversation row into the ticket
 * array FluentCRM renders. "Submitted at" must carry the stored submission time,
 * not a value derived from the current time.
 *
 * @package ThriveDesk\Tests
 */

class FluentCrmTicketFormatTest extends WP_UnitTestCase {

	private function conversation(): object {
		return (object) array(
			'id'         => 'conv_abc',
			'ticket_id'  => 42,
			'title'      => 'Need a hand',
			'status'     => 'open',
			'created_at' => '2024-01-02 03:04:05',
		);
	}

	public function test_submitted_at_is_the_stored_timestamp() {
		$row = \ThriveDesk\FluentCrmHooks::format_ticket( $this->conversation() );

		$this->assertSame( '2024-01-02 03:04:05', $row['Submitted at'] );
	}

	public function test_maps_conversation_fields_to_ticket_shape() {
		$row = \ThriveDesk\FluentCrmHooks::format_ticket( $this->conversation() );

		$this->assertSame( '#42', $row['id'] );
		$this->assertSame( 'Need a hand', $row['title'] );
		$this->assertSame( 'open', $row['status'] );
		$this->assertStringContainsString( '/conversations/conv_abc', $row['action'] );
	}
}
