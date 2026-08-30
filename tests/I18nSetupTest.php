<?php
/**
 * The wiring that makes translation work at all.
 *
 * None of this is about individual strings - WordPress.WP.I18n covers those,
 * repo-wide, via phpcs-i18n.xml. What is pinned here is the plumbing they run
 * through, all three parts of which were broken at once and silently: the
 * plugin declared a Domain Path that did not exist, nothing ever called
 * load_plugin_textdomain(), and wp_set_script_translations() was called without
 * a path. Every string in the plugin was correctly marked up and none of them
 * could ever have been translated.
 *
 * Failures here do not surface as errors. They surface as an English UI on a
 * site that installed a translation, which nobody reports as a bug.
 *
 * @package ThriveDesk\Tests
 */

class I18nSetupTest extends WP_UnitTestCase {

	/**
	 * The headers WordPress reads to find bundled translations.
	 */
	private function headers(): array {
		return get_file_data(
			THRIVEDESK_FILE,
			array(
				'TextDomain' => 'Text Domain',
				'DomainPath' => 'Domain Path',
			)
		);
	}

	public function test_the_declared_text_domain_is_the_one_every_string_uses() {
		$this->assertSame( 'thrivedesk', $this->headers()['TextDomain'] );
	}

	public function test_the_domain_path_points_at_a_directory_that_exists() {
		// It said /languages, and there has never been a /languages directory.
		$path = $this->headers()['DomainPath'];

		$this->assertNotSame( '', $path, 'Domain Path must be declared' );
		$this->assertDirectoryExists(
			THRIVEDESK_DIR . $path,
			'Domain Path names where WordPress looks for bundled translations; it has to exist'
		);
	}

	public function test_the_translation_template_lives_where_the_domain_path_says() {
		$this->assertFileExists( THRIVEDESK_DIR . $this->headers()['DomainPath'] . '/thrivedesk.pot' );
	}

	public function test_the_domain_path_ships_in_the_release() {
		// resources/sass, /js, /css and /images are all excluded from the wp.org
		// build. If resources/languages ever joins them the header would point
		// at a directory that exists in the repo and not in the plugin.
		$distignore = file_get_contents( THRIVEDESK_DIR . '/.distignore' );
		$path       = ltrim( $this->headers()['DomainPath'], '/' );

		$this->assertStringNotContainsString(
			'/' . $path,
			$distignore,
			'the directory holding the translations must not be excluded from the release'
		);
	}

	// loading -----------------------------------------------------------------

	public function test_the_text_domain_is_loaded_on_init() {
		// On init rather than at load: since WordPress 6.7 an earlier call warns
		// "translation loading triggered too early" and can load the wrong
		// locale for the current user.
		$this->assertNotFalse(
			has_action( 'init', array( ThriveDesk::instance(), 'load_textdomain' ) ),
			'nothing loaded the bundled translations at all before this'
		);
	}

	public function test_loading_registers_the_directory_the_header_names() {
		// Since WordPress 6.7 load_plugin_textdomain() does not read a .mo file;
		// it records the directory with the textdomain registry and the actual
		// load happens just in time. So the thing to assert is that the registry
		// ends up pointing at our Domain Path - which is what was wrong, since
		// nothing called load_plugin_textdomain() at all.
		global $wp_textdomain_registry;

		$dir    = THRIVEDESK_DIR . $this->headers()['DomainPath'];
		$locale = 'fr_FR';
		$mofile = $dir . '/thrivedesk-' . $locale . '.mo';

		// The registry resolves a path by looking for a file for that locale in
		// it, so there has to be one for the lookup to have an answer.
		$planted = ! file_exists( $mofile );

		if ( $planted ) {
			file_put_contents( $mofile, '' );
		}

		ThriveDesk::instance()->load_textdomain();

		$found = $wp_textdomain_registry->get( 'thrivedesk', $locale );

		if ( $planted ) {
			unlink( $mofile );
		}

		$this->assertSame(
			trailingslashit( $dir ),
			$found,
			'the plugin\'s translations must resolve to its own Domain Path'
		);
	}

	// script translations ------------------------------------------------------

	/**
	 * Every wp_set_script_translations() call passes a path.
	 *
	 * Read out of the source rather than by enqueuing, which would mean booting
	 * three admin screens. Blunt, and it is the exact regression that shipped:
	 * without the third argument WordPress looks only in WP_LANG_DIR, so the
	 * JSON files bundled under Domain Path are never read and every JavaScript
	 * string stays English however the site is translated.
	 */
	public function test_every_script_translation_call_names_a_path() {
		$sources = array(
			THRIVEDESK_DIR . '/src/Admin.php',
			THRIVEDESK_DIR . '/src/Conversations/Conversation.php',
		);

		$calls = 0;

		foreach ( $sources as $file ) {
			preg_match_all(
				'/wp_set_script_translations\(\s*([^;]*?)\);/s',
				(string) file_get_contents( $file ),
				$matches
			);

			foreach ( $matches[1] as $args ) {
				++$calls;

				$this->assertSame(
					2,
					substr_count( $args, ',' ),
					'wp_set_script_translations() needs a handle, a domain and a path, got: ' . trim( $args )
				);
				$this->assertStringContainsString(
					'/resources/languages',
					$args,
					'the path must be the plugin\'s own Domain Path'
				);
			}
		}

		$this->assertGreaterThanOrEqual( 3, $calls, 'expected the admin, portal and admin-app handles' );
	}
}
