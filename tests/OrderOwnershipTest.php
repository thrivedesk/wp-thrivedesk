<?php
/**
 * The inbound WooCommerce mutators acted on any order_id with no check that the
 * order belongs to the request's customer. The SaaS already sends (and signs)
 * the ticket contact's `email` on every mutator, so the store can verify the
 * order's billing email matches before mutating — the same ownership check the
 * read-only order_status already performs.
 *
 * @package ThriveDesk\Tests
 */

class OrderOwnershipTest extends TD_Ajax_TestCase {

	const TOKEN = 'wc-shared-secret';

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

	private function status_update_payload( string $order_id, string $email ): array {
		return array(
			'listener'     => 'thrivedesk',
			'plugin'       => 'woocommerce',
			'action'       => 'woocommerce_order_status_update',
			'order_id'     => $order_id,
			'order_status' => 'completed',
			'email'        => $email,
		);
	}

	public function test_mutator_rejects_order_owned_by_another_customer() {
		$order_id = $this->make_order( 'owner@example.com' );

		$body = $this->dispatch( $this->status_update_payload( $order_id, 'attacker@example.com' ) );

		$this->assertSame( 'Order does not belong to this customer.', $body['message'] );
	}

	public function test_mutator_allows_the_owning_customer() {
		$order_id = $this->make_order( 'owner@example.com' );

		$body = $this->dispatch( $this->status_update_payload( $order_id, 'owner@example.com' ) );

		$this->assertNotSame( 'Order does not belong to this customer.', $body['message'] ?? '' );
	}
}
