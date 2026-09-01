<?php

namespace ThriveDesk\Services\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PKCE (RFC 7636) verifier and challenge.
 *
 * S256 only. ThriveDesk refuses `plain` for public clients, and it would be no protection
 * here anyway: the challenge travels through the user's browser, so a plain challenge is
 * the verifier in the clear.
 */
final class Pkce {

	/**
	 * A fresh code verifier, base64url with no padding.
	 *
	 * 32 random bytes lands at 43 characters, the minimum RFC 7636 allows.
	 */
	public static function verifier(): string {
		return self::base64_url( random_bytes( 32 ) );
	}

	public static function challenge( string $verifier ): string {
		return self::base64_url( hash( 'sha256', $verifier, true ) );
	}

	/**
	 * Opaque value binding the callback to the request that started it.
	 */
	public static function state(): string {
		return self::base64_url( random_bytes( 16 ) );
	}

	private static function base64_url( string $bytes ): string {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
	}
}
