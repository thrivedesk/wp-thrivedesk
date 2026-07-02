<?php
/**
 * Tests for the WooCommerce subscription cancel handler
 * (Api::woocommerce_subscription_cancel).
 *
 * Locks in the fix for the bug where cancelling or fully refunding an order
 * from the ThriveDesk ticket panel only updated the parent order, leaving the
 * underlying WooCommerce Subscriptions subscription Active and still billing.
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

        if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            $this->markTestSkipped( 'WooCommerce Subscriptions extension is not active in this test environment.' );
        }

        $this->plugin = \ThriveDesk\Plugins\WooCommerce::instance();
    }

    /**
     * Dispatch a woocommerce_subscription_cancel request through the Api
     * singleton and capture the JSON response body.
     *
     * Mirrors the dispatch helper used in test-order-status.php.
     */
    private function dispatch_subscription_cancel( string $order_id, string $subscription_status ): array {
        $api = \ThriveDesk\Api::instance();

        // Inject the plugin instance into the Api singleton.
        $plugin_prop = ( new \ReflectionClass( $api ) )->getProperty( 'plugin' );
        $plugin_prop->setAccessible( true );
        $plugin_prop->setValue( $api, $this->plugin );

        $_REQUEST['order_id']            = $order_id;
        $_REQUEST['subscription_status'] = $subscription_status;

        // Catch wp_die to capture wp_send_json output.
        add_filter( 'wp_doing_ajax', '__return_true' );
        add_filter( 'wp_die_ajax_handler', static function () {
            return static function () {
                throw new \WPDieException( 'wp_die' );
            };
        } );

        ob_start();
        try {
            $api->woocommerce_subscription_cancel( $order_id, $subscription_status );
        } catch ( \WPDieException $e ) {
            // Expected from wp_die on success.
        } catch ( \Exception $e ) {
            // The handler short-circuits with apiResponse->error() for failures,
            // which throws via wp_send_json. Re-throw so the test can assert.
            ob_end_clean();
            unset( $_REQUEST['order_id'], $_REQUEST['subscription_status'] );
            throw $e;
        }
        $body = ob_get_clean();

        unset( $_REQUEST['order_id'], $_REQUEST['subscription_status'] );

        return json_decode( $body, true ) ?: [];
    }

    public function test_subscription_cancel_sets_status_to_cancelled() {
        $order = wc_create_order();
        $order->set_status( 'processing' );
        $order->save();

        // Create a subscription tied to the order via the Subscriptions helper.
        $subscription = wcs_create_subscription( [
            'order_id' => $order->get_id(),
            'status'   => 'active',
            'billing_period' => 'month',
            'billing_interval' => 1,
        ] );
        $this->assertNotWPError( $subscription );

        $response = $this->dispatch_subscription_cancel( (string) $order->get_id(), 'cancelled' );

        $this->assertSame( 'Success', $response['message'] ?? null );
        $this->assertSame( [ (string) $subscription->get_id() ], $response['data']['subscription_ids'] ?? [] );

        $subscription = wcs_get_subscription( $subscription->get_id() );
        $this->assertSame( 'cancelled', $subscription->get_status() );
    }

    public function test_subscription_cancel_sets_status_to_pending_cancel() {
        $order = wc_create_order();
        $order->set_status( 'processing' );
        $order->save();

        $subscription = wcs_create_subscription( [
            'order_id' => $order->get_id(),
            'status'   => 'active',
            'billing_period' => 'month',
            'billing_interval' => 1,
        ] );
        $this->assertNotWPError( $subscription );

        $response = $this->dispatch_subscription_cancel( (string) $order->get_id(), 'pending-cancel' );

        $this->assertSame( 'Success', $response['message'] ?? null );

        $subscription = wcs_get_subscription( $subscription->get_id() );
        $this->assertSame( 'pending-cancel', $subscription->get_status() );
    }

    public function test_subscription_cancel_updates_multiple_subscriptions_for_same_order() {
        $order = wc_create_order();
        $order->set_status( 'processing' );
        $order->save();

        $sub_a = wcs_create_subscription( [
            'order_id' => $order->get_id(),
            'status'   => 'active',
            'billing_period' => 'month',
            'billing_interval' => 1,
        ] );
        $sub_b = wcs_create_subscription( [
            'order_id' => $order->get_id(),
            'status'   => 'active',
            'billing_period' => 'year',
            'billing_interval' => 1,
        ] );
        $this->assertNotWPError( $sub_a );
        $this->assertNotWPError( $sub_b );

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
        $order = wc_create_order();
        $order->set_status( 'processing' );
        $order->save();

        $this->expectException( \Exception::class );

        $this->dispatch_subscription_cancel( (string) $order->get_id(), 'not-a-real-status' );
    }

    public function test_subscription_cancel_is_idempotent_when_already_cancelled() {
        // Once the subscription is already cancelled, a second call should
        // succeed (200) with an empty subscription_ids list rather than
        // returning a 404. Cancellation is the desired end state; treating
        // "already there" as an error would surface confusing messages to
        // the agent.
        $order = wc_create_order();
        $order->set_status( 'processing' );
        $order->save();

        $subscription = wcs_create_subscription( [
            'order_id' => $order->get_id(),
            'status'   => 'cancelled',
            'billing_period' => 'month',
            'billing_interval' => 1,
        ] );
        $this->assertNotWPError( $subscription );

        $response = $this->dispatch_subscription_cancel( (string) $order->get_id(), 'cancelled' );

        $this->assertSame( 'Success', $response['message'] ?? null );
        $this->assertSame( [], $response['data']['subscription_ids'] ?? [ 'unexpected' ] );
    }

    public function test_plugin_cancel_subscriptions_for_order_returns_structured_result() {
        // Exercises the plugin-class contract directly, not via the Api
        // dispatcher. The plugin returns [ 'found' => bool, 'updated_ids' => int[] ]
        // so the dispatcher can distinguish "no subscriptions" (404) from
        // "already cancelled" (200, empty list).
        $order = wc_create_order();
        $order->save();

        // No subscriptions for this order — result must report not found.
        $result = $this->plugin->cancel_subscriptions_for_order( (string) $order->get_id(), 'cancelled' );
        $this->assertFalse( $result['found'] );
        $this->assertSame( [], $result['updated_ids'] );

        // Add a subscription and try again.
        $sub = wcs_create_subscription( [
            'order_id' => $order->get_id(),
            'status'   => 'active',
            'billing_period' => 'month',
            'billing_interval' => 1,
        ] );
        $this->assertNotWPError( $sub );

        $result = $this->plugin->cancel_subscriptions_for_order( (string) $order->get_id(), 'cancelled' );
        $this->assertTrue( $result['found'] );
        $this->assertSame( [ $sub->get_id() ], $result['updated_ids'] );

        // Idempotent: sub is now cancelled.
        $result = $this->plugin->cancel_subscriptions_for_order( (string) $order->get_id(), 'cancelled' );
        $this->assertTrue( $result['found'] );
        $this->assertSame( [], $result['updated_ids'] );
    }

    public function test_subscription_cancel_returns_404_for_order_without_subscriptions() {
        $order = wc_create_order();
        $order->set_status( 'processing' );
        $order->save();

        $this->expectException( \Exception::class );

        $this->dispatch_subscription_cancel( (string) $order->get_id(), 'cancelled' );
    }

    public function test_subscription_cancel_does_not_cancel_when_passed_a_renewal_order() {
        // Parent order with a subscription; then create a renewal order for
        // that subscription. Invoking the handler with the renewal order's
        // ID must NOT cancel the underlying subscription.
        $parent = wc_create_order();
        $parent->set_status( 'processing' );
        $parent->save();

        $subscription = wcs_create_subscription( [
            'order_id' => $parent->get_id(),
            'status'   => 'active',
            'billing_period' => 'month',
            'billing_interval' => 1,
        ] );
        $this->assertNotWPError( $subscription );

        $renewal = wc_create_order();
        $renewal->set_status( 'pending' );
        $renewal->save();
        // Link the renewal to the subscription the way Subscriptions does.
        $renewal->update_meta_data( '_subscription_renewal', $subscription->get_id() );
        $renewal->save();

        // Call the handler with the RENEWAL order id; we expect a 404
        // because no subscriptions are linked to it as a parent.
        $this->expectException( \Exception::class );
        $this->dispatch_subscription_cancel( (string) $renewal->get_id(), 'cancelled' );

        // Verify the underlying subscription is untouched.
        $this->assertSame( 'active', wcs_get_subscription( $subscription->get_id() )->get_status() );
    }
}