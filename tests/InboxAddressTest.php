<?php
/**
 * The address shown beside each inbox on the Portal tab.
 *
 * Two fields off /v1/inboxes, in order: `connected_email_address`, the mailbox
 * the owner has connected, and `inbox_address`, the ThriveDesk-hosted one every
 * inbox has. The first is what customers already write to; the second always
 * works, so it stands in when nothing is connected.
 *
 * The refusals are the part worth pinning. This is printed beside "use this as
 * your form's submission email", so a wrong address does not look like a bug -
 * it looks like a working form whose tickets go nowhere.
 *
 * @package ThriveDesk\Tests
 */

class InboxAddressTest extends WP_UnitTestCase {

	public function test_a_connected_mailbox_is_what_customers_already_write_to() {
		$this->assertSame(
			'support@acme.test',
			thrivedesk_inbox_address(
				[
					'name'                    => 'General',
					'connected_email_address' => 'support@acme.test',
					'inbox_address'           => 'general@acme.thrivedesk.com',
				]
			)
		);
	}

	public function test_the_hosted_address_stands_in_when_nothing_is_connected() {
		foreach ( [ '', null, '   ' ] as $empty ) {
			$this->assertSame(
				'general@acme.thrivedesk.com',
				thrivedesk_inbox_address(
					[
						'connected_email_address' => $empty,
						'inbox_address'           => 'general@acme.thrivedesk.com',
					]
				)
			);
		}

		$this->assertSame(
			'general@acme.thrivedesk.com',
			thrivedesk_inbox_address( [ 'inbox_address' => 'general@acme.thrivedesk.com' ] ),
			'the field may be absent entirely, not just empty'
		);
	}

	/**
	 * Falling through rather than printing it. The hosted address always works,
	 * so it is a better answer than a malformed one.
	 */
	public function test_a_connected_address_that_is_not_an_address_falls_through() {
		$this->assertSame(
			'general@acme.thrivedesk.com',
			thrivedesk_inbox_address(
				[
					'connected_email_address' => 'not an address',
					'inbox_address'           => 'general@acme.thrivedesk.com',
				]
			)
		);
	}

	public function test_an_inbox_with_neither_gets_no_row() {
		$this->assertSame( '', thrivedesk_inbox_address( [ 'id' => 'i1', 'name' => 'General' ] ) );
		$this->assertSame( '', thrivedesk_inbox_address( [] ) );
	}

	/**
	 * It reaches the page inside a copy button's data attribute and a title, so
	 * it is validated on the way out rather than trusted because ThriveDesk sent
	 * it.
	 */
	public function test_anything_that_is_not_an_address_is_refused() {
		foreach ( [ '', '   ', 'General', 'i_01HZX', null, 0, [ 'a' => 'b' ], true ] as $value ) {
			$this->assertSame(
				'',
				thrivedesk_inbox_address( [ 'connected_email_address' => $value, 'inbox_address' => $value ] ),
				'a non-address must not be printed as one'
			);
		}

		$this->assertSame(
			'',
			thrivedesk_inbox_address( [ 'inbox_address' => '"><script>alert(1)</script>@acme.test' ] )
		);
	}
}
