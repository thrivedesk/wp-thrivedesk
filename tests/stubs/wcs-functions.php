<?php
/**
 * Minimal WooCommerce Subscriptions test doubles.
 *
 * The real extension is commercial and absent from this test environment,
 * so these stubs implement just the surface the subscription-cancel handler
 * and its tests touch: wcs_create_subscription(), wcs_get_subscription(),
 * wcs_get_subscriptions_for_order(), and a subscription object with
 * get_id() / get_status() / can_be_updated_to() / update_status().
 *
 * Linkage mirrors the real extension where the handler depends on it: a
 * parent order is the subscription's order_id, and a renewal order carries
 * the subscription id in its '_subscription_renewal' meta.
 *
 * Loaded from tests/bootstrap.php. When the real extension is present the
 * function_exists guard keeps these dormant and the same tests run against
 * the real thing.
 *
 * @package ThriveDesk
 */

if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {

    class TD_Test_WC_Subscription {

        /** @var int */
        private $id;

        /** @var int */
        private $parent_order_id;

        /** @var string */
        private $status;

        public function __construct( int $id, int $parent_order_id, string $status ) {
            $this->id              = $id;
            $this->parent_order_id = $parent_order_id;
            $this->status          = $status;
        }

        public function get_id(): int {
            return $this->id;
        }

        public function get_parent_id(): int {
            return $this->parent_order_id;
        }

        public function get_status(): string {
            return $this->status;
        }

        public function can_be_updated_to( $new_status ): bool {
            // The rule the handler relies on: ended subscriptions stay ended.
            return ! in_array( $this->status, array( 'cancelled', 'expired' ), true );
        }

        public function update_status( $new_status ): void {
            if ( ! $this->can_be_updated_to( $new_status ) ) {
                throw new Exception( "Unable to change subscription status to \"{$new_status}\"." );
            }
            $this->status = $new_status;
        }
    }

    class TD_Test_WCS_Registry {

        /** @var TD_Test_WC_Subscription[] keyed by subscription id */
        public static $subscriptions = array();

        /** @var int */
        public static $next_id = 90001;

        public static function reset(): void {
            self::$subscriptions = array();
            self::$next_id       = 90001;
        }
    }

    function wcs_create_subscription( $args = array() ) {
        $id  = TD_Test_WCS_Registry::$next_id++;
        $sub = new TD_Test_WC_Subscription( $id, (int) ( $args['order_id'] ?? 0 ), $args['status'] ?? 'pending' );

        TD_Test_WCS_Registry::$subscriptions[ $id ] = $sub;

        return $sub;
    }

    function wcs_get_subscription( $id ) {
        return TD_Test_WCS_Registry::$subscriptions[ (int) $id ] ?? false;
    }

    function wcs_get_subscriptions_for_order( $order, $args = array() ) {
        $order = is_object( $order ) ? $order : wc_get_order( $order );
        if ( ! $order ) {
            return array();
        }

        $order_types = (array) ( $args['order_type'] ?? array( 'parent' ) );
        $found       = array();

        foreach ( TD_Test_WCS_Registry::$subscriptions as $id => $subscription ) {
            if ( in_array( 'parent', $order_types, true ) && $subscription->get_parent_id() === $order->get_id() ) {
                $found[ $id ] = $subscription;
                continue;
            }
            if ( in_array( 'renewal', $order_types, true ) && (int) $order->get_meta( '_subscription_renewal' ) === $id ) {
                $found[ $id ] = $subscription;
            }
        }

        return $found;
    }
}
