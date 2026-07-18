<?php
/**
 * The assistant bootstrap snippet interpolates the current user's display name
 * and email straight into an inline <script>. Without JS escaping a double
 * quote breaks out of the string literal, so the values must be run through
 * esc_js(). wp_kses() alone does not JS-escape.
 *
 * @package ThriveDesk\Tests
 */

class AssistantScriptEscapingTest extends WP_UnitTestCase {

	public function test_display_name_is_js_escaped_in_the_snippet() {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_assistant_id' => 'aid123' ] );

		$user = self::factory()->user->create( [ 'display_name' => 'Ev"il' ] );
		wp_set_current_user( $user );

		ob_start();
		\ThriveDesk\Assistants\Assistant::instance()->load_assistant_script();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'aid123', $output, 'snippet should have rendered' );
		$this->assertStringNotContainsString( 'Ev"il', $output, 'a raw double quote must not survive into the script' );
	}
}
