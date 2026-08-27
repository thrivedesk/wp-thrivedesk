<?php
/**
 * The shipped catalogue of form plugins.
 *
 * A generated file - swept from the wordpress.org plugins API and filtered by
 * hand - so what is worth testing is not its contents but its shape. It is
 * matched against directory names from get_plugins(), and a malformed key
 * simply never matches: the failure is a card that quietly stops appearing, not
 * an error anyone would see.
 *
 * @package ThriveDesk\Tests
 */

class FormPluginCatalogueTest extends WP_UnitTestCase {

	public function test_the_catalogue_ships_and_is_not_empty() {
		$this->assertFileExists( THRIVEDESK_DIR . '/includes/data/form-plugins.php' );
		$this->assertGreaterThan( 100, count( thrivedesk_form_plugins() ) );
	}

	/**
	 * Every key has to be a wordpress.org slug, because that is also the plugin
	 * directory name and the only thing detection compares against.
	 */
	public function test_every_key_is_a_plugin_directory_name() {
		foreach ( thrivedesk_form_plugins() as $slug => $name ) {
			$this->assertMatchesRegularExpression(
				'/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
				$slug,
				"`$slug` cannot be a plugin directory name, so it can never match"
			);
			$this->assertNotSame( '', trim( (string) $name ), "`$slug` has no display name" );
		}
	}

	/**
	 * The order is the tie-break rule: a site with two form plugins gets the
	 * one more sites run. A catalogue that lost its ordering would still work
	 * and still be wrong.
	 */
	public function test_the_most_installed_plugins_lead() {
		$leaders = array_slice( array_keys( thrivedesk_form_plugins() ), 0, 10 );

		$this->assertSame( 'contact-form-7', $leaders[0] );
		$this->assertContains( 'wpforms-lite', $leaders );
		$this->assertContains( 'ninja-forms', $leaders );
	}

	/**
	 * The filter that exists because the generated list cannot be complete -
	 * a premium-only form plugin is not on wordpress.org to be swept up.
	 */
	public function test_a_site_can_add_a_plugin_the_sweep_could_not_find() {
		add_filter(
			'thrivedesk_form_plugins',
			static function ( $plugins ) {
				return array_merge( [ 'gravityforms' => 'Gravity Forms' ], $plugins );
			}
		);

		$plugins = thrivedesk_form_plugins();

		remove_all_filters( 'thrivedesk_form_plugins' );

		$this->assertArrayHasKey( 'gravityforms', $plugins );
		$this->assertSame( 'gravityforms', array_key_first( $plugins ), 'and can outrank the sweep' );
	}

	/**
	 * Every verified builder URL has to belong to a plugin the catalogue lists,
	 * or detection never reaches it. The failure is silent: the work of reading
	 * that URL out of the plugin's source is simply never used.
	 */
	public function test_no_builder_url_is_stranded() {
		$this->assertSame(
			[],
			array_values( array_diff( array_keys( thrivedesk_form_plugin_actions() ), array_keys( thrivedesk_form_plugins() ) ) ),
			'a builder URL for a plugin the catalogue does not list can never fire'
		);
	}

	/**
	 * Each URL is handed to admin_url(), which does not tolerate a leading
	 * slash, and each one was read out of a plugin's own menu registration.
	 */
	public function test_the_builder_urls_are_relative_admin_paths() {
		$actions = thrivedesk_form_plugin_actions();

		$this->assertNotEmpty( $actions );

		foreach ( $actions as $slug => $url ) {
			$this->assertStringStartsNotWith( '/', $url, "$slug must be relative" );
			$this->assertStringStartsNotWith( 'http', $url, "$slug must not leave this site" );
			$this->assertMatchesRegularExpression( '/^[a-z-]+\.php\?/', $url, "$slug must name an admin screen" );
		}
	}

	/**
	 * The most-installed form plugins are the ones worth having a button for.
	 * Losing one of these to a regeneration would leave the biggest audiences
	 * with a sentence where they used to get a link.
	 */
	public function test_the_biggest_form_plugins_have_a_button() {
		$actions = thrivedesk_form_plugin_actions();

		foreach ( [ 'contact-form-7', 'wpforms-lite', 'ninja-forms', 'fluentform', 'forminator', 'formidable' ] as $slug ) {
			$this->assertArrayHasKey( $slug, $actions, "$slug is popular enough to be worth a verified URL" );
		}
	}

	/**
	 * Things that turn up under the same tags and are not form builders. Each
	 * of these was excluded on purpose; a regeneration that let one back in
	 * would have the Portal tab tell people to build their ticket form in a
	 * spam filter.
	 */
	public function test_what_is_not_a_form_plugin_stays_out() {
		$plugins = thrivedesk_form_plugins();

		foreach ( [ 'akismet', 'flamingo', 'mailchimp-for-wp', 'kirki', 'cmb2', 'elementor' ] as $slug ) {
			$this->assertArrayNotHasKey( $slug, $plugins, "$slug is not something you build a form with" );
		}
	}
}
