<?php
/**
 * The business-hours option on the Portal tab, and the bar it turns on.
 *
 * The service is tested in BusinessHoursTest; what is pinned here is the part
 * that spans the screen and the portal - the gate that stops an admin switching
 * on hours their workspace does not have, the setting surviving a save, and the
 * portal only carrying the bar when it has been asked to.
 *
 * @package ThriveDesk\Tests
 */

use ThriveDesk\Services\BusinessHoursService;

class BusinessHoursScreenTest extends TD_Ajax_TestCase {

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );

		delete_transient( BusinessHoursService::PROFILES_TRANSIENT );
		delete_transient( BusinessHoursService::HOLIDAYS_TRANSIENT );
		delete_option( 'td_helpdesk_settings' );
		delete_option( 'td_helpdesk_verified' );

		parent::tear_down();
	}

	/**
	 * Answer /v1/business-hours with these profiles and everything else with an
	 * empty list, so the rest of the screen renders without reaching out.
	 *
	 * @param array $profiles Profiles the workspace has.
	 */
	private function stub_api( array $profiles ): void {
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( $profiles ) {
				$data = false !== strpos( $url, '/business-hours' ) ? $profiles : array();

				return array(
					'headers'  => array(),
					'response' => array( 'code' => 200, 'message' => 'OK' ),
					'body'     => wp_json_encode( array( 'data' => $data ) ),
				);
			},
			10,
			3
		);
	}

	/**
	 * @param array $days Day keys the profile is open on.
	 * @param string $name Profile name.
	 * @param string $id   Profile id.
	 */
	private function profile( string $id = 'p1', string $name = 'Support' ): array {
		return array(
			'id'       => $id,
			'name'     => $name,
			'mode'     => 'standard',
			'timezone' => 'UTC',
			'schedule' => array(
				array(
					'day'     => 'mon',
					'enabled' => true,
					'start'   => '09:00',
					'end'     => '17:00',
				),
			),
		);
	}

	/**
	 * The Portal tab as an admin sees it on a connected site.
	 *
	 * @param array $profiles Profiles the workspace has.
	 * @param array $settings Extra stored settings.
	 */
	private function screen( array $profiles, array $settings = array() ): string {
		update_option( 'td_helpdesk_settings', array_merge( array( 'td_helpdesk_api_key' => 'k' ), $settings ) );
		update_option( 'td_helpdesk_verified', true );

		$this->stub_api( $profiles );

		ob_start();
		include THRIVEDESK_DIR . '/includes/views/partials/settings.php';

		return (string) ob_get_clean();
	}

	/**
	 * Save through the real AJAX handler, which emits JSON and wp_die()s.
	 *
	 * @param array $data Posted fields.
	 */
	private function save( array $data ): array {
		$_POST = array(
			'nonce' => wp_create_nonce( 'thrivedesk-nonce' ),
			'data'  => array_merge( array( 'td_helpdesk_api_key' => 'REAL-KEY-1234567890' ), $data ),
		);

		$_REQUEST = $_POST;

		$body = $this->capture_json(
			static function () {
				\ThriveDesk\Conversations\Conversation::instance()->td_save_helpdesk_form();
			}
		);

		$this->assertSame( 'success', $body['status'] ?? null, 'the save has to have landed for the rest to mean anything' );

		return (array) get_option( 'td_helpdesk_settings' );
	}

	// the gate ---------------------------------------------------------------

	public function test_a_workspace_with_no_hours_gets_the_option_disabled_rather_than_hidden() {
		// Hidden would leave an admin with nothing to tell them the feature
		// exists, let alone that it is switched on somewhere else.
		$html = $this->screen( array() );

		$this->assertStringContainsString( 'id="td_helpdesk_business_hours"', $html );
		$this->assertMatchesRegularExpression(
			'/id="td_helpdesk_business_hours"[^>]*disabled/',
			$html
		);
	}

	public function test_the_disabled_option_says_where_to_go_and_switch_them_on() {
		$html = $this->screen( array() );

		$this->assertStringContainsString( 'Set your business hours in ThriveDesk first', $html );
		$this->assertStringContainsString( 'Set them in ThriveDesk', $html );
	}

	public function test_hours_on_the_workspace_unlock_the_option() {
		$html = $this->screen( array( $this->profile() ) );

		$this->assertStringContainsString( 'id="td_helpdesk_business_hours"', $html );
		$this->assertDoesNotMatchRegularExpression(
			'/id="td_helpdesk_business_hours"[^>]*disabled/',
			$html
		);
		$this->assertStringNotContainsString( 'Set your business hours in ThriveDesk first', $html );
	}

	public function test_a_stored_tick_comes_back_ticked() {
		$html = $this->screen(
			array( $this->profile() ),
			array( 'td_helpdesk_business_hours' => 1 )
		);

		$this->assertMatchesRegularExpression(
			'/id="td_helpdesk_business_hours"[^>]*checked/',
			$html
		);
	}

	// choosing between schedules ---------------------------------------------

	public function test_one_schedule_is_not_a_choice_so_no_picker_is_drawn() {
		$html = $this->screen( array( $this->profile() ) );

		$this->assertStringNotContainsString( 'id="td_helpdesk_business_hours_profile"', $html );
	}

	public function test_more_than_one_schedule_gets_a_picker() {
		$html = $this->screen(
			array( $this->profile( 'eu', 'Support EU' ), $this->profile( 'us', 'Support US' ) )
		);

		$this->assertStringContainsString( 'id="td_helpdesk_business_hours_profile"', $html );
		$this->assertStringContainsString( 'Support EU', $html );
		$this->assertStringContainsString( 'Support US', $html );
	}

	public function test_the_picker_comes_back_on_the_schedule_that_was_chosen() {
		$html = $this->screen(
			array( $this->profile( 'eu', 'Support EU' ), $this->profile( 'us', 'Support US' ) ),
			array( 'td_helpdesk_business_hours_profile' => 'us' )
		);

		$this->assertMatchesRegularExpression( '/<option value="us"\s+selected/', $html );
	}

	// saving -----------------------------------------------------------------

	public function test_ticking_it_is_remembered() {
		$this->assertSame( 1, $this->save( array( 'td_helpdesk_business_hours' => '1' ) )['td_helpdesk_business_hours'] );
	}

	public function test_unticking_it_is_written_down_as_a_decision() {
		// An unticked checkbox posts nothing. Left absent, "off" would be
		// indistinguishable from "this install predates the setting".
		$this->save( array( 'td_helpdesk_business_hours' => '1' ) );

		$saved = $this->save( array() );

		$this->assertArrayHasKey( 'td_helpdesk_business_hours', $saved );
		$this->assertSame( 0, $saved['td_helpdesk_business_hours'] );
	}

	public function test_the_chosen_schedule_is_remembered() {
		$saved = $this->save(
			array(
				'td_helpdesk_business_hours'         => '1',
				'td_helpdesk_business_hours_profile' => 'us',
			)
		);

		$this->assertSame( 'us', $saved['td_helpdesk_business_hours_profile'] );
	}

	public function test_no_chosen_schedule_stores_an_empty_string_meaning_whichever_is_first() {
		$saved = $this->save( array( 'td_helpdesk_business_hours' => '1' ) );

		$this->assertSame( '', $saved['td_helpdesk_business_hours_profile'] );
	}

	// the bar on the portal ---------------------------------------------------

	/**
	 * The portal as a logged-in customer sees it.
	 *
	 * @param array $profiles Profiles the workspace has.
	 * @param array $settings Extra stored settings.
	 */
	private function portal( array $profiles, array $settings = array() ): string {
		update_option( 'td_helpdesk_settings', array_merge( array( 'td_helpdesk_api_key' => 'k' ), $settings ) );
		update_option( 'td_helpdesk_verified', true );

		// Primed rather than fetched: the plan lookup is PortalService's
		// business and is tested there.
		set_transient( \ThriveDesk\Services\PortalService::PORTAL_ACCESS_TRANSIENT, 'yes', HOUR_IN_SECONDS );

		$this->stub_api( $profiles );

		$this->go_to( get_permalink( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );

		ob_start();
		include THRIVEDESK_DIR . '/includes/views/shortcode/conversations.php';
		$html = (string) ob_get_clean();

		delete_transient( \ThriveDesk\Services\PortalService::PORTAL_ACCESS_TRANSIENT );

		return $html;
	}

	public function test_the_portal_carries_the_bar_once_the_option_is_on() {
		$html = $this->portal(
			array( $this->profile() ),
			array( 'td_helpdesk_business_hours' => 1 )
		);

		$this->assertStringContainsString( 'class="td-hours"', $html );
		$this->assertStringContainsString( 'data-td-hours=', $html );
	}

	public function test_the_payload_on_the_bar_is_the_schedule_the_browser_needs() {
		$html = $this->portal(
			array( $this->profile() ),
			array( 'td_helpdesk_business_hours' => 1 )
		);

		preg_match( '/data-td-hours="([^"]*)"/', $html, $found );

		$payload = json_decode( html_entity_decode( $found[1] ?? '', ENT_QUOTES ), true );

		$this->assertIsArray( $payload );
		$this->assertSame( array( array( 32400, 61200 ) ), $payload['week'][1] );
		$this->assertArrayHasKey( 'now', $payload, 'without the server clock the browser cannot correct its own' );
	}

	public function test_the_portal_carries_nothing_while_the_option_is_off() {
		$html = $this->portal( array( $this->profile() ) );

		$this->assertStringNotContainsString( 'class="td-hours"', $html );
	}

	public function test_the_option_being_on_is_not_enough_if_the_workspace_lost_its_hours() {
		// Switched on here, then deleted upstream. Better to draw nothing than a
		// bar that cannot say anything.
		$html = $this->portal( array(), array( 'td_helpdesk_business_hours' => 1 ) );

		$this->assertStringNotContainsString( 'class="td-hours"', $html );
	}
}
