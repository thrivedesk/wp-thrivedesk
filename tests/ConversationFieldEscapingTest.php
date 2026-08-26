<?php
/**
 * `title` and `status` come off the inbound SaaS sync payload, where
 * sanitize_text_field() strips tags but leaves quotes, ampersands and entities
 * intact. Both were re-emitted raw into the ticket arrays FluentCRM and the
 * REST consumer render, on the very same line where `id` was escaped.
 *
 * @package ThriveDesk\Tests
 */

class ConversationFieldEscapingTest extends WP_UnitTestCase {

	private function conversation( array $overrides = array() ): object {
		return (object) array_merge(
			array(
				'id'         => 'conv_abc',
				'ticket_id'  => 42,
				'title'      => 'Need a hand',
				'status'     => 'open',
				'created_at' => '2024-01-02 03:04:05',
			),
			$overrides
		);
	}

	public function test_a_quote_in_the_title_is_escaped() {
		$row = \ThriveDesk\FluentCrmHooks::format_ticket(
			$this->conversation( array( 'title' => 'Refund for "order" <b>#7</b>' ) )
		);

		$this->assertStringNotContainsString( '<b>', $row['title'] );
		$this->assertStringContainsString( '&quot;', $row['title'] );
	}

	public function test_a_markup_bearing_status_is_replaced_not_rendered() {
		$row = \ThriveDesk\FluentCrmHooks::format_ticket(
			$this->conversation( array( 'status' => '"><script>alert(1)</script>' ) )
		);

		$this->assertSame( 'unknown', $row['status'] );
	}

	public function test_real_statuses_survive_untouched() {
		// The vocabulary belongs to the SaaS and the plugin's own UI already
		// knows more than three values, so the guard must not relabel them.
		foreach ( array( 'active', 'open', 'pending', 'closed', 'resolved', 'on-hold', 'archived' ) as $status ) {
			$row = \ThriveDesk\FluentCrmHooks::format_ticket( $this->conversation( array( 'status' => $status ) ) );

			$this->assertSame( $status, $row['status'], "$status must render as itself" );
		}
	}

	public function test_an_empty_status_becomes_unknown() {
		$row = \ThriveDesk\FluentCrmHooks::format_ticket( $this->conversation( array( 'status' => '' ) ) );

		$this->assertSame( 'unknown', $row['status'] );
	}

	public function test_the_submitted_timestamp_is_still_the_stored_one() {
		$row = \ThriveDesk\FluentCrmHooks::format_ticket( $this->conversation() );

		$this->assertSame( '2024-01-02 03:04:05', $row['Submitted at'] );
	}
}
