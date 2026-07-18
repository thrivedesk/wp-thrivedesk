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
		$body = $this->capture_json(
			function () {
				\ThriveDesk\Api::instance()->woocommerce_order_quantity_update( '123', '456', '0' );
			}
		);

		$this->assertSame( 'Quantity must be greater than zero.', $body['message'] ?? null );
	}
}
