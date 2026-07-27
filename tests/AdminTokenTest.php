<?php
/**
 * The per-store connect token is the HMAC key for the ?listener= signing
 * contract, so it must be high-entropy. The old md5(time()) was 32 hex chars
 * derived from a seconds-granularity timestamp, brute-forceable when the
 * connect time is roughly known.
 *
 * @package ThriveDesk\Tests
 */

class AdminTokenTest extends WP_UnitTestCase {

	public function test_generated_api_token_is_high_entropy() {
		$method = new ReflectionMethod( \ThriveDesk\Admin::class, 'generate_api_token' );
		$method->setAccessible( true );

		$first  = $method->invoke( null );
		$second = $method->invoke( null );

		// 32 random bytes, hex-encoded: 64 chars, and not time-derived so two
		// back-to-back calls differ (md5(time()) would collide within a second).
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $first );
		$this->assertNotSame( $first, $second );
	}
}
