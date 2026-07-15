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
     * Helper to mock and dispatch an order status request and capture the response.
     *
     * @param  string  $email
     * @param  string  $order_id
     * @return array
     * @throws ReflectionException
     */
    private function dispatch_order_status_request( string $email, string $order_id ): array {
        $api = \ThriveDesk\Api::instance();

        // Inject the plugin instance into the Api singleton.
        $plugin_prop = ( new \ReflectionClass( $api ) )->getProperty( 'plugin' );
        $plugin_prop->setAccessible( true );
        $plugin_prop->setValue( $api, $this->plugin );

        $_REQUEST['email']    = $email;
        $_REQUEST['order_id'] = $order_id;

        // Catch wp_die calls to capture output from wp_send_json.
        add_filter( 'wp_doing_ajax', '__return_true' );
        add_filter( 'wp_die_ajax_handler', static function () {
            return static function () {
                throw new \WPDieException( 'wp_die' );
            };
        } );

        ob_start();
        try {
            $api->get_woocommerce_order_status();
        } catch ( \WPDieException $e ) {
            // Expected exception from wp_die.
        }
        $body = ob_get_clean();

        unset( $_REQUEST['email'], $_REQUEST['order_id'] );

        return json_decode( $body, true ) ?: [];
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

        $this->assertNotEmpty( $result, 'Order should be found.' );
        $this->assertNotSame(
            $order_a,
            $order_b,
            'Fixture sanity check: orders must be different.'
        );
        // With no numbering plugin active, order_id should equal the default post ID.
        $this->assertSame(
            (string) $order_a,
            (string) ( $result['order_id'] ?? '' ),
            'Should return order A, not order B.'
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
     * Test resolving order status with non-numeric characters in the order number.
     */
    public function test_order_status_resolves_order_number_with_special_characters() {
        $this->create_order_with_sequential_number( self::LYNNE_EMAIL, '2025/001' );

        $this->plugin->customer_email = self::LYNNE_EMAIL;

        $result = $this->plugin->order_status( '2025/001' );

        $this->assertNotEmpty(
            $result,
            'Order status should resolve for invoice-style order numbers.'
        );
    }

    /**
     * Test that the API endpoint preserves custom order number characters.
     */
    public function test_order_status_endpoint_preserves_custom_order_number() {
        $order_id = $this->create_order_with_sequential_number( self::LYNNE_EMAIL, '2025/001' );

        $response = $this->dispatch_order_status_request( self::LYNNE_EMAIL, '2025/001' );

        $this->assertSame(
            'Success',
            $response['message'] ?? null,
            'API should return a successful response.'
        );
        $this->assertSame(
            (string) $order_id,
            (string) ( $response['data']['order_id'] ?? '' ),
            'API should return the correct order details.'
        );
    }

    /**
     * Dispatch a woocommerce_order_status_update request through the Api
     * singleton and capture the JSON response body.
     */
    private function dispatch_order_status_update( string $order_id, string $order_status ): array {
        $api = \ThriveDesk\Api::instance();

        $plugin_prop = ( new \ReflectionClass( $api ) )->getProperty( 'plugin' );
        $plugin_prop->setAccessible( true );
        $plugin_prop->setValue( $api, $this->plugin );

        add_filter( 'wp_doing_ajax', '__return_true' );
        add_filter( 'wp_die_ajax_handler', static function () {
            return static function () {
                throw new \WPDieException( 'wp_die' );
            };
        } );

        ob_start();
        try {
            $api->woocommerce_order_status_update( $order_id, $order_status );
        } catch ( \WPDieException $e ) {
            // Expected exception from wp_die.
        }
        $body = ob_get_clean();

        return json_decode( $body, true ) ?: [];
    }

    /**
     * The status-update handler must resolve the customer-facing order
     * number. It used to build the order from the raw value, which casts a
     * number like 'ord_6' to post ID 0 and reports success while updating
     * nothing.
     */
    public function test_order_status_update_resolves_sequential_order_number() {
        $order_id = $this->create_order_with_sequential_number( self::LYNNE_EMAIL, 'ord_6' );

        $response = $this->dispatch_order_status_update( 'ord_6', 'wc-cancelled' );

        $this->assertSame( 'Success', $response['message'] ?? null );
        $this->assertSame( 'cancelled', wc_get_order( $order_id )->get_status() );
    }

    public function test_order_status_update_works_with_plain_post_id() {
        $order_id = $this->create_order_with_sequential_number( self::LYNNE_EMAIL, (string) PHP_INT_MAX );

        $response = $this->dispatch_order_status_update( (string) $order_id, 'wc-completed' );

        $this->assertSame( 'Success', $response['message'] ?? null );
        $this->assertSame( 'completed', wc_get_order( $order_id )->get_status() );
    }

    /**
     * The listener drives other plugins (order status changes fire the
     * WooCommerce Subscriptions scheduler, which finishes its own setup at
     * init 10), so it must not run inside init itself.
     */
    public function test_api_listener_runs_after_all_plugins_finish_init() {
        $api = \ThriveDesk\Api::instance();

        $this->assertNotFalse(
            has_action( 'wp_loaded', array( $api, 'api_listener' ) ),
            'api_listener must be hooked to wp_loaded.'
        );
        $this->assertFalse(
            has_action( 'init', array( $api, 'api_listener' ) ),
            'api_listener must not be hooked to init.'
        );
    }

    public function test_order_status_update_returns_404_for_unknown_order() {
        $before = count( wc_get_orders( [ 'limit' => -1, 'return' => 'ids' ] ) );

        $response = $this->dispatch_order_status_update( 'no-such-order', 'wc-cancelled' );

        $this->assertSame( 'Order not found.', $response['message'] ?? null );

        // The old handler saved a blank order as a side effect of the blind
        // update; a miss must not create anything.
        $after = count( wc_get_orders( [ 'limit' => -1, 'return' => 'ids' ] ) );
        $this->assertSame( $before, $after );
    }
}
