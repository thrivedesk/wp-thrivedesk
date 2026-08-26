<?php
/**
 * create_portal_page() hangs off 'activated_plugin', which fires for EVERY
 * plugin activation on the site, and it ignored the $plugin argument - so
 * activating anything at all could publish a ThriveDesk page. It also matched
 * on the page title, which meant an unrelated page sharing that title was
 * silently adopted, and it hardcoded post_author => 1.
 *
 * @package ThriveDesk\Tests
 */

class PortalPageCreationTest extends WP_UnitTestCase {

	private const TITLE = 'Thrivedesk Support Portal';

	/** @var int */
	private $admin;

	public function set_up() {
		parent::set_up();

		$this->admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin );

		delete_option( \ThriveDesk\Admin::PORTAL_PAGE_OPTION );
	}

	private function activate( string $plugin ): void {
		\ThriveDesk\Admin::instance()->create_portal_page( $plugin );
	}

	private function this_plugin(): string {
		return plugin_basename( THRIVEDESK_FILE );
	}

	private function portal_pages(): array {
		return get_posts(
			[
				'post_type'   => 'page',
				'title'       => self::TITLE,
				'post_status' => 'any',
				'numberposts' => -1,
			]
		);
	}

	public function test_activating_some_other_plugin_creates_nothing() {
		$this->activate( 'akismet/akismet.php' );

		$this->assertSame( [], $this->portal_pages(), 'another plugin\'s activation is none of our business' );
		$this->assertFalse( get_option( \ThriveDesk\Admin::PORTAL_PAGE_OPTION, false ) );
	}

	public function test_activating_this_plugin_creates_the_page_and_records_its_id() {
		$this->activate( $this->this_plugin() );

		$pages = $this->portal_pages();

		$this->assertCount( 1, $pages );
		$this->assertSame( (int) $pages[0]->ID, (int) get_option( \ThriveDesk\Admin::PORTAL_PAGE_OPTION ) );
		$this->assertSame( '[thrivedesk_portal]', $pages[0]->post_content );
	}

	public function test_the_page_is_owned_by_the_user_who_activated_it() {
		$this->activate( $this->this_plugin() );

		$page = get_post( (int) get_option( \ThriveDesk\Admin::PORTAL_PAGE_OPTION ) );

		$this->assertSame(
			$this->admin,
			(int) $page->post_author,
			'post_author => 1 assumes user 1 exists and is the right owner'
		);
	}

	public function test_reactivating_does_not_create_a_second_page() {
		$this->activate( $this->this_plugin() );
		$this->activate( $this->this_plugin() );

		$this->assertCount( 1, $this->portal_pages() );
	}

	/**
	 * Even after the admin renames it - the id is what identifies it now, not
	 * the title.
	 */
	public function test_a_renamed_page_is_not_recreated() {
		$this->activate( $this->this_plugin() );

		$page_id = (int) get_option( \ThriveDesk\Admin::PORTAL_PAGE_OPTION );
		wp_update_post( [ 'ID' => $page_id, 'post_title' => 'Support' ] );

		$this->activate( $this->this_plugin() );

		$this->assertSame( $page_id, (int) get_option( \ThriveDesk\Admin::PORTAL_PAGE_OPTION ) );
		$this->assertSame( [], $this->portal_pages(), 'no fresh copy under the old title' );
	}

	/**
	 * Upgrade path: installs from before the id was recorded already have the
	 * page the old title match created.
	 */
	public function test_an_existing_page_from_before_the_option_is_adopted_not_duplicated() {
		$legacy = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_title'  => self::TITLE,
				'post_status' => 'publish',
			]
		);

		$this->activate( $this->this_plugin() );

		$this->assertSame( $legacy, (int) get_option( \ThriveDesk\Admin::PORTAL_PAGE_OPTION ) );
		$this->assertCount( 1, $this->portal_pages() );
	}

	public function test_a_caller_without_the_capability_creates_nothing() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$this->activate( $this->this_plugin() );

		$this->assertSame( [], $this->portal_pages() );
	}

	/**
	 * get_page_by_title() passed get_post_status() with no argument, which
	 * reads the global $post - absent on activation, so it returned false and
	 * the status filter never did what it reads as.
	 */
	public function test_the_title_lookup_finds_a_published_page() {
		$page = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_title'  => self::TITLE,
				'post_status' => 'publish',
			]
		);

		$found = \ThriveDesk\Admin::instance()->get_page_by_title( self::TITLE );

		$this->assertInstanceOf( 'WP_Post', $found );
		$this->assertSame( $page, $found->ID );
	}
}
