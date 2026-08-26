<?php
/**
 * woocommerce_order_quantity_update used to do nothing (falling through to a
 * silent wp_die with no response) when quantity <= 0, so the caller could not
 * tell a rejected update from a successful one. It must answer with an error.
 *
 * It also wrote the new quantity straight to the _qty item meta, which left the
 * line totals at their old value, so the order's money never followed the
 * quantity. Those cases are pinned here too.
 *
 * @package ThriveDesk\Tests
 */

class OrderQuantityUpdateTest extends TD_Ajax_TestCase {

	const TOKEN = 'wc-shared-secret';

	const OWNER = 'owner@example.com';

	/** Unit price of the fixture product. */
	const UNIT_PRICE = 12.50;

	public function set_up() {
		parent::set_up();
		update_option(
			'thrivedesk_options',
			array( 'woocommerce' => array( 'api_token' => self::TOKEN, 'connected' => true ) )
		);
	}

	private function api(): \ThriveDesk\Api {
		$api         = \ThriveDesk\Api::instance();
		$plugin_prop = ( new \ReflectionClass( $api ) )->getProperty( 'plugin' );
		$plugin_prop->setAccessible( true );
		$plugin_prop->setValue( $api, \ThriveDesk\Plugins\WooCommerce::instance() );

		return $api;
	}

	/**
	 * An order with a single line item of one unit at self::UNIT_PRICE.
	 *
	 * @return array{0:\WC_Order,1:int} The order and the line's product id.
	 */
	private function make_order_with_one_item(): array {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Widget' );
		$product->set_regular_price( (string) self::UNIT_PRICE );
		$product->save();

		$order = wc_create_order();
		$order->set_billing_email( self::OWNER );
		$order->add_product( $product, 1 );
		$order->calculate_totals();
		$order->save();

		return array( $order, $product->get_id() );
	}

	private function dispatch( string $order_id, string $product_id, string $quantity ): array {
		$payload = array(
			'listener' => 'thrivedesk',
			'plugin'   => 'woocommerce',
			'action'   => 'woocommerce_order_quantity_update',
			'order_id' => $order_id,
			'item_id'  => $product_id,
			'quantity' => $quantity,
			'email'    => self::OWNER,
		);

		$_GET                           = $payload;
		$_POST                          = array();
		$_REQUEST                       = $payload;
		$_SERVER['HTTP_X_TD_SIGNATURE'] = td_test_sign_payload( $payload, self::TOKEN );

		return $this->capture_json(
			function () {
				\ThriveDesk\Api::instance()->api_listener();
			}
		);
	}

	public function test_non_positive_quantity_returns_an_error() {
		$api = $this->api();

		// order 123 does not exist. The quantity is validated before the order is
		// resolved (it is a property of the request, not of the store), so the
		// quantity guard answers rather than the ownership guard's 404.
		$body = $this->capture_json(
			function () use ( $api ) {
				$api->woocommerce_order_quantity_update( '123', '456', '0' );
			}
		);

		$this->assertSame( 'Quantity must be greater than zero.', $body['message'] ?? null );
	}

	public function test_negative_quantity_returns_an_error() {
		$api = $this->api();

		$body = $this->capture_json(
			function () use ( $api ) {
				$api->woocommerce_order_quantity_update( '123', '456', '-10' );
			}
		);

		$this->assertSame( 'Quantity must be greater than zero.', $body['message'] ?? null );
	}

	public function test_absurd_quantity_is_rejected() {
		$api = $this->api();

		$body = $this->capture_json(
			function () use ( $api ) {
				$api->woocommerce_order_quantity_update( '123', '456', '1000' );
			}
		);

		$this->assertSame( 'Quantity must not exceed 999.', $body['message'] ?? null );
	}

	/**
	 * The bug: wc_update_order_item_meta( '_qty' ) bypasses
	 * WC_Order_Item::set_quantity(), so _line_subtotal / _line_total keep their
	 * one-unit value and calculate_totals() re-sums the stale money. The order
	 * shipped 10 units for the price of one.
	 */
	public function test_quantity_update_scales_the_line_and_order_totals() {
		list( $order, $product_id ) = $this->make_order_with_one_item();

		$body = $this->dispatch( (string) $order->get_id(), (string) $product_id, '10' );

		$this->assertSame( 'Success', $body['message'] ?? null );

		$fresh = wc_get_order( $order->get_id() );
		$items = array_values( $fresh->get_items() );

		$this->assertCount( 1, $items );
		$this->assertSame( 10, $items[0]->get_quantity() );
		$this->assertEqualsWithDelta( self::UNIT_PRICE * 10, (float) $items[0]->get_subtotal(), 0.01 );
		$this->assertEqualsWithDelta( self::UNIT_PRICE * 10, (float) $items[0]->get_total(), 0.01 );
		$this->assertEqualsWithDelta( self::UNIT_PRICE * 10, (float) $fresh->get_total(), 0.01 );
	}

	/**
	 * Scaling is off the line's own unit price, so an order placed at a price
	 * the catalogue no longer offers keeps the price the customer paid.
	 */
	public function test_quantity_update_keeps_the_price_the_customer_paid() {
		list( $order, $product_id ) = $this->make_order_with_one_item();

		$product = wc_get_product( $product_id );
		$product->set_regular_price( '99.00' );
		$product->save();

		$this->dispatch( (string) $order->get_id(), (string) $product_id, '3' );

		$fresh = wc_get_order( $order->get_id() );
		$items = array_values( $fresh->get_items() );

		$this->assertEqualsWithDelta( self::UNIT_PRICE * 3, (float) $items[0]->get_total(), 0.01 );
	}
}
