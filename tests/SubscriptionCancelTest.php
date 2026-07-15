<?php
/**
 * Tests for the WooCommerce subscription cancel handler.
 *
 * The business logic lives in \ThriveDesk\Plugins\WooCommerce::
 * cancel_subscriptions_for_order(); \ThriveDesk\Api::woocommerce_subscription_cancel()
 * is a thin dispatcher that converts the structured result into a JSON
 * response. Both layers are exercised here.
 *
 * WooCommerce Subscriptions itself is stubbed (tests/stubs/wcs-functions.php)
 * because the real extension is commercial and absent from this environment.
 * Success and error responses both leave through wp_send_json + wp_die, so
 * assertions are made on the captured JSON body, not on exceptions.
 *
 * @package ThriveDesk
 */

class TD_Subscription_Cancel_Test extends WP_UnitTestCase {

    /** @var \ThriveDesk\Plugins\WooCommerce */
    private $plugin;

    public function set_up() {
        parent::set_up();

        if ( ! class_exists( '\ThriveDesk\Plugins\WooCommerce' ) ) {
            $this->markTestSkipped( 'WooCommerce integration class is not autoloaded.' );
        }

        if ( ! function_exists( 'wc_create_order' ) ) {
            $this->markTestSkipped( 'WooCommerce is not active in this test environment.' );
        }

        // Order IDs repeat across tests (the DB rolls back), so the
        // in-memory subscription registry must start clean every time.
        if ( class_exists( 'TD_Test_WCS_Registry' ) ) {
            TD_Test_WCS_Registry::reset();
        }

        $this->plugin = \ThriveDesk\Plugins\WooCommerce::instance();
    }

    /**
     * Dispatch a woocommerce_subscription_cancel request through the Api
     * singleton and capture the JSON response body.
     *
     * Mirrors the dispatch helper used in OrderStatusTest.php.
     */
    private function dispatch_subscription_cancel( string $order_id, string $subscription_status, string $order_types = '' ): array {
        $api = \ThriveDesk\Api::instance();

        // Inject the plugin instance into the Api singleton.
        $plugin_prop = ( new \ReflectionClass( $api ) )->getProperty( 'plugin' );
        $plugin_prop->setAccessible( true );
        $plugin_prop->setValue( $api, $this->plugin );

        // Catch wp_die to capture wp_send_json output.
        add_filter( 'wp_doing_ajax', '__return_true' );
        add_filter( 'wp_die_ajax_handler', static function () {
            return static function () {
                throw new \WPDieException( 'wp_die' );
            };
        } );

        ob_start();
        try {
            $api->woocommerce_subscription_cancel( $order_id, $subscription_status, $order_types );
        } catch ( \WPDieException $e ) {
            // Expected: every response, success or error, ends in wp_die.
        }
        $body = ob_get_clean();

        return json_decode( $body, true ) ?: [];
    }

    private function create_order( string $status = 'processing' ): \WC_Order {
        $order = wc_create_order();
        $order->set_status( $status );
        $order->save();

        return $order;
    }

    /** @return object subscription (stub or real WC_Subscription) */
    private function create_subscription( int $order_id, string $status = 'active' ) {
        $subscription = wcs_create_subscription( [
            'order_id'         => $order_id,
            'status'           => $status,
            'billing_period'   => 'month',
            'billing_interval' => 1,
        ] );
        $this->assertNotWPError( $subscription );

        return $subscription;
    }

    /** Link an order to a subscription the way Subscriptions marks renewals. */
    private function create_renewal_order( $subscription ): \WC_Order {
        $renewal = wc_create_order();
        $renewal->set_status( 'processing' );
        $renewal->update_meta_data( '_subscription_renewal', $subscription->get_id() );
        $renewal->save();

        return $renewal;
    }

    public function test_subscription_cancel_sets_status_to_cancelled() {
        $order        = $this->create_order();
        $subscription = $this->create_subscription( $order->get_id() );

        $response = $this->dispatch_subscription_cancel( (string) $order->get_id(), 'cancelled' );

        $this->assertSame( 'Success', $response['message'] ?? null );
        $this->assertSame( [ (string) $subscription->get_id() ], $response['data']['subscription_ids'] ?? [] );
        $this->assertSame( 'cancelled', wcs_get_subscription( $subscription->get_id() )->get_status() );
    }

