<?php
/**
 * The settings screen and its save handler agree by id, not by form.
 *
 * resources/js/admin.js autosaves on `change` of a fixed list of selectors and
 * then reads each of them back by id - it never serialises the form, which is
 * what lets the panels live inside React tabs at all. Nothing enforces that
 * agreement at runtime: a field renamed or dropped in a redesign does not
 * error, it silently stops being saved, and the setting quietly reverts on the
 * next page load.
 *
 * So the list is asserted here. If a selector below no longer appears on the
 * screen, either the markup lost a field or TD_AUTOSAVE_FIELDS is out of date -
 * and both are bugs.
 *
 * @package ThriveDesk\Tests
 */

class SettingsFieldContractTest extends WP_UnitTestCase {

	/** Kept in step with TD_AUTOSAVE_FIELDS in resources/js/admin.js. */
	private const AUTOSAVED = [
		'#td-assistants'          => 'id="td-assistants"',
		'#td-excluded-routes'     => 'id="td-excluded-routes"',
		'#td-inboxes'             => 'id="td-inboxes"',
		'#td_helpdesk_page_id'    => 'id="td_helpdesk_page_id"',
		'#td_knowledgebase_slug'  => 'id="td_knowledgebase_slug"',
		'.td_helpdesk_post_types' => 'class="td_helpdesk_post_types"',
		'.td_user_account_pages'  => 'class="td_user_account_pages"',
	];

	public function set_up() {
		parent::set_up();

		// The panels are only rendered for a connected site; without this they
		// are placeholders offering an account.
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'REAL-KEY-1234567890' ] );
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
	 * Enough of an assistant, an inbox and a knowledge base for the selects
	 * that list them to render at all.
	 *
	 * @param mixed  $preempt Short-circuit value.
	 * @param array  $args    Request arguments.
	 * @param string $url     Request URL.
	 *
	 * @return array
	 */
	public function answer_with( $preempt, $args, $url ) {
		$body = [];

		if ( false !== strpos( $url, 'assistant' ) ) {
			$body = [ 'assistants' => [ [ 'id' => 'a1', 'name' => 'Support' ] ] ];
		} elseif ( false !== strpos( $url, 'inbox' ) ) {
			$body = [ 'inboxes' => [ [ 'id' => 'i1', 'name' => 'General' ] ] ];
		} elseif ( false !== strpos( $url, 'knowledgebase' ) ) {
			$body = [ 'data' => [ [ 'slug' => 'help', 'name' => 'Help' ] ] ];
		}

		return [
			'headers'  => [],
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => wp_json_encode( $body ),
		];
	}

	private function render(): string {
		ob_start();
		include THRIVEDESK_DIR . '/includes/views/partials/settings.php';

		return (string) ob_get_clean();
	}

	public function test_every_autosaved_field_is_on_the_screen() {
		$html = $this->render();

		foreach ( self::AUTOSAVED as $selector => $marker ) {
			$this->assertStringContainsString(
				$marker,
				$html,
				"$selector is autosaved by admin.js but no longer renders"
			);
		}
	}

	/**
	 * Every one of them has to be inside a panel, because a field outside the
	 * three #td-panel-* divs is never adopted into a tab and so is never seen.
	 */
	public function test_the_fields_live_inside_a_tab_panel() {
		$html = $this->render();

		$first = strpos( $html, 'id="td-panel-overview"' );

		$this->assertIsInt( $first );

		foreach ( self::AUTOSAVED as $selector => $marker ) {
			$this->assertGreaterThan( $first, strpos( $html, $marker ), "$selector escaped the panels" );
		}
	}

	/**
	 * The inbox select is not shown to anyone, and is deliberately still
	 * rendered: the save handler reads it by id like every other field, and a
	 * missing one would post an empty inbox over a chosen one.
	 */
	public function test_the_hidden_inbox_select_survives() {
		$html = $this->render();

		$this->assertStringContainsString( 'id="td-inboxes"', $html );
		$this->assertStringContainsString( 'display:none', $html );
	}
}
