<?php
/**
 * Two portal load-shedding fixes.
 *
 * 1. The expired-transient sweep is a self-joined DELETE over wp_options, the
 *    hottest table in WordPress, and it ran on every portal render. It belongs
 *    on cron, the way core's own transient cleanup is.
 * 2. Portal AJAX actions are open to every logged-in user, so the ones that
 *    force an uncached upstream fetch need a per-user rate gate.
 *
 * @package ThriveDesk\Tests
 */

class PortalThrottleAndCleanupCronTest extends WP_UnitTestCase {

	public function tear_down() {
		wp_clear_scheduled_hook( THRIVEDESK_CLEANUP_CRON_HOOK );
		parent::tear_down();
	}

	// the cron sweep ---------------------------------------------------------

	public function test_the_cleanup_handler_is_wired_to_the_cron_hook() {
		$this->assertNotFalse(
			has_action( THRIVEDESK_CLEANUP_CRON_HOOK, 'thrivedesk_delete_expired_transients' ),
			'the sweep must run from cron, not from a page render'
		);
	}

	public function test_the_scheduler_is_hooked_to_init() {
		// Scheduling hangs off init rather than the activation hook so stores
		// that update without re-activating still get the event.
		$this->assertNotFalse( has_action( 'init', 'thrivedesk_schedule_cleanup_cron' ) );
	}

	public function test_the_scheduler_registers_a_daily_sweep_when_it_is_missing() {
		wp_clear_scheduled_hook( THRIVEDESK_CLEANUP_CRON_HOOK );
		$this->assertFalse( wp_next_scheduled( THRIVEDESK_CLEANUP_CRON_HOOK ) );

		thrivedesk_schedule_cleanup_cron();

		$this->assertNotFalse( wp_next_scheduled( THRIVEDESK_CLEANUP_CRON_HOOK ) );
		$this->assertSame( 'daily', wp_get_schedule( THRIVEDESK_CLEANUP_CRON_HOOK ) );
	}

	public function test_scheduling_is_idempotent() {
		wp_clear_scheduled_hook( THRIVEDESK_CLEANUP_CRON_HOOK );

		thrivedesk_schedule_cleanup_cron();
		$first = wp_next_scheduled( THRIVEDESK_CLEANUP_CRON_HOOK );

		thrivedesk_schedule_cleanup_cron();

		$this->assertSame( $first, wp_next_scheduled( THRIVEDESK_CLEANUP_CRON_HOOK ), 'must not re-schedule' );
	}

	public function test_the_sweep_removes_only_expired_thrivedesk_transients() {
		set_transient( 'thrivedesk_fresh_entry', 'keep', HOUR_IN_SECONDS );
		set_transient( 'thrivedesk_stale_entry', 'drop', HOUR_IN_SECONDS );
		// Backdate the stale one's timeout so the sweep sees it as expired.
		update_option( '_transient_timeout_thrivedesk_stale_entry', time() - HOUR_IN_SECONDS );

		// Someone else's expired transient must survive; this is our sweep.
		set_transient( 'someone_elses_entry', 'keep', HOUR_IN_SECONDS );
		update_option( '_transient_timeout_someone_elses_entry', time() - HOUR_IN_SECONDS );

		thrivedesk_delete_expired_transients();

		// Read straight from the table: the sweep is a direct DELETE, so the
		// options object cache still holds the rows it removed.
		$this->assertSame( 'keep', $this->stored_option( '_transient_thrivedesk_fresh_entry' ) );
		$this->assertNull( $this->stored_option( '_transient_thrivedesk_stale_entry' ) );
		$this->assertSame( 'keep', $this->stored_option( '_transient_someone_elses_entry' ) );
	}

	/**
	 * The option value as it exists in wp_options, bypassing the object cache.
	 *
	 * @param string $name Option name.
	 * @return string|null
	 */
	private function stored_option( string $name ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s", $name ) );
	}

	// the per-user gate ------------------------------------------------------

	public function test_a_second_call_inside_the_window_is_throttled() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertFalse( td_user_action_throttled( 'reload_tickets' ), 'first call passes' );
		$this->assertTrue( td_user_action_throttled( 'reload_tickets' ), 'second call inside the window is refused' );
		$this->assertTrue( td_user_action_throttled( 'reload_tickets' ) );
	}

	public function test_the_gate_is_per_user() {
		$first  = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$second = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $first );
		$this->assertFalse( td_user_action_throttled( 'reload_tickets' ) );
		$this->assertTrue( td_user_action_throttled( 'reload_tickets' ) );

		// One noisy customer must not lock out everyone else.
		wp_set_current_user( $second );
		$this->assertFalse( td_user_action_throttled( 'reload_tickets' ) );
	}

	public function test_the_gate_is_per_action() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertFalse( td_user_action_throttled( 'reload_tickets' ) );
		$this->assertFalse( td_user_action_throttled( 'some_other_action' ) );
	}

	public function test_the_window_is_honoured() {
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user );

		$this->assertFalse( td_user_action_throttled( 'reload_tickets', 10 ) );

		// Expire the gate the way the clock would.
		delete_transient( 'td_throttle_reload_tickets_' . $user );

		$this->assertFalse( td_user_action_throttled( 'reload_tickets', 10 ), 'the gate must lift once the window passes' );
	}
}