    public function test_subscription_cancel_sets_status_to_pending_cancel() {
        $order        = $this->create_order();
        $subscription = $this->create_subscription( $order->get_id() );

        $response = $this->dispatch_subscription_cancel( (string) $order->get_id(), 'pending-cancel' );

        $this->assertSame( 'Success', $response['message'] ?? null );
        $this->assertSame( 'pending-cancel', wcs_get_subscription( $subscription->get_id() )->get_status() );
    }

    public function test_subscription_cancel_updates_multiple_subscriptions_for_same_order() {
        $order = $this->create_order();
        $sub_a = $this->create_subscription( $order->get_id() );
        $sub_b = $this->create_subscription( $order->get_id() );

        $response = $this->dispatch_subscription_cancel( (string) $order->get_id(), 'cancelled' );

        $this->assertSame( 'Success', $response['message'] ?? null );
        $updated_ids = $response['data']['subscription_ids'] ?? [];
        $this->assertCount( 2, $updated_ids );
        $this->assertContains( (string) $sub_a->get_id(), $updated_ids );
        $this->assertContains( (string) $sub_b->get_id(), $updated_ids );

        $this->assertSame( 'cancelled', wcs_get_subscription( $sub_a->get_id() )->get_status() );
        $this->assertSame( 'cancelled', wcs_get_subscription( $sub_b->get_id() )->get_status() );
    }

    public function test_subscription_cancel_rejects_invalid_status() {
        $order        = $this->create_order();
        $subscription = $this->create_subscription( $order->get_id() );

        $response = $this->dispatch_subscription_cancel( (string) $order->get_id(), 'not-a-real-status' );

        $this->assertSame( "Invalid subscription_status. Use 'cancelled' or 'pending-cancel'.", $response['message'] ?? null );
        $this->assertSame( 'active', wcs_get_subscription( $subscription->get_id() )->get_status() );
    }

    public function test_subscription_cancel_is_idempotent_when_already_cancelled() {
        // Once the subscription is already cancelled, a second call should
        // succeed (200) with an empty subscription_ids list. Cancellation is
        // the desired end state, and treating "already there" as an error
        // would surface confusing messages to the agent.
        $order = $this->create_order();
        $this->create_subscription( $order->get_id(), 'cancelled' );

        $response = $this->dispatch_subscription_cancel( (string) $order->get_id(), 'cancelled' );

        $this->assertSame( 'Success', $response['message'] ?? null );
        $this->assertSame( [], $response['data']['subscription_ids'] ?? [ 'unexpected' ] );
    }

    public function test_subscription_cancel_is_a_quiet_noop_for_order_without_subscriptions() {
        // The handler runs automatically after every panel cancel/refund,
        // so a plain order must produce a clean success, not an error the
        // agent would see.
        $order = $this->create_order();

        $response = $this->dispatch_subscription_cancel( (string) $order->get_id(), 'cancelled' );

        $this->assertSame( 'Success', $response['message'] ?? null );
        $this->assertSame( [], $response['data']['subscription_ids'] ?? [ 'unexpected' ] );
    }

    public function test_subscription_cancel_returns_404_for_missing_order() {
        $response = $this->dispatch_subscription_cancel( '999999999', 'cancelled' );

        $this->assertSame( 'Order not found.', $response['message'] ?? null );
    }

    public function test_subscription_cancel_does_not_cancel_when_passed_a_renewal_order() {
        // Default order_types is parent-only: cancelling a renewal order
        // from the panel must NOT stop the underlying subscription.
        $parent       = $this->create_order();
        $subscription = $this->create_subscription( $parent->get_id() );
        $renewal      = $this->create_renewal_order( $subscription );

        $response = $this->dispatch_subscription_cancel( (string) $renewal->get_id(), 'cancelled' );

        $this->assertSame( 'Success', $response['message'] ?? null );
        $this->assertSame( [], $response['data']['subscription_ids'] ?? [ 'unexpected' ] );
        $this->assertSame( 'active', wcs_get_subscription( $subscription->get_id() )->get_status() );
    }

