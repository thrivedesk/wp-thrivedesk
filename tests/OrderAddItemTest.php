<?php
/**
 * add_item_on_woocommerce_order took the quantity straight from
 * sanitize_key( $_GET['quantity'] ), which permits '-', so a signed
 * quantity=-10 produced a negative line total that drove the order total down —
 * a discount anyone holding a signature could mint. It also resolved the
 * product through wc_get_product_object( 'line_item', … ), which has no such
 * product type and so handed back a blank WC_Product_Simple for any id at all,
 * and passed the *unit* price to set_subtotal() while set_total() got the line
 * price, so every added line looked pre-discounted.
 *
 * @package ThriveDesk\Tests
 */

class OrderAddItemTest extends TD_Ajax_TestCase {

	const TOKEN = 'wc-shared-secret';

	const OWNER = 'owner@example.com';

	/** Unit price of the fixture product. */
	const UNIT_PRICE = 20.00;

	public function set_up() {
		parent::set_up();
		update_option(
			'thrivedesk_options',
			array( 'woocommerce' => array( 'api_token' => self::TOKEN, 'connected' => true ) )
		);
	}

	private function make_product(): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Widget' );
		$product->set_regular_price( (string) self::UNIT_PRICE );
		$product->save();

		return $product;
	}

	private function make_empty_order(): \WC_Order {
		$order = wc_create_order();
		$order->set_billing_email( self::OWNER );
		$order->save();

		return $order;
	}

	private function dispatch( string $order_id, string $item, string $quantity ): array {
		$payload = array(
			'listener' => 'thrivedesk',
			'plugin'   => 'woocommerce',
			'action'   => 'add_item_on_woocommerce_order',
			'order_id' => $order_id,
			'item'     => $item,
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

	public function test_negative_quantity_is_rejected_and_the_order_is_untouched() {
		$product = $this->make_product();
		$order   = $this->make_empty_order();

		$body = $this->dispatch( (string) $order->get_id(), (string) $product->get_id(), '-10' );

		$this->assertSame( 'Quantity must be greater than zero.', $body['message'] ?? null );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertCount( 0, $fresh->get_items(), 'a rejected add must not touch the order' );
		$this->assertEqualsWithDelta( 0.0, (float) $fresh->get_total(), 0.01 );
	}

	public function test_absurd_quantity_is_rejected() {
		$product = $this->make_product();
		$order   = $this->make_empty_order();

		$body = $this->dispatch( (string) $order->get_id(), (string) $product->get_id(), '1000' );

		$this->assertSame( 'Quantity must not exceed 999.', $body['message'] ?? null );
	}

	public function test_an_id_that_is_not_a_product_is_rejected() {
		$order   = $this->make_empty_order();
		$post_id = self::factory()->post->create();

		$body = $this->dispatch( (string) $order->get_id(), (string) $post_id, '1' );

		$this->assertSame( 'Product is not available for purchase.', $body['message'] ?? null );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertCount( 0, $fresh->get_items() );
	}

	public function test_added_line_is_priced_for_the_whole_quantity() {
		$product = $this->make_product();
		$order   = $this->make_empty_order();

		$body = $this->dispatch( (string) $order->get_id(), (string) $product->get_id(), '3' );

		$this->assertSame( 'Success', $body['message'] ?? null );

		$fresh = wc_get_order( $order->get_id() );
		$items = array_values( $fresh->get_items() );

		$this->assertCount( 1, $items );
		$this->assertSame( 3, $items[0]->get_quantity() );
		$this->assertSame( $product->get_id(), $items[0]->get_product_id() );
		// subtotal is the line price, not the unit price: no phantom discount.
		$this->assertEqualsWithDelta( self::UNIT_PRICE * 3, (float) $items[0]->get_subtotal(), 0.01 );
		$this->assertEqualsWithDelta( self::UNIT_PRICE * 3, (float) $items[0]->get_total(), 0.01 );
		$this->assertEqualsWithDelta( self::UNIT_PRICE * 3, (float) $fresh->get_total(), 0.01 );
	}
}
