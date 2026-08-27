<?php
/**
 * The forwarding address shown beside each inbox on the Portal tab.
 *
 * /v1/inboxes is passed through raw - Inbox::inboxes() hands back whatever
 * ThriveDesk sent - and the plugin has only ever read `id` and `name` from it.
 * Nothing in this repository records what the address field is called, so
 * rather than commit to one name and render nothing when it is wrong, the
 * plausible ones are tried and anything that is not an address is refused.
 *
 * The refusal is the part worth testing. An address printed beside "set this
 * as your form's submission email" has to be right or absent; a truncated id
 * or a null that stringifies to nothing is worse than no row at all.
 *
 * @package ThriveDesk\Tests
 */

class InboxAddressTest extends WP_UnitTestCase {

	public function test_it_finds_the_address_under_any_of_the_names_it_knows() {
		foreach ( [ 'email', 'email_address', 'inbox_email', 'forward_email', 'forwarding_email', 'address' ] as $key ) {
			$this->assertSame(
				'help@acme.thrivedesk.com',
				thrivedesk_inbox_address( [ 'name' => 'General', $key => 'help@acme.thrivedesk.com' ] ),
				"an inbox whose address arrives as `$key` still gets a row"
			);
		}
	}

	public function test_the_first_name_it_knows_wins() {
		$this->assertSame(
			'first@acme.test',
			thrivedesk_inbox_address( [ 'email' => 'first@acme.test', 'address' => 'second@acme.test' ] )
		);
	}

	public function test_an_inbox_with_no_address_gets_no_row() {
		$this->assertSame( '', thrivedesk_inbox_address( [ 'id' => 'i1', 'name' => 'General' ] ) );
		$this->assertSame( '', thrivedesk_inbox_address( [] ) );
	}

	/**
	 * The failure that would be worst to print: a field that exists, is called
	 * something plausible, and holds something that is not an address.
	 */
	public function test_anything_that_is_not_an_address_is_refused() {
		foreach ( [ '', '   ', 'General', 'i_01HZX', null, 0, [ 'a' => 'b' ], true ] as $value ) {
			$this->assertSame(
				'',
				thrivedesk_inbox_address( [ 'email' => $value ] ),
				'a non-address must not be printed as one'
			);
		}
	}

	/**
	 * It reaches the page inside a copy button's data attribute and a title, so
	 * it is sanitised on the way out rather than trusted because it came from
	 * ThriveDesk.
	 */
	public function test_the_address_is_sanitised() {
		$this->assertSame(
			'help@acme.test',
			thrivedesk_inbox_address( [ 'email' => 'help@acme.test' ] )
		);
		$this->assertSame(
			'',
			thrivedesk_inbox_address( [ 'email' => '"><script>alert(1)</script>@acme.test' ] )
		);
	}
}
