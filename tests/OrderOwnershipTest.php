<?php
/**
 * The inbound WooCommerce mutators acted on any order_id with no check that the
 * order belongs to the request's customer. The SaaS already sends (and signs)
 * the ticket contact's `email` on every mutator, so the store can verify the
 * order's billing email matches before mutating — the same ownership check the
 * read-only order_status already performs.
 *
 * Every mutator is covered: the guard is a single line per handler, so one
 * shared case would let the other five lose it silently.
 *
 * @package ThriveDesk\Tests
 */

class OrderOwnershipTest extends TD_Ajax_TestCase {

	const TOKEN = 'wc-shared-secret';

	const OWNER    = 'owner@example.com';
	const ATTACKER = 'attacker@example.com';

	const REJECTED = 'Order does not belong to this customer.';

	public function set_up() {
		parent::set_up();
		update_option(
			'thrivedesk_options',
			array( 'woocommerce' => array( 'api_token' => self::TOKEN, 'connected' => true ) )
		);
	}

	private function make_order( string $billing_email ): string {
		$order = wc_create_order();
		$order->set_billing_email( $billing_email );
		$order->save();

		return (string) $order->get_id();
	}

	private function dispatch( array $payload ): array {
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

	private function mutator_payload( string $action, array $extra, string $order_id, string $email ): array {
		return array_merge(
			array(
				'listener' => 'thrivedesk',
				'plugin'   => 'woocommerce',
				'action'   => $action,
				'order_id' => $order_id,
				'email'    => $email,
			),
			$extra
		);
	}

	/**
	 * Every inbound action that writes to an order, with the extra params its
	 * dispatcher branch reads. The values only have to get past parsing: the
	 * ownership guard runs before any of them is used.
	 */
	public static function mutator_actions(): array {
		return array(
			'status update'       => array( 'woocommerce_order_status_update', array( 'order_status' => 'completed' ) ),
			'subscription cancel' => array( 'woocommerce_subscription_cancel', array( 'subscription_status' => 'cancelled' ) ),
			'quantity update'     => array( 'woocommerce_order_quantity_update', array( 'item_id' => '1', 'quantity' => '3' ) ),
			'apply coupon'        => array( 'woocommerce_order_apply_coupon', array( 'coupon' => 'save10' ) ),
			'add item'            => array( 'add_item_on_woocommerce_order', array( 'item' => '1', 'quantity' => '1' ) ),
			'remove item'         => array( 'remove_item_from_woocommerce_order', array( 'item' => '1' ) ),
		);
	}

	/**
	 * @dataProvider mutator_actions
	 */
	public function test_mutator_rejects_order_owned_by_another_customer( string $action, array $extra ) {
		$order_id = $this->make_order( self::OWNER );

		$body = $this->dispatch( $this->mutator_payload( $action, $extra, $order_id, self::ATTACKER ) );

		$this->assertSame( self::REJECTED, $body['message'] ?? null, "$action must reject a non-owner" );
	}

	/**
	 * A mutator with no email at all is a mismatch, not a pass: the guard has to
	 * fail closed or dropping the param would sidestep it entirely.
	 *
	 * @dataProvider mutator_actions
	 */
	public function test_mutator_rejects_a_request_with_no_customer_email( string $action, array $extra ) {
		$order_id = $this->make_order( self::OWNER );

		$payload = $this->mutator_payload( $action, $extra, $order_id, '' );
		unset( $payload['email'] );

		$body = $this->dispatch( $payload );

		$this->assertSame( self::REJECTED, $body['message'] ?? null, "$action must reject a request with no email" );
	}

	public function test_mutator_applies_the_change_for_the_owning_customer() {
		$order_id = $this->make_order( self::OWNER );

		$body = $this->dispatch(
			$this->mutator_payload( 'woocommerce_order_status_update', array( 'order_status' => 'completed' ), $order_id, self::OWNER )
		);

		$this->assertSame( 'Success', $body['message'] ?? null );
		$this->assertSame( 'completed', wc_get_order( $order_id )->get_status(), 'the owning customer must still be able to mutate' );
	}

	/**
	 * The billing email on the order and the ticket contact's email are typed by
	 * two different people; case must not decide whether the action goes through.
	 */
	public function test_ownership_match_is_case_insensitive() {
		$plugin   = \ThriveDesk\Plugins\WooCommerce::instance();
		$order_id = $this->make_order( 'Owner@Example.com' );

		$this->assertTrue( $plugin->order_belongs_to_customer( $order_id, 'owner@example.COM' ) );
	}

	public function test_ownership_is_false_for_a_different_customer() {
		$plugin   = \ThriveDesk\Plugins\WooCommerce::instance();
		$order_id = $this->make_order( self::OWNER );

		$this->assertFalse( $plugin->order_belongs_to_customer( $order_id, self::ATTACKER ) );
	}

	public function test_ownership_is_false_when_no_email_is_supplied() {
		$plugin   = \ThriveDesk\Plugins\WooCommerce::instance();
		$order_id = $this->make_order( self::OWNER );

		$this->assertFalse( $plugin->order_belongs_to_customer( $order_id, '' ) );
	}

	/**
	 * An order with no billing email is not "owned by nobody". An empty request
	 * email must not match it.
	 */
	public function test_ownership_is_false_for_an_order_with_no_billing_email() {
		$plugin   = \ThriveDesk\Plugins\WooCommerce::instance();
		$order_id = $this->make_order( '' );

		$this->assertFalse( $plugin->order_belongs_to_customer( $order_id, '' ) );
	}

	/**
	 * Null, not false: a missing order is a 404 from the mutator, not a
	 * misleading "does not belong to this customer".
	 */
	public function test_ownership_is_null_when_the_order_does_not_exist() {
		$plugin = \ThriveDesk\Plugins\WooCommerce::instance();

		$this->assertNull( $plugin->order_belongs_to_customer( '99999999', self::OWNER ) );
	}

	/**
	 * Fail closed on an integration that can't answer the ownership question at
	 * all, rather than letting the mutator through unchecked.
	 */
	public function test_mutator_is_rejected_when_the_integration_cannot_verify_ownership() {
		update_option(
			'thrivedesk_options',
			array( 'edd' => array( 'api_token' => self::TOKEN, 'connected' => true ) )
		);

		$payload = $this->mutator_payload( 'woocommerce_order_status_update', array( 'order_status' => 'completed' ), '1', self::OWNER );
		$payload['plugin'] = 'edd';

		$body = $this->dispatch( $payload );

		$this->assertSame( self::REJECTED, $body['message'] ?? null );
	}
}
