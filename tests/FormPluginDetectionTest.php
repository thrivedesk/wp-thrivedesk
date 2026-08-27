<?php
/**
 * Finding the form plugin this site would build its ticket page with.
 *
 * The Portal tab tells people to make a page with a form plugin and point it at
 * a ThriveDesk inbox. Most sites already have one, so rather than leave that as
 * an instruction in the abstract, the tab names it and offers to open it.
 *
 * The rules worth pinning are the tie-breaks. A site with three form plugins
 * gets one answer, and it has to be the one the reader would have meant.
 *
 * @package ThriveDesk\Tests
 */

class FormPluginDetectionTest extends WP_UnitTestCase {

	/** @var array What active_plugins held before a case rewrote it. */
	private $restore = [];

	public function set_up() {
		parent::set_up();

		$this->restore = (array) get_option( 'active_plugins', [] );

		add_filter( 'thrivedesk_form_plugins', [ $this, 'catalogue' ] );
		add_filter( 'thrivedesk_form_plugin_actions', [ $this, 'actions' ] );
	}

	public function tear_down() {
		remove_filter( 'thrivedesk_form_plugins', [ $this, 'catalogue' ] );
		remove_filter( 'thrivedesk_form_plugin_actions', [ $this, 'actions' ] );

		wp_cache_delete( 'plugins', 'plugins' );
		update_option( 'active_plugins', $this->restore );

		parent::tear_down();
	}

	/** Most-installed first, which is the order detection walks. */
	public function catalogue(): array {
		return [
			'contact-form-7' => 'Contact Form 7',
			'wpforms-lite'   => 'WPForms',
			'ninja-forms'    => 'Ninja Forms',
			'obscure-forms'  => 'Obscure Forms',
		];
	}

	public function actions(): array {
		return [ 'contact-form-7' => 'admin.php?page=wpcf7-new' ];
	}

	/**
	 * Stand up a site with these plugins on disk, without putting any on disk.
	 *
	 * Through the object cache rather than a filter: `all_plugins` is applied by
	 * the plugins list table, not by get_plugins(), so filtering it changes
	 * nothing here. get_plugins() reads its own 'plugins' cache entry before it
	 * touches the filesystem, keyed by folder - '' being "all of them" - and
	 * seeding that is the seam core itself uses.
	 *
	 * @param string[] $files  Plugin files present on disk.
	 * @param string[] $active Which of them are switched on.
	 */
	private function site( array $files, array $active = [] ): void {
		$installed = [];

		foreach ( $files as $file ) {
			$installed[ $file ] = [ 'Name' => $file, 'Version' => '1.0' ];
		}

		wp_cache_set( 'plugins', [ '' => $installed ], 'plugins' );
		update_option( 'active_plugins', $active );
	}

	public function test_a_site_with_no_form_plugin_gets_no_card() {
		$this->site( [ 'akismet/akismet.php', 'hello.php' ] );

		$this->assertSame( [], thrivedesk_detected_form_plugin() );
	}

	public function test_the_active_form_plugin_is_the_answer() {
		$this->site( [ 'ninja-forms/ninja-forms.php' ], [ 'ninja-forms/ninja-forms.php' ] );

		$found = thrivedesk_detected_form_plugin();

		$this->assertSame( 'ninja-forms', $found['slug'] );
		$this->assertSame( 'Ninja Forms', $found['name'] );
		$this->assertTrue( $found['active'] );
	}

	/**
	 * The tie-break that matters most. Contact Form 7 outranks Ninja Forms in
	 * the catalogue, but an inactive plugin cannot build anything.
	 */
	public function test_an_active_plugin_beats_a_more_popular_inactive_one() {
		$this->site(
			[ 'contact-form-7/wp-contact-form-7.php', 'ninja-forms/ninja-forms.php' ],
			[ 'ninja-forms/ninja-forms.php' ]
		);

		$found = thrivedesk_detected_form_plugin();

		$this->assertSame( 'ninja-forms', $found['slug'] );
		$this->assertTrue( $found['active'] );
	}

	/** Two active: the one more sites run is the one more readers will mean. */
	public function test_the_more_popular_of_two_active_plugins_wins() {
		$this->site(
			[ 'ninja-forms/ninja-forms.php', 'contact-form-7/wp-contact-form-7.php' ],
			[ 'ninja-forms/ninja-forms.php', 'contact-form-7/wp-contact-form-7.php' ]
		);

		$this->assertSame( 'contact-form-7', thrivedesk_detected_form_plugin()['slug'] );
	}

	public function test_an_installed_but_inactive_plugin_is_still_worth_naming() {
		$this->site( [ 'wpforms-lite/wpforms.php' ] );

		$found = thrivedesk_detected_form_plugin();

		$this->assertSame( 'wpforms-lite', $found['slug'] );
		$this->assertFalse( $found['active'] );
	}

	public function test_the_builder_url_comes_from_the_verified_table() {
		$this->site( [ 'contact-form-7/wp-contact-form-7.php' ], [ 'contact-form-7/wp-contact-form-7.php' ] );

		$this->assertSame( 'admin.php?page=wpcf7-new', thrivedesk_detected_form_plugin()['new_form_url'] );
	}

	/**
	 * A plugin with no verified builder URL is still detected. It gets a
	 * sentence instead of a button - see the Portal panel - because a button
	 * that lands somewhere approximate is worse than none.
	 */
	public function test_a_plugin_with_no_verified_url_is_still_detected() {
		$this->site( [ 'obscure-forms/obscure-forms.php' ], [ 'obscure-forms/obscure-forms.php' ] );

		$found = thrivedesk_detected_form_plugin();

		$this->assertSame( 'obscure-forms', $found['slug'] );
		$this->assertSame( '', $found['new_form_url'] );
	}

	/**
	 * Nothing is bundled and nothing is fetched server-side: the icon is a
	 * wordpress.org URL the browser loads, and the lettermark behind it stays
	 * when it does not.
	 */
	public function test_the_icon_is_served_by_wordpress_org() {
		$this->site( [ 'wpforms-lite/wpforms.php' ], [ 'wpforms-lite/wpforms.php' ] );

		$this->assertSame(
			'https://ps.w.org/wpforms-lite/assets/icon-128x128.png',
			thrivedesk_detected_form_plugin()['icon']
		);
	}

	/** A plugin that is one file in the root has no directory to match on. */
	public function test_a_single_file_plugin_cannot_be_matched() {
		$this->site( [ 'contact-form-7.php' ], [ 'contact-form-7.php' ] );

		$this->assertSame( [], thrivedesk_detected_form_plugin() );
	}

	/** Nothing to match against - a release that lost the data file. */
	public function test_an_empty_catalogue_is_not_an_error() {
		remove_filter( 'thrivedesk_form_plugins', [ $this, 'catalogue' ] );
		add_filter( 'thrivedesk_form_plugins', '__return_empty_array', 99 );

		$this->site( [ 'contact-form-7/wp-contact-form-7.php' ], [ 'contact-form-7/wp-contact-form-7.php' ] );

		$this->assertSame( [], thrivedesk_detected_form_plugin() );

		remove_filter( 'thrivedesk_form_plugins', '__return_empty_array', 99 );
	}
}