    public function test_subscription_cancel_with_renewal_order_type_cancels_from_a_renewal_order() {
        // The full-refund follow-up passes order_types=parent,renewal so
        // that refunding a renewal order stops the billing it belongs to.
        $parent       = $this->create_order();
        $subscription = $this->create_subscription( $parent->get_id() );
        $renewal      = $this->create_renewal_order( $subscription );

        $response = $this->dispatch_subscription_cancel( (string) $renewal->get_id(), 'cancelled', 'parent,renewal' );

        $this->assertSame( 'Success', $response['message'] ?? null );
        $this->assertSame( [ (string) $subscription->get_id() ], $response['data']['subscription_ids'] ?? [] );
        $this->assertSame( 'cancelled', wcs_get_subscription( $subscription->get_id() )->get_status() );
    }

    public function test_subscription_cancel_rejects_unknown_order_types() {
        $order = $this->create_order();
        $this->create_subscription( $order->get_id() );

        $response = $this->dispatch_subscription_cancel( (string) $order->get_id(), 'cancelled', 'resubscribe' );

        $this->assertSame( "Invalid order_types. Use 'parent' and/or 'renewal'.", $response['message'] ?? null );
    }

    public function test_plugin_cancel_subscriptions_for_order_returns_structured_result() {
        // Exercises the plugin-class contract directly, not via the Api
        // dispatcher. The plugin returns [ 'found' => bool, 'updated_ids' => int[] ]
        // so the dispatcher can distinguish "no subscriptions" (404) from
        // "already cancelled" (200, empty list).
        $order = $this->create_order();

        // No subscriptions for this order, so the result must report not found.
        $result = $this->plugin->cancel_subscriptions_for_order( (string) $order->get_id(), 'cancelled' );
        $this->assertFalse( $result['found'] );
        $this->assertSame( [], $result['updated_ids'] );

        // Add a subscription and try again.
        $sub = $this->create_subscription( $order->get_id() );

        $result = $this->plugin->cancel_subscriptions_for_order( (string) $order->get_id(), 'cancelled' );
        $this->assertTrue( $result['found'] );
        $this->assertSame( [ $sub->get_id() ], $result['updated_ids'] );

        // Idempotent: sub is now cancelled.
        $result = $this->plugin->cancel_subscriptions_for_order( (string) $order->get_id(), 'cancelled' );
        $this->assertTrue( $result['found'] );
        $this->assertSame( [], $result['updated_ids'] );
    }

    public function test_plugin_resolves_order_by_sequential_order_number() {
        // The panel sends the customer-facing order number, which on stores
        // with sequential numbering differs from the post ID.
        $order = $this->create_order();
        $order->update_meta_data( '_order_number', 'TD-77001' );
        $order->save();

        $subscription = $this->create_subscription( $order->get_id() );

        $result = $this->plugin->cancel_subscriptions_for_order( 'TD-77001', 'cancelled' );

        $this->assertTrue( $result['found'] );
        $this->assertSame( [ $subscription->get_id() ], $result['updated_ids'] );
        $this->assertSame( 'cancelled', wcs_get_subscription( $subscription->get_id() )->get_status() );
    }

    public function test_plugin_skips_subscriptions_that_cannot_transition() {
        // An expired subscription can't be cancelled. The handler must skip
        // it (found, nothing updated) instead of letting WCS throw.
        $order = $this->create_order();
        $this->create_subscription( $order->get_id(), 'expired' );

        $result = $this->plugin->cancel_subscriptions_for_order( (string) $order->get_id(), 'cancelled' );

        $this->assertTrue( $result['found'] );
        $this->assertSame( [], $result['updated_ids'] );
    }

    public function test_plugin_rejects_invalid_order_types() {
        $order = $this->create_order();

        $this->expectException( \InvalidArgumentException::class );

        $this->plugin->cancel_subscriptions_for_order( (string) $order->get_id(), 'cancelled', [ 'switch' ] );
    }
}
