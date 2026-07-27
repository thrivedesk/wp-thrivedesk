<?php
/**
 * Deactivation must be reversible: it used to delete all plugin options, so a
 * user deactivating to debug lost their configuration. Destructive teardown
 * belongs in uninstall, not deactivate.
 *
 * @package ThriveDesk\Tests
 */

class DeactivatePreservesConfigTest extends WP_UnitTestCase {

	public function test_deactivate_preserves_configuration() {
		update_option( 'td_helpdesk_settings', array( 'td_helpdesk_api_key' => 'keep-me' ) );
		update_option( 'thrivedesk_options', array( 'edd' => array( 'connected' => true ) ) );

		\ThriveDesk\Admin::deactivate();

		$this->assertSame( array( 'td_helpdesk_api_key' => 'keep-me' ), get_option( 'td_helpdesk_settings' ) );
		$this->assertNotFalse( get_option( 'thrivedesk_options' ) );
	}
}
