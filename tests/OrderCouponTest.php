<?php
/**
 * woocommerce_order_apply_coupon wrapped its whole body in `if ( $coupon )`, so
 * an empty coupon took no branch, sent no response, and ended the request at
 * wp_die() with an empty 200 body the panel could not tell from a success.
 *
 * @package ThriveDesk\Tests
 */

class OrderCouponTest extends TD_Ajax_TestCase {

	const TOKEN = 'wc-shared-secret';

	const OWNER = 'owner@example.com';

	const UNIT_PRICE = 50.00;

	public function set_up() {
		parent::set_up();
		update_option(
			'thrivedesk_options',
			array( 'woocommerce' => array( 'api_token' => self::TOKEN, 'connected' => true ) )
		);
	}

	private function make_order(): \WC_Order {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Widget' );
		$product->set_regular_price( (string) self::UNIT_PRICE );
		$product->save();

		$order = wc_create_order();
		$order->set_billing_email( self::OWNER );
		$order->add_product( $product, 1 );
		$order->calculate_totals();
		$order->save();

		return $order;
	}

	private function dispatch( string $order_id, string $coupon ): array {
		$payload = array(
			'listener' => 'thrivedesk',
			'plugin'   => 'woocommerce',
			'action'   => 'woocommerce_order_apply_coupon',
			'order_id' => $order_id,
			'coupon'   => $coupon,
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

	public function test_an_empty_coupon_is_an_error_not_an_empty_success() {
		$order = $this->make_order();

		$body = $this->dispatch( (string) $order->get_id(), '' );

		$this->assertSame( 'coupon is required.', $body['message'] ?? null );
	}

	public function test_an_unknown_coupon_is_reported() {
		$order = $this->make_order();

		$body = $this->dispatch( (string) $order->get_id(), 'nosuchcoupon' );

		$this->assertSame( 'Coupon does not exist!.', $body['message'] ?? null );
	}

	public function test_a_real_coupon_is_applied() {
		$coupon = new \WC_Coupon();
		$coupon->set_code( 'save10' );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		$coupon->save();

		$order = $this->make_order();

		$body = $this->dispatch( (string) $order->get_id(), 'save10' );

		$this->assertSame( 'Success', $body['message'] ?? null );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertEqualsWithDelta( self::UNIT_PRICE * 0.9, (float) $fresh->get_total(), 0.01 );
	}
}
