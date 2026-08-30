<?php
/**
 * Business hours, mirrored from ThriveDesk onto the portal.
 *
 * The service does the awkward part - turning a flat list of day fragments
 * with times as strings into a week a browser can index - so most of what is
 * pinned here is that normalising, plus the load discipline the portal has
 * needed before: nothing over the wire while disconnected, and an answer cached
 * whether or not it was the answer we wanted.
 *
 * @package ThriveDesk\Tests
 */

use ThriveDesk\Services\BusinessHoursService;

class BusinessHoursTest extends WP_UnitTestCase {

	/** @var int */
	private $calls = 0;

	/** @var array */
	private $timeouts = array();

	public function set_up() {
		parent::set_up();

		$this->calls    = 0;
		$this->timeouts = array();

		$this->forget();
	}

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		$this->forget();

		parent::tear_down();
	}

	private function forget(): void {
		delete_transient( BusinessHoursService::PROFILES_TRANSIENT );
		delete_transient( BusinessHoursService::HOLIDAYS_TRANSIENT );
		delete_option( 'td_helpdesk_settings' );
		delete_option( 'td_helpdesk_verified' );
	}

	private function connect(): void {
		update_option( 'td_helpdesk_settings', array( 'td_helpdesk_api_key' => 'test-key' ) );
		update_option( 'td_helpdesk_verified', true );
	}

	/**
	 * Answer /v1/business-hours and /v1/holidays with fixtures, counting calls.
	 *
	 * @param array $profiles Profile list.
	 * @param array $holidays Holiday list.
	 */
	private function stub_api( array $profiles, array $holidays = array() ): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $profiles, $holidays ) {
				++$this->calls;
				$this->timeouts[] = $args['timeout'] ?? null;

				$data = false !== strpos( $url, '/holidays' ) ? $holidays : $profiles;

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode( array( 'data' => $data ) ),
				);
			},
			10,
			3
		);
	}

	/**
	 * A profile open 09:00-17:00 on the given days.
	 *
	 * @param array  $days Day keys.
	 * @param string $name Profile name.
	 * @param string $id   Profile id.
	 */
	private function nine_to_five( array $days = array( 'mon' ), string $name = 'Support', string $id = 'p1' ): array {
		$schedule = array();

		foreach ( $days as $day ) {
			$schedule[] = array(
				'day'     => $day,
				'enabled' => true,
				'start'   => '09:00',
				'end'     => '17:00',
			);
		}

		return array(
			'id'       => $id,
			'name'     => $name,
			'mode'     => 'standard',
			'schedule' => $schedule,
			'timezone' => 'UTC',
		);
	}

	// load discipline --------------------------------------------------------

	public function test_nothing_goes_over_the_wire_while_the_site_is_not_connected() {
		$this->stub_api( array( $this->nine_to_five() ) );

		$this->assertSame( array(), BusinessHoursService::profiles() );
		$this->assertSame( array(), BusinessHoursService::holidays() );
		$this->assertFalse( BusinessHoursService::is_available() );
		$this->assertNull( BusinessHoursService::payload() );

		$this->assertSame( 0, $this->calls, 'a disconnected site has no key to send and must not ask' );
	}

	public function test_the_profile_list_is_fetched_once_and_then_cached() {
		$this->connect();
		$this->stub_api( array( $this->nine_to_five() ) );

		BusinessHoursService::profiles();
		BusinessHoursService::profiles();
		BusinessHoursService::profiles();

		$this->assertSame( 1, $this->calls );
	}

	public function test_an_empty_answer_is_cached_too() {
		// An empty list and a list we could not fetch look the same to every
		// caller, so re-asking on the next render buys nothing and costs a
		// round trip on a page that renders for every logged-in visitor.
		$this->connect();
		$this->stub_api( array() );

		$this->assertFalse( BusinessHoursService::is_available() );
		$this->assertFalse( BusinessHoursService::is_available() );

		$this->assertSame( 1, $this->calls );
	}

	public function test_a_failed_lookup_is_cached_rather_than_retried_every_render() {
		$this->connect();

		add_filter(
			'pre_http_request',
			function ( $preempt, $args ) {
				++$this->calls;

				return new WP_Error( 'http_request_failed', 'down' );
			},
			10,
			2
		);

		$this->assertSame( array(), BusinessHoursService::profiles() );
		$this->assertSame( array(), BusinessHoursService::profiles() );

		$this->assertSame( 1, $this->calls );
	}

	public function test_the_lookup_uses_the_short_render_timeout() {
		// The portal shortcode blocks on this. TDApiService's 90 second default
		// would hold a PHP worker for a minute and a half per render.
		$this->connect();
		$this->stub_api( array( $this->nine_to_five() ) );

		BusinessHoursService::profiles();

		$this->assertSame( array( BusinessHoursService::RENDER_TIMEOUT ), $this->timeouts );
	}

	public function test_clearing_the_portal_cache_drops_the_stored_hours() {
		$this->connect();
		$this->stub_api( array( $this->nine_to_five() ) );

		BusinessHoursService::profiles();
		$this->assertNotFalse( get_transient( BusinessHoursService::PROFILES_TRANSIENT ) );

		remove_thrivedesk_all_cache();

		$this->assertFalse( get_transient( BusinessHoursService::PROFILES_TRANSIENT ) );
	}

	// choosing a profile -----------------------------------------------------

	public function test_the_first_profile_is_the_default() {
		$this->connect();
		$this->stub_api(
			array(
				$this->nine_to_five( array( 'mon' ), 'Support EU', 'eu' ),
				$this->nine_to_five( array( 'mon' ), 'Support US', 'us' ),
			)
		);

		$this->assertSame( 'eu', BusinessHoursService::profile()['id'] );
	}

	public function test_a_named_profile_is_used_when_one_is_stored() {
		$this->connect();
		$this->stub_api(
			array(
				$this->nine_to_five( array( 'mon' ), 'Support EU', 'eu' ),
				$this->nine_to_five( array( 'mon' ), 'Support US', 'us' ),
			)
		);

		$this->assertSame( 'us', BusinessHoursService::profile( 'us' )['id'] );
	}

	public function test_a_profile_deleted_upstream_falls_back_to_the_first() {
		// The stored id outlives the profile it names. Degrading to the first
		// beats showing nothing on a portal whose hours were working yesterday.
		$this->connect();
		$this->stub_api( array( $this->nine_to_five( array( 'mon' ), 'Support EU', 'eu' ) ) );

		$this->assertSame( 'eu', BusinessHoursService::profile( 'deleted-long-ago' )['id'] );
	}

	// the normalised week ----------------------------------------------------

	public function test_a_weekday_schedule_becomes_seconds_indexed_by_weekday() {
		$this->connect();
		$this->stub_api( array( $this->nine_to_five( array( 'mon', 'fri' ) ) ) );

		$week = BusinessHoursService::payload()['week'];

		// 0 is Sunday, matching JavaScript's getDay().
		$this->assertSame( array( array( 32400, 61200 ) ), $week[1] );
		$this->assertSame( array( array( 32400, 61200 ) ), $week[5] );
		$this->assertSame( array(), $week[0] );
		$this->assertSame( array(), $week[6] );
	}

	public function test_a_day_named_twice_keeps_both_windows_in_order() {
		// A split shift: mornings and afternoons with lunch closed. Picking one
		// would tell half the day's visitors the wrong thing.
		$this->connect();
		$this->stub_api(
			array(
				array(
					'id'       => 'p1',
					'mode'     => 'standard',
					'timezone' => 'UTC',
					'schedule' => array(
						array(
							'day'     => 'tue',
							'enabled' => true,
							'start'   => '13:00',
							'end'     => '17:00',
						),
						array(
							'day'     => 'tue',
							'enabled' => true,
							'start'   => '09:00',
							'end'     => '12:00',
						),
					),
				),
			)
		);

		$this->assertSame(
			array( array( 32400, 43200 ), array( 46800, 61200 ) ),
			BusinessHoursService::payload()['week'][2]
		);
	}

	public function test_windows_that_touch_are_fused_into_one() {
		// A desk listed as 09:00-12:00 and 12:00-17:00 is open 09:00-17:00. Left
		// as two, the portal counts down to a close at noon that never happens
		// and then jumps back to five hours remaining.
		$this->connect();
		$this->stub_api(
			array(
				array(
					'id'       => 'p1',
					'mode'     => 'standard',
					'timezone' => 'UTC',
					'schedule' => array(
						array(
							'day'     => 'mon',
							'enabled' => true,
							'start'   => '09:00',
							'end'     => '12:00',
						),
						array(
							'day'     => 'mon',
							'enabled' => true,
							'start'   => '12:00',
							'end'     => '17:00',
						),
					),
				),
			)
		);

		$this->assertSame( array( array( 32400, 61200 ) ), BusinessHoursService::payload()['week'][1] );
	}

	public function test_overlapping_windows_are_fused_too() {
		$this->connect();
		$this->stub_api(
			array(
				array(
					'id'       => 'p1',
					'mode'     => 'standard',
					'timezone' => 'UTC',
					'schedule' => array(
						array(
							'day'     => 'mon',
							'enabled' => true,
							'start'   => '09:00',
							'end'     => '14:00',
						),
						array(
							'day'     => 'mon',
							'enabled' => true,
							'start'   => '11:00',
							'end'     => '17:00',
						),
					),
				),
			)
		);

		$this->assertSame( array( array( 32400, 61200 ) ), BusinessHoursService::payload()['week'][1] );
	}

	public function test_a_night_shift_is_cut_at_midnight_into_two_days() {
		// 22:00-02:00 on Saturday is Saturday evening plus Sunday morning. Split
		// here so nothing the browser sees ever spans a day boundary.
		$this->connect();
		$this->stub_api(
			array(
				array(
					'id'       => 'p1',
					'mode'     => 'standard',
					'timezone' => 'UTC',
					'schedule' => array(
						array(
							'day'     => 'sat',
							'enabled' => true,
							'start'   => '22:00',
							'end'     => '02:00',
						),
					),
				),
			)
		);

		$week = BusinessHoursService::payload()['week'];

		$this->assertSame( array( array( 79200, 86400 ) ), $week[6] );
		$this->assertSame( array( array( 0, 7200 ) ), $week[0] );
	}

	public function test_days_that_are_switched_off_are_dropped() {
		$this->connect();
		$this->stub_api(
			array(
				array(
					'id'       => 'p1',
					'mode'     => 'standard',
					'timezone' => 'UTC',
					'schedule' => array(
						array(
							'day'     => 'mon',
							'enabled' => true,
							'start'   => '09:00',
							'end'     => '17:00',
						),
						array(
							'day'     => 'sun',
							'enabled' => false,
							'start'   => '09:00',
							'end'     => '17:00',
						),
					),
				),
			)
		);

		$week = BusinessHoursService::payload()['week'];

		$this->assertSame( array( array( 32400, 61200 ) ), $week[1] );
		$this->assertSame( array(), $week[0] );
	}

	public function test_times_are_accepted_with_or_without_seconds_and_junk_is_dropped() {
		$this->connect();
		$this->stub_api(
			array(
				array(
					'id'       => 'p1',
					'mode'     => 'standard',
					'timezone' => 'UTC',
					'schedule' => array(
						array(
							'day'     => 'mon',
							'enabled' => true,
							'start'   => '9:30',
							'end'     => '17:45:30',
						),
						array(
							'day'     => 'tue',
							'enabled' => true,
							'start'   => 'whenever',
							'end'     => '17:00',
						),
						array(
							'day'     => 'wed',
							'enabled' => true,
							'start'   => '09:00',
							'end'     => '09:00',
						),
						array(
							'day'     => 'notaday',
							'enabled' => true,
							'start'   => '09:00',
							'end'     => '17:00',
						),
					),
				),
			)
		);

		$week = BusinessHoursService::payload()['week'];

		$this->assertSame( array( array( 34200, 63930 ) ), $week[1] );
		$this->assertSame( array(), $week[2], 'an unparseable time is not a window' );
		$this->assertSame( array(), $week[3], 'a zero-length window is not a window' );
	}

	public function test_a_round_the_clock_profile_needs_no_week() {
		$this->connect();
		$this->stub_api(
			array(
				array(
					'id'       => 'p1',
					'name'     => 'Always on',
					'mode'     => 'calendar_24_7',
					'timezone' => 'UTC',
					'schedule' => array(),
				),
			)
		);

		$payload = BusinessHoursService::payload();

		$this->assertTrue( $payload['always'] );
		$this->assertSame( array(), $payload['week'] );
	}

	public function test_a_profile_with_no_open_window_draws_no_bar_at_all() {
		// Neither 24/7 nor open at any point is a misconfiguration upstream.
		// Announcing "closed, indefinitely" on the strength of it would be
		// worse than saying nothing.
		$this->connect();
		$this->stub_api(
			array(
				array(
					'id'       => 'p1',
					'mode'     => 'standard',
					'timezone' => 'UTC',
					'schedule' => array(
						array(
							'day'     => 'mon',
							'enabled' => false,
							'start'   => '09:00',
							'end'     => '17:00',
						),
					),
				),
			)
		);

		$this->assertNull( BusinessHoursService::payload() );
	}

	// timezone ---------------------------------------------------------------

	public function test_the_profiles_own_timezone_is_used_when_the_api_sends_one() {
		$this->connect();
		update_option( 'timezone_string', 'UTC' );

		$profile             = $this->nine_to_five();
		$profile['timezone'] = 'Asia/Dhaka'; // +06:00, no DST.

		$this->stub_api( array( $profile ) );

		$this->assertSame( 6 * HOUR_IN_SECONDS, BusinessHoursService::payload()['offset'] );
	}

	public function test_the_site_timezone_is_the_fallback_when_the_api_sends_none() {
		// The documented list payload carries no timezone. A site and a desk run
		// by the same people share one, which is the only guess available.
		$this->connect();
		update_option( 'timezone_string', 'Asia/Dhaka' );

		$profile = $this->nine_to_five();
		unset( $profile['timezone'] );

		$this->stub_api( array( $profile ) );

		$this->assertSame( 6 * HOUR_IN_SECONDS, BusinessHoursService::payload()['offset'] );
	}

	public function test_a_timezone_php_does_not_know_falls_back_rather_than_fatalling() {
		$this->connect();
		update_option( 'timezone_string', 'UTC' );

		$profile             = $this->nine_to_five();
		$profile['timezone'] = 'Middle/Earth';

		$this->stub_api( array( $profile ) );

		$this->assertSame( 0, BusinessHoursService::payload()['offset'] );
	}

	// holidays ---------------------------------------------------------------

	public function test_a_holiday_runs_to_the_end_of_its_last_day() {
		// end_date is inclusive upstream - a one-day holiday has the same start
		// and end - so the range has to cover that whole day, not stop at its
		// first second.
		$this->connect();
		update_option( 'timezone_string', 'UTC' );

		$today = gmdate( 'Y-m-d' );

		$this->stub_api(
			array( $this->nine_to_five() ),
			array(
				array(
					'id'         => 'h1',
					'name'       => 'Eid al-Fitr',
					'start_date' => $today,
					'end_date'   => $today,
				),
			)
		);

		$holidays = BusinessHoursService::payload()['holidays'];

		$this->assertCount( 1, $holidays );
		$this->assertSame( 'Eid al-Fitr', $holidays[0]['name'] );
		$this->assertSame( DAY_IN_SECONDS, $holidays[0]['to'] - $holidays[0]['from'] );
		$this->assertLessThanOrEqual( time(), $holidays[0]['from'] );
		$this->assertGreaterThan( time(), $holidays[0]['to'] );
	}

	public function test_holidays_that_are_already_over_are_dropped() {
		$this->connect();
		update_option( 'timezone_string', 'UTC' );

		$this->stub_api(
			array( $this->nine_to_five() ),
			array(
				array(
					'name'       => 'Last year',
					'start_date' => gmdate( 'Y-m-d', time() - 40 * DAY_IN_SECONDS ),
					'end_date'   => gmdate( 'Y-m-d', time() - 39 * DAY_IN_SECONDS ),
				),
				array(
					'name'       => 'Coming up',
					'start_date' => gmdate( 'Y-m-d', time() + 10 * DAY_IN_SECONDS ),
					'end_date'   => gmdate( 'Y-m-d', time() + 11 * DAY_IN_SECONDS ),
				),
			)
		);

		$holidays = BusinessHoursService::payload()['holidays'];

		$this->assertCount( 1, $holidays );
		$this->assertSame( 'Coming up', $holidays[0]['name'] );
	}

	public function test_holidays_come_back_in_date_order() {
		$this->connect();
		update_option( 'timezone_string', 'UTC' );

		$this->stub_api(
			array( $this->nine_to_five() ),
			array(
				array(
					'name'       => 'Later',
					'start_date' => gmdate( 'Y-m-d', time() + 20 * DAY_IN_SECONDS ),
					'end_date'   => gmdate( 'Y-m-d', time() + 20 * DAY_IN_SECONDS ),
				),
				array(
					'name'       => 'Sooner',
					'start_date' => gmdate( 'Y-m-d', time() + 5 * DAY_IN_SECONDS ),
					'end_date'   => gmdate( 'Y-m-d', time() + 5 * DAY_IN_SECONDS ),
				),
			)
		);

		$names = wp_list_pluck( BusinessHoursService::payload()['holidays'], 'name' );

		$this->assertSame( array( 'Sooner', 'Later' ), $names );
	}

	public function test_a_holiday_with_an_unusable_date_is_skipped() {
		$this->connect();

		$this->stub_api(
			array( $this->nine_to_five() ),
			array(
				array(
					'name'       => 'Sometime',
					'start_date' => 'next Thursday',
					'end_date'   => 'the one after',
				),
			)
		);

		$this->assertSame( array(), BusinessHoursService::payload()['holidays'] );
	}

	// the payload as a whole -------------------------------------------------

	public function test_the_payload_carries_the_server_clock_so_the_browser_can_correct_its_own() {
		// A visitor whose machine is an hour out would otherwise be told, with
		// total confidence, the wrong time to expect a reply.
		$this->connect();
		$this->stub_api( array( $this->nine_to_five() ) );

		$this->assertEqualsWithDelta( time(), BusinessHoursService::payload()['now'], 5 );
	}

	public function test_the_week_encodes_as_a_json_array_not_an_object() {
		// The browser does `Array.isArray(data.week) ? data.week : []`, so a week
		// that encoded as an object would leave the bar permanently closed - and
		// json_decode( ..., true ) in a test would not notice, because it hands
		// back an array either way.
		$this->connect();
		$this->stub_api( array( $this->nine_to_five( array( 'mon' ) ) ) );

		$json = wp_json_encode( BusinessHoursService::payload()['week'] );

		$this->assertStringStartsWith( '[[', $json );
	}

	public function test_the_payload_names_the_profile_it_came_from() {
		$this->connect();
		$this->stub_api( array( $this->nine_to_five( array( 'mon' ), 'Support EU', 'eu' ) ) );

		$this->assertSame( 'Support EU', BusinessHoursService::payload()['name'] );
	}
}
