<?php
/**
 * woocommerce_order_quantity_update used to do nothing (falling through to a
 * silent wp_die with no response) when quantity <= 0, so the caller could not
 * tell a rejected update from a successful one. It must answer with an error.
 *
 * @package ThriveDesk\Tests
 */

class OrderQuantityUpdateTest extends TD_Ajax_TestCase {

	public function test_non_positive_quantity_returns_an_error() {
		$api         = \ThriveDesk\Api::instance();
		$plugin_prop = ( new \ReflectionClass( $api ) )->getProperty( 'plugin' );
		$plugin_prop->setAccessible( true );
		$plugin_prop->setValue( $api, \ThriveDesk\Plugins\WooCommerce::instance() );

		// order 123 does not exist, so ownership resolves to not-found (not a
		// mismatch) and the quantity guard is what answers.
		$body = $this->capture_json(
			function () use ( $api ) {
				$api->woocommerce_order_quantity_update( '123', '456', '0' );
			}
		);

		$this->assertSame( 'Quantity must be greater than zero.', $body['message'] ?? null );
	}
}
