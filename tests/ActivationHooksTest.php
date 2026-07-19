<?php
/**
 * The activation/deactivation hooks were registered inside Admin's constructor,
 * which only runs under is_admin(). Under WP-CLI (`wp plugin activate`,
 * is_admin() false) they never registered and activate() never ran. They must
 * be registered at plugin-file scope so activation works in every context.
 *
 * @package ThriveDesk\Tests
 */

class ActivationHooksTest extends WP_UnitTestCase {

	public function test_lifecycle_hooks_are_registered_outside_the_admin_gate() {
		$base = plugin_basename( THRIVEDESK_FILE );

		$this->assertNotFalse(
			has_action( 'activate_' . $base ),
			'activation hook must be registered at file scope, not behind is_admin()'
		);
		$this->assertNotFalse(
			has_action( 'deactivate_' . $base ),
			'deactivation hook must be registered at file scope, not behind is_admin()'
		);
	}

	public function test_activate_installs_options_and_schema() {
		delete_option( 'thrivedesk_installed' );

		\ThriveDesk\Admin::activate();

		$this->assertNotFalse( get_option( 'thrivedesk_installed' ), 'activate() should record install time' );
		$this->assertEquals( THRIVEDESK_VERSION, get_option( 'thrivedesk_version' ) );

		global $wpdb;
		$table = $wpdb->prefix . 'td_conversations';
		$this->assertSame( $table, $wpdb->get_var( "SHOW TABLES LIKE '$table'" ), 'activate() should create the table' );
	}
}
