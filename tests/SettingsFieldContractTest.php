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
			$body = [ 'data' => [ [ 'id' => 'i1', 'name' => 'General', 'connected_email_address' => '', 'inbox_address' => 'general@acme.thrivedesk.com' ] ] ];
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
	/**
	 * The Portal tab tells people to use an inbox address as their form's
	 * submission email, so the addresses are printed there rather than left in
	 * another tab - the connected mailbox where there is one, and the hosted
	 * address otherwise. See thrivedesk_inbox_address().
	 */
	public function test_the_portal_tab_offers_the_inbox_addresses_to_copy() {
		$html = $this->render();

		$this->assertStringContainsString( 'general@acme.thrivedesk.com', $html );
		$this->assertStringContainsString( 'data-td-copy="general@acme.thrivedesk.com"', $html );
		$this->assertStringContainsString( 'Your inbox addresses', $html );
	}

	public function test_the_shortcode_is_offered_beside_the_form_field() {
		$html = $this->render();

		$this->assertStringContainsString( 'data-td-copy="[thrivedesk_portal]"', $html );
		$this->assertStringContainsString( 'td-info__title', $html );
		$this->assertStringContainsString( 'Ticket creation form', $html );

		// One live region for the copy announcements, not one per button.
		$this->assertSame( 1, substr_count( $html, 'id="td-copy-status"' ) );
	}

	/**
	 * The step above says "use your existing form plugin". When there is one,
	 * the tab names it and offers to open it rather than leaving that as an
	 * instruction. See thrivedesk_detected_form_plugin().
	 */
	public function test_a_detected_form_plugin_is_offered_on_the_portal_tab() {
		add_filter( 'thrivedesk_form_plugins', static fn() => [ 'contact-form-7' => 'Contact Form 7' ] );
		add_filter( 'thrivedesk_form_plugin_actions', static fn() => [ 'contact-form-7' => 'admin.php?page=wpcf7-new' ] );

		wp_cache_set( 'plugins', [ '' => [ 'contact-form-7/wp-contact-form-7.php' => [ 'Name' => 'Contact Form 7' ] ] ], 'plugins' );
		$restore = (array) get_option( 'active_plugins', [] );
		update_option( 'active_plugins', [ 'contact-form-7/wp-contact-form-7.php' ] );

		$html = $this->render();

		wp_cache_delete( 'plugins', 'plugins' );
		update_option( 'active_plugins', $restore );
		remove_all_filters( 'thrivedesk_form_plugins' );
		remove_all_filters( 'thrivedesk_form_plugin_actions' );

		$this->assertStringContainsString( 'Form plugin found', $html );
		$this->assertStringContainsString( 'Contact Form 7', $html );
		$this->assertStringContainsString( 'https://ps.w.org/contact-form-7/assets/icon-128x128.png', $html );
		$this->assertStringContainsString( admin_url( 'admin.php?page=wpcf7-new' ), $html );

		// The letter behind the icon, for when wordpress.org cannot be reached.
		$this->assertStringContainsString( 'data-letter="C"', $html );
	}

	public function test_a_site_with_no_form_plugin_is_not_told_about_one() {
		add_filter( 'thrivedesk_form_plugins', static fn() => [ 'contact-form-7' => 'Contact Form 7' ] );
		wp_cache_set( 'plugins', [ '' => [ 'akismet/akismet.php' => [ 'Name' => 'Akismet' ] ] ], 'plugins' );

		$html = $this->render();

		wp_cache_delete( 'plugins', 'plugins' );
		remove_all_filters( 'thrivedesk_form_plugins' );

		$this->assertStringNotContainsString( 'Form plugin found', $html );

		// And the tab is otherwise intact.
		$this->assertStringContainsString( 'Ticket creation form', $html );
	}

	public function test_the_hidden_inbox_select_survives() {
		$html = $this->render();

		$this->assertStringContainsString( 'id="td-inboxes"', $html );
		$this->assertStringContainsString( 'display:none', $html );
	}
}
