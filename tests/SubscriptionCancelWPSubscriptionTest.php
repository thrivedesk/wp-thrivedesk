<?php
/**
 * Tests for the WPSubscription branch of the subscription cancel handler.
 *
 * Runs against the real "subscription" plugin (SpringDevs/ConversWP), which
 * the bootstrap loads when the environment has it. Subscriptions are
 * subscrpt_order posts linked to WooCommerce orders through the
 * {prefix}subscrpt_order_relation table with type 'new' (initial order) or
 * 'renew' (renewal order).
 *
 * @package ThriveDesk
 */

class TD_Subscription_Cancel_WPSubscription_Test extends WP_UnitTestCase {

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

        if ( ! class_exists( '\SpringDevs\Subscription\Illuminate\Helper' ) ) {
            $this->markTestSkipped( 'WPSubscription is not active in this test environment.' );
        }

        $this->create_relation_table();

        // The WCS stubs are also loaded; keep their registry empty so only
        // the WPSubscription branch produces results in these tests.
        if ( class_exists( 'TD_Test_WCS_Registry' ) ) {
            TD_Test_WCS_Registry::reset();
        }

        $this->plugin = \ThriveDesk\Plugins\WooCommerce::instance();
    }

    /**
     * The plugin creates this table on activation, which the test bootstrap
     * does not run; mirror the live schema.
     */
    private function create_relation_table(): void {
        global $wpdb;

        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}subscrpt_order_relation (
                id INT NOT NULL AUTO_INCREMENT,
                subscription_id INT NOT NULL,
                order_id INT NOT NULL,
                order_item_id INT NOT NULL,
                type VARCHAR(50) NOT NULL,
                PRIMARY KEY (id)
            )"
        );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}subscrpt_order_relation" );
    }

    private function create_order( string $status = 'processing' ): \WC_Order {
        $order = wc_create_order();
        $order->set_status( $status );
        $order->save();

        return $order;
    }

    /**
     * Mirrors what the plugin creates at checkout: a real order line item,
     * the relation row pointing at it, and the item/subscription meta its
     * templates read. The email templates dereference the order item, so a
     * fake order_item_id would fatal on any status-change email.
     *
     * @return int subscription (subscrpt_order post) id
     */
    private function create_subscription_for_order( \WC_Order $order, string $relation_type = 'new', string $status = 'active' ): int {
        global $wpdb;

        $product = new \WC_Product_Simple();
        $product->set_name( 'Monthly Plan' );
        $product->set_regular_price( '29.00' );
        $product->save();

        $item_id = $order->add_product( $product, 1 );
        $order->calculate_totals();
        $order->save();
        wc_update_order_item_meta( $item_id, '_subscrpt_meta', [ 'time' => 1, 'type' => 'month', 'trial' => null ] );

        $subscription_id = wp_insert_post( [
            'post_type'   => 'subscrpt_order',
            'post_status' => $status,
            'post_title'  => 'Subscription for order ' . $order->get_id(),
        ] );

        update_post_meta( $subscription_id, '_subscrpt_order_id', $order->get_id() );
        update_post_meta( $subscription_id, '_subscrpt_price', $product->get_price() );
        update_post_meta( $subscription_id, '_subscrpt_start_date', time() );
        update_post_meta( $subscription_id, '_subscrpt_next_date', strtotime( '+1 month' ) );

        $wpdb->insert(
            $wpdb->prefix . 'subscrpt_order_relation',
            [
                'subscription_id' => $subscription_id,
                'order_id'        => $order->get_id(),
                'order_item_id'   => $item_id,
                'type'            => $relation_type,
            ],
            [ '%d', '%d', '%d', '%s' ]
        );

        return $subscription_id;
    }

    public function test_cancel_updates_active_wpsubscription_on_parent_order() {
        $order           = $this->create_order();
        $subscription_id = $this->create_subscription_for_order( $order, 'new' );

        $result = $this->plugin->cancel_subscriptions_for_order( (string) $order->get_id(), 'cancelled' );

        $this->assertTrue( $result['found'] );
        $this->assertSame( [ $subscription_id ], $result['updated_ids'] );
        $this->assertSame( 'cancelled', get_post_status( $subscription_id ) );
    }

    public function test_pending_cancel_maps_to_pe_cancelled_status() {
        $order           = $this->create_order();
        $subscription_id = $this->create_subscription_for_order( $order, 'new' );

        $result = $this->plugin->cancel_subscriptions_for_order( (string) $order->get_id(), 'pending-cancel' );

        $this->assertSame( [ $subscription_id ], $result['updated_ids'] );
        $this->assertSame( 'pe_cancelled', get_post_status( $subscription_id ) );
    }

    public function test_parent_only_lookup_ignores_renewal_relation() {
        $renewal_order   = $this->create_order();
        $subscription_id = $this->create_subscription_for_order( $renewal_order, 'renew' );

        $result = $this->plugin->cancel_subscriptions_for_order( (string) $renewal_order->get_id(), 'cancelled', [ 'parent' ] );

        $this->assertFalse( $result['found'] );
        $this->assertSame( [], $result['updated_ids'] );
        $this->assertSame( 'active', get_post_status( $subscription_id ) );
    }

    public function test_renewal_order_type_cancels_from_a_renewal_order() {
        $renewal_order   = $this->create_order();
        $subscription_id = $this->create_subscription_for_order( $renewal_order, 'renew' );

        $result = $this->plugin->cancel_subscriptions_for_order( (string) $renewal_order->get_id(), 'cancelled', [ 'parent', 'renewal' ] );

        $this->assertTrue( $result['found'] );
        $this->assertSame( [ $subscription_id ], $result['updated_ids'] );
        $this->assertSame( 'cancelled', get_post_status( $subscription_id ) );
    }

    public function test_already_cancelled_subscription_reports_found_with_no_updates() {
        $order           = $this->create_order();
        $subscription_id = $this->create_subscription_for_order( $order, 'new', 'cancelled' );

        $result = $this->plugin->cancel_subscriptions_for_order( (string) $order->get_id(), 'cancelled' );

        $this->assertTrue( $result['found'] );
        $this->assertSame( [], $result['updated_ids'] );
        $this->assertSame( 'cancelled', get_post_status( $subscription_id ) );
    }

    public function test_expired_subscription_is_left_alone() {
        $order           = $this->create_order();
        $subscription_id = $this->create_subscription_for_order( $order, 'new', 'expired' );

        $result = $this->plugin->cancel_subscriptions_for_order( (string) $order->get_id(), 'cancelled' );

        $this->assertTrue( $result['found'] );
        $this->assertSame( [], $result['updated_ids'] );
        $this->assertSame( 'expired', get_post_status( $subscription_id ) );
    }

    public function test_plugins_own_cascade_cancels_subscription_on_order_cancel() {
        // The plugin listens to woocommerce_order_status_changed itself:
        // flipping the order (what the panel's status update does) must
        // cancel the related subscription without our follow-up.
        $order           = $this->create_order();
        $subscription_id = $this->create_subscription_for_order( $order, 'new' );

        $order->update_status( 'cancelled', '' );

        $this->assertSame( 'cancelled', get_post_status( $subscription_id ) );

        // The follow-up then arrives and must be an idempotent no-op.
        $result = $this->plugin->cancel_subscriptions_for_order( (string) $order->get_id(), 'cancelled' );
        $this->assertTrue( $result['found'] );
        $this->assertSame( [], $result['updated_ids'] );
    }

    public function test_results_merge_when_both_providers_have_subscriptions() {
        // A store can run WCS and WPSubscription side by side; both branches
        // must contribute to one merged result. WCS is stubbed, so its
        // subscription comes from the stub registry.
        $order              = $this->create_order();
        $wps_subscription   = $this->create_subscription_for_order( $order, 'new' );
        $wcs_subscription   = wcs_create_subscription( [ 'order_id' => $order->get_id(), 'status' => 'active' ] );

        $result = $this->plugin->cancel_subscriptions_for_order( (string) $order->get_id(), 'cancelled' );

        $this->assertTrue( $result['found'] );
        $this->assertCount( 2, $result['updated_ids'] );
        $this->assertContains( $wps_subscription, $result['updated_ids'] );
        $this->assertContains( $wcs_subscription->get_id(), $result['updated_ids'] );
        $this->assertSame( 'cancelled', get_post_status( $wps_subscription ) );
        $this->assertSame( 'cancelled', wcs_get_subscription( $wcs_subscription->get_id() )->get_status() );
    }
}
