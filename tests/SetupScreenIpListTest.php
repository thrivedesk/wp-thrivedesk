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

	/**
	 * The reference column is nested markup now - a rail, a panel, and the
	 * allowlist inside it - and a stray tag there silently reflows the whole
	 * card. libxml is stricter about this than a browser is.
	 */
	public function test_rendered_markup_is_well_formed() {
		$html = $this->render_setup_screen();

		$previous = libxml_use_internal_errors( true );
		libxml_clear_errors();

		$doc = new DOMDocument();
		$doc->loadHTML( '<!DOCTYPE html><html><body>' . $html . '</body></html>' );

		$structural = array_filter(
			libxml_get_errors(),
			static function ( $error ) {
				return false !== stripos( $error->message, 'mismatch' )
					|| false !== stripos( $error->message, 'Unexpected end tag' )
					|| false !== stripos( $error->message, 'Premature end of data' );
			}
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$this->assertSame(
			[],
			array_map( static function ( $error ) { return trim( $error->message ); }, $structural )
		);
	}

	/**
	 * The rail is the only way into the panel, so its aria wiring has to point
	 * at the thing it opens.
	 */
	public function test_the_reference_column_collapses_to_a_labelled_rail() {
		$html = $this->render_setup_screen();

		$this->assertStringContainsString( 'Additional Info - click to expand', $html );
		$this->assertStringContainsString( 'aria-controls="td-setup-aside-panel"', $html );
		$this->assertStringContainsString( 'id="td-setup-aside-panel"', $html );

		// Collapsed is the initial state, and the panel is not open in the markup.
		$this->assertStringContainsString( 'aria-expanded="false"', $html );
		$this->assertStringNotContainsString( 'td-split is-open', $html );

		// With the rail hidden once open, the close control is the way back.
		$this->assertStringContainsString( 'td-aside-close', $html );
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
