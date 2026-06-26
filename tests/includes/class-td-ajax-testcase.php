<?php
/**
 * Base test case for code paths that terminate via wp_send_json()/wp_die().
 *
 * WordPress's Ajax test case turns wp_die() into a WPAjaxDie*Exception, but
 * ThriveDesk\Api::api_listener() wraps its dispatch in `catch (\Exception)`,
 * which would swallow that exception and double-send the response. To get a
 * single, faithful dispatch we override the die handler to throw an \Error
 * subclass (a Throwable that is NOT an Exception), so api_listener's catch lets
 * it propagate up to capture_json().
 *
 * Note: the HTTP status code passed to wp_send_json() is not observable here.
 * WordPress only calls status_header() when ! headers_sent(), and under the
 * PHPUnit CLI headers are already "sent", so we assert on the JSON body, which
 * already encodes each outcome via its message.
 *
 * @package ThriveDesk\Tests
 */

/**
 * Signal thrown in place of WordPress's WPAjaxDie*Exception. Extends \Error so
 * it bypasses `catch (\Exception)` blocks in the code under test.
 */
class TD_WP_Die_Signal extends \Error {}

class TD_Ajax_TestCase extends WP_Ajax_UnitTestCase {

	public function tear_down() {
		// A signed-request header must not leak into later tests.
		unset( $_SERVER['HTTP_X_TD_SIGNATURE'] );
		$_REQUEST = [];

		parent::tear_down();
	}

	/**
	 * Replacement for WP_Ajax_UnitTestCase::dieHandler(): capture the buffered
	 * output, then throw an \Error so `catch (\Exception)` cannot intercept it.
	 *
	 * @param string|int $message
	 */
	public function dieHandler( $message ) {
		$this->_last_response .= ob_get_clean();
		throw new TD_WP_Die_Signal( is_scalar( $message ) ? (string) $message : '' );
	}

	/**
	 * Run $fn (which is expected to emit JSON then wp_die) and return the
	 * decoded body. Returns [] when the path produced no JSON.
	 */
	protected function capture_json( callable $fn ): array {
		$this->_last_response = '';
		$level                = ob_get_level();

		ob_start();
		try {
			$fn();
		} catch ( TD_WP_Die_Signal $e ) {
			// wp_die() fired; output is already captured into _last_response.
		}

		// Close any buffer that survives when no wp_die() fired (e.g. an early
		// return). On the wp_die path the handler already closed our buffer.
		while ( ob_get_level() > $level ) {
			ob_end_clean();
		}

		$decoded = json_decode( $this->_last_response, true );
		return is_array( $decoded ) ? $decoded : [];
	}
}
