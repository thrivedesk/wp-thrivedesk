<?php
/**
 * The search settings wait for a ticket form.
 *
 * Everything on that card describes what happens on the way to a ticket form -
 * what the portal searches before it lets anyone open one - so none of it means
 * anything until a form page has been chosen. Left open, it invites people to
 * configure a step that does not exist yet and then wonder why nothing happens.
 *
 * The `disabled` attribute is the part worth pinning. Dimming alone leaves the
 * controls reachable by keyboard and still saving, which is a worse lie than
 * not dimming at all.
 *
 * @package ThriveDesk\Tests
 */

class PortalSearchGateTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		update_option( 'td_helpdesk_verified', true );
		add_filter( 'pre_http_request', [ $this, 'answer_with' ], 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'answer_with' ], 10 );
		delete_option( 'td_helpdesk_settings' );
		delete_option( 'td_helpdesk_verified' );

		parent::tear_down();
	}

	/**
	 * @param mixed  $preempt Short-circuit value.
	 * @param array  $args    Request arguments.
	 * @param string $url     Request URL.
	 *
	 * @return array
	 */
	public function answer_with( $preempt, $args, $url ) {
		$body = false !== strpos( $url, 'knowledgebase' )
			? [ 'data' => [ [ 'slug' => 'help', 'name' => 'Help' ] ] ]
			: [];

		return [
			'headers'  => [],
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => wp_json_encode( $body ),
		];
	}

	private function render( $page_id = '' ): string {
		update_option(
			'td_helpdesk_settings',
			[
				'td_helpdesk_api_key' => 'REAL-KEY-1234567890',
				'td_helpdesk_page_id' => $page_id,
			]
		);

		ob_start();
		include THRIVEDESK_DIR . '/includes/views/partials/settings.php';

		return (string) ob_get_clean();
	}

	private function card( string $html ): string {
		$start = strpos( $html, 'id="td-search-card"' );

		$this->assertIsInt( $start, 'the search card has to be on the screen either way' );

		return substr( $html, $start, strpos( $html, 'Elsewhere on this site' ) - $start );
	}

	public function test_the_card_is_locked_until_a_ticket_form_is_chosen() {
		$html = $this->render();

		$this->assertStringContainsString( 'td-gated is-locked', $html );
		$this->assertStringContainsString( 'Select a ticket creation form above', $html );
	}

	/**
	 * Not hidden - locked. Hiding it would leave no clue that the setting
	 * exists, or that choosing a page is what reveals it.
	 */
	public function test_the_settings_are_still_visible_while_locked() {
		$card = $this->card( $this->render() );

		$this->assertStringContainsString( 'id="td_knowledgebase_slug"', $card );
		$this->assertStringContainsString( 'Help Center', $card );
	}

	public function test_every_control_on_it_is_really_disabled() {
		$card = $this->card( $this->render() );

		$this->assertMatchesRegularExpression(
			'/<select id="td_knowledgebase_slug"[^>]*\bdisabled\b/',
			$card,
			'a dimmed control that still takes focus and still saves is worse than no dimming'
		);
		$this->assertMatchesRegularExpression( '/class="td_helpdesk_post_types"[^>]*\bdisabled\b/', $card );
	}

	public function test_choosing_a_ticket_form_opens_it() {
		$page = self::factory()->post->create( [ 'post_type' => 'page' ] );

		$html = $this->render( $page );
		$card = $this->card( $html );

		$this->assertStringNotContainsString( 'td-gated is-locked', $html );
		$this->assertStringNotContainsString( 'disabled', $card );
		$this->assertStringContainsString( 'td-gated__hint" hidden', $html );
	}

	/** The wording follows the product: it is a Help Center, not a knowledge base. */
	public function test_the_card_talks_about_a_help_center() {
		$card = $this->card( $this->render() );

		$this->assertStringContainsString( 'Connect with Help Center', $card );
		$this->assertStringNotContainsString( 'knowledge base', $card );
		$this->assertStringNotContainsString( 'Knowledge base', $card );
	}
}
