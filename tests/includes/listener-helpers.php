<?php
/**
 * Helpers for the ?listener=thrivedesk dispatcher tests.
 *
 * @package ThriveDesk\Tests
 */

// EDD::is_plugin_active() only checks function_exists('EDD'); defining this stub
// lets the listener tests treat the EDD integration as active without installing
// Easy Digital Downloads. It is a no-op everywhere else.
if ( ! function_exists( 'EDD' ) ) {
	function EDD() {
		return null;
	}
}

if ( ! function_exists( 'td_test_sign_payload' ) ) {
	/**
	 * Reproduce ThriveDesk\Api::verify_token()'s signing exactly: coerce the
	 * "true"/"false" strings to booleans, then HMAC-SHA1 the wp_json_encode()d
	 * result. Values are hashed raw — the SaaS signs what it sends, and the
	 * plugin sanitizes only at the point of use, after verification. That method
	 * is the source of truth; keep this helper in step with it.
	 *
	 * @param array  $payload The request payload ($_REQUEST equivalent).
	 * @param string $token   The integration api_token used as the HMAC key.
	 * @return string Lowercase hex SHA1 HMAC.
	 */
	function td_test_sign_payload( array $payload, string $token ): string {
		foreach ( $payload as $key => $value ) {
			if ( is_string( $value ) ) {
				$lower = strtolower( $value );
				if ( 'true' === $lower ) {
					$payload[ $key ] = true;
				} elseif ( 'false' === $lower ) {
					$payload[ $key ] = false;
				}
			}
		}

		return hash_hmac( 'SHA1', wp_json_encode( $payload ), $token );
	}
}
