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

    public function test_subscription_cancel_sets_status_to_pending_cancellation() {
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

        $response = $this->dispatch_subscription_cancel( (string) $order->get_id(), 'pending-cancellation' );

        $this->assertSame( 'Success', $response['message'] ?? null );

        $subscription = wcs_get_subscription( $subscription->get_id() );
        $this->assertSame( 'pending-cancellation', $subscription->get_status() );
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

    public function test_subscription_cancel_returns_404_for_order_without_subscriptions() {
        $order = wc_create_order();
        $order->set_status( 'processing' );
        $order->save();

        $this->expectException( \Exception::class );

        $this->dispatch_subscription_cancel( (string) $order->get_id(), 'cancelled' );
    }
}