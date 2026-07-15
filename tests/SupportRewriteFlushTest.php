<?php
/**
 * Tests for the td-support WC endpoint rewrite-flush fix.
 *
 * The td-support endpoint is registered dynamically when the "Add to WooCommerce"
 * setting is on, but nothing used to flush rewrite rules afterwards. So
 * /my-account/td-support/ kept 404-ing until someone manually re-saved permalinks.
 *
 * UserAccountPages now queues a flush when the toggle flips and runs it on the
 * next init. These tests cover both halves plus the end-to-end case where the
 * endpoint actually ends up in the persisted rewrite rules.
 *
 * @package ThriveDesk
 */

class TD_Support_Endpoint_Rewrite_Test extends WP_UnitTestCase {

    /** @var \ThriveDesk\Portal\UserAccountPages */
    private $pages;

    public function set_up() {
        parent::set_up();

        if ( ! class_exists( '\ThriveDesk\Portal\UserAccountPages' ) ) {
            $this->markTestSkipped( 'UserAccountPages is not autoloaded.' );
        }

        $this->pages = \ThriveDesk\Portal\UserAccountPages::instance();
        delete_option( \ThriveDesk\Portal\UserAccountPages::FLUSH_FLAG_OPTION );
    }

    private function flag_is_set(): bool {
        return (bool) get_option( \ThriveDesk\Portal\UserAccountPages::FLUSH_FLAG_OPTION );
    }

    // queueing -------------------------------------------------------------

    public function test_queue_flush_when_woocommerce_enabled() {
        $this->pages->maybe_queue_rewrite_flush( [], [ 'woocommerce' ] );

        $this->assertTrue(
            $this->flag_is_set(),
            'Enabling the WC tab should queue a rewrite flush.'
        );
    }

    public function test_queue_flush_when_woocommerce_disabled() {
        $this->pages->maybe_queue_rewrite_flush( [ 'woocommerce' ], [] );

        $this->assertTrue(
            $this->flag_is_set(),
            'Disabling the WC tab should queue a rewrite flush so the rule gets dropped.'
        );
    }

    public function test_no_flush_when_membership_unchanged() {
        $this->pages->maybe_queue_rewrite_flush( [ 'woocommerce' ], [ 'woocommerce' ] );
        $this->assertFalse( $this->flag_is_set(), 'WC still on, no flush needed.' );

        $this->pages->maybe_queue_rewrite_flush( [], [] );
        $this->assertFalse( $this->flag_is_set(), 'WC never on, no flush needed.' );
    }

    public function test_no_flush_when_only_unrelated_pages_change() {
        $this->pages->maybe_queue_rewrite_flush( [ 'woocommerce' ], [ 'woocommerce', 'edd' ] );

        $this->assertFalse(
            $this->flag_is_set(),
            'WC membership unchanged, other pages toggling should not queue a flush.'
        );
    }

    // execution ------------------------------------------------------------

    public function test_maybe_flush_regenerates_rules_and_clears_flag() {
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure( '/%postname%/' );

        delete_option( 'rewrite_rules' );
        update_option( \ThriveDesk\Portal\UserAccountPages::FLUSH_FLAG_OPTION, true );

        $this->pages->maybe_flush_rewrite_rules();

        $this->assertNotFalse(
            get_option( 'rewrite_rules' ),
            'Flush should regenerate the rewrite_rules option.'
        );
        $this->assertFalse(
            $this->flag_is_set(),
            'Flag should clear after flushing so it only runs once.'
        );
    }

    public function test_maybe_flush_is_noop_without_flag() {
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure( '/%postname%/' );

        delete_option( 'rewrite_rules' );
        delete_option( \ThriveDesk\Portal\UserAccountPages::FLUSH_FLAG_OPTION );

        $this->pages->maybe_flush_rewrite_rules();

        $this->assertFalse(
            get_option( 'rewrite_rules' ),
            'No queued flush, rewrite_rules should stay unset.'
        );
    }

    // end-to-end -----------------------------------------------------------

    /**
     * Once the endpoint is registered and the queued flush runs, the persisted
     * rewrite rules contain td-support (i.e. /my-account/td-support/ resolves
     * instead of 404-ing.
     */
    public function test_flushed_rules_include_td_support_endpoint() {
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure( '/%postname%/' );

        // Same call handle_pages() -> init makes on a real request.
        $this->pages->register_td_portal_endpoint_for_woocommerce_account_page();

        update_option( \ThriveDesk\Portal\UserAccountPages::FLUSH_FLAG_OPTION, true );
        $this->pages->maybe_flush_rewrite_rules();

        $rules = get_option( 'rewrite_rules' );

        $this->assertIsArray( $rules, 'Rewrite rules should be persisted after a flush.' );
        $this->assertStringContainsString(
            'td-support',
            implode( ' ', array_keys( (array) $rules ) ),
            'Flushed rules should contain the td-support endpoint.'
        );
    }
}