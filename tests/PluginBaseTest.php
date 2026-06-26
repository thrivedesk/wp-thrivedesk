<?php
/**
 * Characterization tests for the shared logic in the abstract ThriveDesk\Plugin
 * base class. They pin today's behavior (including known quirks such as the
 * string-coercing get_formated_amount) so later refactor phases surface any
 * accidental change.
 *
 * @package ThriveDesk\Tests
 */

use ThriveDesk\Tests\Support\FakePlugin;

class PluginBaseTest extends WP_UnitTestCase {

	private function make_plugin(): FakePlugin {
		$plugin                   = new FakePlugin();
		$plugin->fixture_customer = [
			'name'          => 'Ada Lovelace',
			'email'         => 'ada@example.com',
			'registered_at' => '10 Dec 1815',
		];
		$plugin->fixture_orders = [
			[ 'order_id' => 'A', 'amount' => 100.0, 'order_status' => 'Completed', 'date' => '2000-01-01' ],
			[ 'order_id' => 'B', 'amount' => 50.0, 'order_status' => 'Completed', 'date' => gmdate( 'Y-m-d' ) ],
			[ 'order_id' => 'C', 'amount' => 25.0, 'order_status' => 'Refunded', 'date' => gmdate( 'Y-m-d' ) ],
		];
		return $plugin;
	}

	public function test_filter_accepted_orders_keeps_only_accepted_statuses() {
		$plugin   = $this->make_plugin();
		$accepted = $plugin->filter_accepted_orders( $plugin->get_orders() );

		$this->assertCount( 2, $accepted );
		$this->assertSame( [ 'A', 'B' ], array_values( array_column( $accepted, 'order_id' ) ) );
	}

	public function test_get_lifetime_order_sums_amounts() {
		$plugin   = $this->make_plugin();
		$accepted = $plugin->filter_accepted_orders( $plugin->get_orders() );

		$this->assertSame( 150.0, $plugin->get_lifetime_order( $accepted ) );
		// Declared `: float`, so an empty order set yields 0.0, not int 0.
		$this->assertSame( 0.0, $plugin->get_lifetime_order( [] ) );
	}

	public function test_get_this_year_order_only_counts_last_12_months() {
		$plugin   = $this->make_plugin();
		$accepted = $plugin->filter_accepted_orders( $plugin->get_orders() );

		// Order A is dated 2000; only order B (today) falls within a year.
		$this->assertSame( 50.0, $plugin->get_this_year_order( $accepted ) );
	}

	public function test_get_formated_amount_base_returns_amount_coerced_to_string() {
		$plugin = $this->make_plugin();

		// The base method is declared `: string` but returns the float verbatim;
		// in non-strict mode PHP coerces it, dropping a trailing ".0".
		$this->assertSame( '12.5', $plugin->get_formated_amount( 12.5 ) );
		$this->assertSame( '12', $plugin->get_formated_amount( 12.0 ) );
		$this->assertSame( '0', $plugin->get_formated_amount( 0.0 ) );
	}

	public function test_prepare_data_shape_and_totals() {
		$plugin = $this->make_plugin();
		$data   = $plugin->prepare_data();

		$this->assertSame(
			[ 'customer', 'customer_since', 'lifetime_order', 'this_year_order', 'avg_order', 'orders', 'add_order' ],
			array_keys( $data )
		);

		$this->assertSame( $plugin->fixture_customer, $data['customer'] );
		$this->assertSame( '10 Dec 1815', $data['customer_since'] );

		// Totals run through the base get_formated_amount(), hence string output.
		$this->assertSame( '150', $data['lifetime_order'] );
		$this->assertSame( '50', $data['this_year_order'] );
		$this->assertSame( '75', $data['avg_order'] ); // number_format("75.00") re-coerced.

		// All orders are returned, not just the accepted ones.
		$this->assertSame( $plugin->fixture_orders, $data['orders'] );
		$this->assertStringContainsString( 'post-new.php?post_type=shop_order', $data['add_order'] );
	}

	public function test_prepare_data_handles_no_accepted_orders() {
		$plugin                   = new FakePlugin();
		$plugin->fixture_customer = [ 'registered_at' => '01 Jan 2020' ];
		$plugin->fixture_orders   = [
			[ 'order_id' => 'X', 'amount' => 10.0, 'order_status' => 'Refunded', 'date' => gmdate( 'Y-m-d' ) ],
		];

		$data = $plugin->prepare_data();

		$this->assertSame( '0', $data['lifetime_order'] );
		$this->assertSame( '0', $data['this_year_order'] );
		$this->assertSame( '0', $data['avg_order'] );
		$this->assertSame( $plugin->fixture_orders, $data['orders'] );
	}
}
