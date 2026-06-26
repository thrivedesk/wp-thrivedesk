<?php
/**
 * Regression tests for WooCommerce::order_status() — PR #166.
 *
 * Locks in the fix for the bug where asking for a specific order_id
 * returned a different order belonging to the same customer, because the
 * handler looked the customer up by email and then accepted any order_id
 * wc_get_order() returned.
 *
 * @package ThriveDesk
 */

class TD_Order_Status_Test extends WP_UnitTestCase {

    /** @var \ThriveDesk\Plugins\WooCommerce */
    private $plugin;

    /** @var string Lynne's billing email (matches issue #167). */
    private const LYNNE_EMAIL = 'lynnebooth.orlando@gmail.com';

    /** @var string A second customer's email. */
    private const OTHER_EMAIL = 'someone-else@example.com';

    public function set_up() {
        parent::set_up();

        if ( ! class_exists( '\ThriveDesk\Plugins\WooCommerce' ) ) {
            $this->markTestSkipped( 'WooCommerce integration class is not autoloaded.' );
        }

        if ( ! function_exists( 'wc_create_order' ) ) {
            $this->markTestSkipped( 'WooCommerce is not active in this test environment.' );
        }

        $this->plugin = \ThriveDesk\Plugins\WooCommerce::instance();
    }

    /**
     * Helper: create a completed order for the given billing email with
     * a unique `_order_number` meta so we can exercise the sequential
     * numbering lookup branch.
     */
    private function create_order_with_sequential_number( string $email, string $user_facing_number, float $amount = 10.0 ): int {
        $order = wc_create_order();
        $order->set_billing_email( $email );
        $order->set_status( 'completed' );
        $order->set_total( $amount );
        $order->update_meta_data( '_order_number', $user_facing_number );
        $order->save();

        return $order->get_id();
    }

    /**
     * Reproduces issue #167: a customer has two orders. Asking for one
     * must return THAT order, not the other.
     */
    public function test_order_status_returns_requested_order_not_first_match() {
        $order_a = $this->create_order_with_sequential_number( self::LYNNE_EMAIL, '71382' );
        $order_b = $this->create_order_with_sequential_number( self::LYNNE_EMAIL, '38538' );

        $this->plugin->customer_email = self::LYNNE_EMAIL;

        $result = $this->plugin->order_status( '71382' );

        $this->assertNotEmpty( $result, 'Asking for order 71382 must return an order.' );
        $this->assertSame(
            $order_a,
            $this->find_post_id_for_order_number( $result['order_id'] ?? '' ),
            'Asking for sequential number 71382 must return the post whose _order_number is 71382, not the other order under the same email.'
        );
        $this->assertNotSame(
            $order_a,
            $order_b,
            'Test fixture sanity: the two orders must be different posts.'
        );
    }

    /**
     * An email belonging to one customer must NOT be able to retrieve
     * another customer's order, even if the order_id is known.
     */
    public function test_order_status_rejects_cross_customer_lookup() {
        $this->create_order_with_sequential_number( self::LYNNE_EMAIL, '71382' );

        $this->plugin->customer_email = self::OTHER_EMAIL;

        $result = $this->plugin->order_status( '71382' );

        $this->assertSame(
            [],
            $result,
            'A different customer asking for Lynne\'s order must get an empty result (handler emits 404).'
        );
    }

    /**
     * Empty / missing order_id must return [] so the handler can emit 400.
     */
    public function test_order_status_returns_empty_for_missing_order_id() {
        $this->plugin->customer_email = self::LYNNE_EMAIL;

        $this->assertSame( [], $this->plugin->order_status( '' ) );
        $this->assertSame( [], $this->plugin->order_status( '0' ) );
    }

    /**
     * A non-existent order number must return [] so the handler can emit 404.
     */
    public function test_order_status_returns_empty_for_nonexistent_order() {
        $this->plugin->customer_email = self::LYNNE_EMAIL;

        $this->assertSame( [], $this->plugin->order_status( '99999999' ) );
    }

    /**
     * Email comparison is case-insensitive (WooCommerce doesn't guarantee
     * stored casing). Make sure the ownership check tolerates both.
     */
    public function test_order_status_matches_email_case_insensitively() {
        $this->create_order_with_sequential_number( self::LYNNE_EMAIL, '71382' );

        $this->plugin->customer_email = strtoupper( self::LYNNE_EMAIL );

        $result = $this->plugin->order_status( '71382' );

        $this->assertNotEmpty(
            $result,
            'Upper-case email from the request must still resolve the order (ownership check is case-insensitive).'
        );
    }

    /**
     * Direct post-id lookups (the fast path) must still work for callers
     * that pass the internal post id instead of the user-facing number.
     */
    public function test_order_status_accepts_internal_post_id() {
        $order_id = $this->create_order_with_sequential_number( self::LYNNE_EMAIL, '71382' );

        $this->plugin->customer_email = self::LYNNE_EMAIL;

        $result = $this->plugin->order_status( (string) $order_id );

        $this->assertNotEmpty(
            $result,
            'Asking for an order by its internal post id must still resolve.'
        );
    }

    /**
     * Look up the post whose _order_number equals the given string. Helper
     * for assertions, since the helper returns the order's user-facing
     * number (which the active numbering plugin controls), not the post id.
     */
    private function find_post_id_for_order_number( string $user_facing_number ): int {
        $posts = get_posts( [
            'post_type'      => wc_get_order_types( 'view-orders' ) ?: [ 'shop_order' ],
            'post_status'    => array_keys( wc_get_order_statuses() ) ?: [ 'any' ],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_order_number',
                    'value' => $user_facing_number,
                ],
            ],
        ] );

        return $posts[0] ?? 0;
    }
}
