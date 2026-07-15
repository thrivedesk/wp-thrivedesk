<?php
/**
 * A minimal concrete implementation of the abstract ThriveDesk\Plugin. It lets
 * the base class's shared logic be characterized without loading a real
 * e-commerce integration; customer/order data is injected via public props.
 *
 * @package ThriveDesk\Tests
 */

namespace ThriveDesk\Tests\Support;

use ThriveDesk\Plugin;

class FakePlugin extends Plugin {

	/** @var array Customer payload returned by get_customer(). */
	public $fixture_customer = [];

	/** @var array Orders returned by get_orders(). */
	public $fixture_orders = [];

	/** @var array Data returned by get_plugin_data(). */
	public $fixture_plugin_data = [];

	/** @var bool Records the last connect()/disconnect() call. */
	public $connected = false;

	public static function is_plugin_active(): bool {
		return true;
	}

	public function is_customer_exist(): bool {
		return ! empty( $this->fixture_customer );
	}

	public function accepted_statuses(): array {
		return [ 'Completed' ];
	}

	public function get_customer(): array {
		return $this->fixture_customer;
	}

	public function get_orders(): array {
		return $this->fixture_orders;
	}

	public function get_plugin_data( string $key = '' ) {
		if ( '' === $key ) {
			return $this->fixture_plugin_data;
		}
		return $this->fixture_plugin_data[ $key ] ?? '';
	}

	public function connect() {
		$this->connected = true;
	}

	public function disconnect() {
		$this->connected = false;
	}
}
