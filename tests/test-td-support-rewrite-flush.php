<?php
/**
 * Regression tests for the td-support WooCommerce endpoint rewrite-flush fix.
 *
 * Background: the td-support rewrite endpoint is registered dynamically when the
 * "Add to WooCommerce" setting is enabled, but rewrite rules were never flushed.
 * So /my-account/td-support/ returned a 404 (or fell through to the default account
 * page) until permalinks were manually re-saved.
 *
 * UserAccountPages now queues a flush whenever the toggle flips
 * (maybe_queue_rewrite_flush) and performs it on the next init
 * (maybe_flush_rewrite_rules). These tests lock in both halves, plus the
 * end-to-end result that the endpoint ends up in the persisted rewrite rules.
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

    // --- queueing: which membership changes schedule a flush ---------------

    /** Enabling the WooCommerce tab (off -> on) must queue a flush. */
    public function test_queue_flush_when_woocommerce_enabled() {
        $this->pages->maybe_queue_rewrite_flush( [], [ 'woocommerce' ] );

        $this->assertTrue(
            $this->flag_is_set(),
            'Enabling the WooCommerce tab must queue a rewrite flush.'
        );
    }

    /** Disabling the WooCommerce tab (on -> off) must queue a flush so the rule is dropped. */
    public function test_queue_flush_when_woocommerce_disabled() {
        $this->pages->maybe_queue_rewrite_flush( [ 'woocommerce' ], [] );

        $this->assertTrue(
            $this->flag_is_set(),
            'Disabling the WooCommerce tab must queue a rewrite flush.'
        );
    }

    /** No membership change (still on, or still off) must NOT queue a flush. */
    public function test_no_flush_when_membership_unchanged() {
        $this->pages->maybe_queue_rewrite_flush( [ 'woocommerce' ], [ 'woocommerce' ] );
        $this->assertFalse( $this->flag_is_set(), 'Still-enabled WC must not queue a flush.' );

        $this->pages->maybe_queue_rewrite_flush( [], [] );
        $this->assertFalse( $this->flag_is_set(), 'Never-enabled WC must not queue a flush.' );
    }

    /** Changing an unrelated page entry while WC stays on must NOT queue a flush. */
    public function test_no_flush_when_only_unrelated_pages_change() {
        $this->pages->maybe_queue_rewrite_flush( [ 'woocommerce' ], [ 'woocommerce', 'edd' ] );

        $this->assertFalse(
            $this->flag_is_set(),
            'WC membership unchanged (still present) must not queue a flush even if other pages change.'
        );
    }

    // --- execution: the queued flush runs once, then clears itself ---------

    /** A queued flush regenerates the persisted rewrite rules and clears the flag. */
    public function test_maybe_flush_regenerates_rules_and_clears_flag() {
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure( '/%postname%/' );

        delete_option( 'rewrite_rules' );
        update_option( \ThriveDesk\Portal\UserAccountPages::FLUSH_FLAG_OPTION, true );

        $this->pages->maybe_flush_rewrite_rules();

        $this->assertNotFalse(
            get_option( 'rewrite_rules' ),
            'A queued flush must regenerate the rewrite_rules option.'
        );
        $this->assertFalse(
            $this->flag_is_set(),
            'The flush flag must be cleared after flushing so it only runs once.'
        );
    }

    /** Without the flag set, the init callback is a no-op (no flush). */
    public function test_maybe_flush_is_noop_without_flag() {
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure( '/%postname%/' );

        delete_option( 'rewrite_rules' );
        delete_option( \ThriveDesk\Portal\UserAccountPages::FLUSH_FLAG_OPTION );

        $this->pages->maybe_flush_rewrite_rules();

        $this->assertFalse(
            get_option( 'rewrite_rules' ),
            'rewrite_rules must stay unset when no flush was queued.'
        );
    }

    // --- end-to-end: the route actually becomes resolvable -----------------

    /**
     * Once the endpoint is registered and the queued flush runs, the persisted
     * rewrite rules contain td-support — i.e. /my-account/td-support/ resolves
     * instead of 404ing. This is the exact condition the bug report described.
     */
    public function test_flushed_rules_include_td_support_endpoint() {
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure( '/%postname%/' );

        // Register the endpoint the way handle_pages() -> init does on a real request.
        $this->pages->register_td_portal_endpoint_for_woocommerce_account_page();

        update_option( \ThriveDesk\Portal\UserAccountPages::FLUSH_FLAG_OPTION, true );
        $this->pages->maybe_flush_rewrite_rules();

        $rules = get_option( 'rewrite_rules' );

        $this->assertIsArray( $rules, 'Rewrite rules should be persisted after a flush.' );
        $this->assertStringContainsString(
            'td-support',
            implode( ' ', array_keys( (array) $rules ) ),
            'Flushed rules must contain the td-support endpoint so the route resolves.'
        );
    }
}
