<?php
/**
 * The setup screen's IP allowlist block.
 *
 * The IP addresses used to be typed out twice - once as prose in the view and
 * once in TDApiService's Cloudflare-block message - so the two could drift
 * apart. These tests pin them to the single helper both now read, and pin the
 * copy affordance the view renders around each one.
 *
 * @package ThriveDesk\Tests
 */

class SetupScreenIpListTest extends WP_UnitTestCase {

	/**
	 * Render includes/views/pages/api-verify.php and return its markup.
	 */
	private function render_setup_screen(): string {
		ob_start();
		include THRIVEDESK_DIR . '/includes/views/pages/api-verify.php';

		return (string) ob_get_clean();
	}

	public function test_service_ips_are_a_non_empty_list_of_addresses() {
		$ips = thrivedesk_service_ips();

		$this->assertNotEmpty( $ips );

		foreach ( $ips as $ip ) {
			$this->assertNotFalse(
				filter_var( $ip, FILTER_VALIDATE_IP ),
				sprintf( '%s is not a valid IP address', $ip )
			);
		}
	}

	public function test_every_service_ip_is_rendered_with_its_own_copy_button() {
		$html = $this->render_setup_screen();

		foreach ( thrivedesk_service_ips() as $ip ) {
			// The value the copy button puts on the clipboard, not just the
			// address appearing loose somewhere in the prose.
			$this->assertStringContainsString(
				'data-td-copy="' . esc_attr( $ip ) . '"',
				$html,
				sprintf( '%s has no copy button', $ip )
			);
		}

		$this->assertSame(
			count( thrivedesk_service_ips() ),
			substr_count( $html, 'data-td-copy=' ),
			'one copy button per address, no more and no fewer'
		);
	}

	public function test_copy_buttons_carry_an_accessible_name() {
		$html = $this->render_setup_screen();

		foreach ( thrivedesk_service_ips() as $ip ) {
			$this->assertStringContainsString(
				'aria-label="Copy ' . esc_attr( $ip ) . '"',
				$html,
				sprintf( 'the copy button for %s is unlabelled', $ip )
			);
		}
	}

	public function test_support_address_is_the_help_inbox() {
		$html = $this->render_setup_screen();

		$this->assertStringContainsString( 'mailto:help@thrivedesk.com', $html );
		$this->assertStringNotContainsString( 'support@thrivedesk.com', $html );
	}

	public function test_cloudflare_block_message_lists_the_same_addresses() {
		$service  = new \ThriveDesk\Services\TDApiService( 'irrelevant-token' );
		$method   = new ReflectionMethod( $service, 'handle_response' );
		$method->setAccessible( true );

		// A 403 with Cloudflare's marker in the body is the branch that tells
		// the site owner which addresses to let through.
		$result = $method->invoke(
			$service,
			[
				'response' => [ 'code' => 403 ],
				'body'     => 'Attention Required! | Cloudflare',
				'headers'  => [],
			]
		);

		foreach ( thrivedesk_service_ips() as $ip ) {
			$this->assertStringContainsString(
				$ip,
				$result['message'],
				sprintf( '%s is missing from the Cloudflare block message', $ip )
			);
		}
	}
}
