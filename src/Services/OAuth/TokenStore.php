<?php

namespace ThriveDesk\Services\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The site's OAuth credential.
 *
 * Access tokens last an hour, so the refresh token is the thing that actually keeps the site
 * connected and it is the value worth protecting. Stored in its own option rather than beside
 * the pasted API key so the two connection methods can never be half-migrated into each other.
 */
final class TokenStore {

	public const OPTION = 'td_oauth_connection';

	/**
	 * Refresh this many seconds before the token actually expires, so a request that takes a
	 * while to reach ThriveDesk does not arrive with a token that died in flight.
	 */
	private const EXPIRY_SKEW = 60;

	/**
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, [] );

		return is_array( $stored ) ? $stored : [];
	}

	public static function client_id(): string {
		$stored = self::get();

		return isset( $stored['client_id'] ) ? (string) $stored['client_id'] : '';
	}

	public static function access_token(): string {
		$stored = self::get();

		return isset( $stored['access_token'] ) ? (string) $stored['access_token'] : '';
	}

	public static function refresh_token(): string {
		$stored = self::get();

		return isset( $stored['refresh_token'] ) ? (string) $stored['refresh_token'] : '';
	}

	public static function is_connected(): bool {
		return '' !== self::access_token();
	}

	public static function needs_refresh(): bool {
		$stored = self::get();

		if ( empty( $stored['expires_at'] ) ) {
			return false;
		}

		return time() >= ( (int) $stored['expires_at'] - self::EXPIRY_SKEW );
	}

	/**
	 * Record the client id before consent, so a retry after an abandoned or failed consent
	 * reuses the registration instead of creating another one.
	 */
	public static function remember_client( string $client_id, string $scope = '' ): void {
		$existing              = self::get();
		$existing['client_id'] = $client_id;

		if ( '' !== $scope ) {
			$existing['registered_scope'] = $scope;
		}

		update_option( self::OPTION, $existing, false );
	}

	/**
	 * The scopes ThriveDesk registered this site for, as returned by registration.
	 */
	public static function registered_scope(): string {
		$stored = self::get();

		return isset( $stored['registered_scope'] ) ? (string) $stored['registered_scope'] : '';
	}

	/**
	 * Persist a token response. The client id and refresh token are carried forward when the
	 * response omits them: a refresh exchange does not always return a new refresh token, and
	 * dropping the old one would silently end the connection at the next expiry.
	 *
	 * @param array<string, mixed> $token Decoded /oauth/token response.
	 */
	public static function save( array $token, string $client_id = '' ): void {
		$existing = self::get();

		$refresh = isset( $token['refresh_token'] ) && '' !== $token['refresh_token']
			? (string) $token['refresh_token']
			: ( isset( $existing['refresh_token'] ) ? (string) $existing['refresh_token'] : '' );

		update_option(
			self::OPTION,
			[
				'client_id'     => '' !== $client_id ? $client_id : ( isset( $existing['client_id'] ) ? (string) $existing['client_id'] : '' ),
				'access_token'  => isset( $token['access_token'] ) ? (string) $token['access_token'] : '',
				'refresh_token' => $refresh,
				'scope'         => isset( $token['scope'] ) ? (string) $token['scope'] : ( isset( $existing['scope'] ) ? (string) $existing['scope'] : '' ),
				'expires_at'    => isset( $token['expires_in'] ) ? time() + (int) $token['expires_in'] : 0,
				'connected_at'  => isset( $existing['connected_at'] ) ? (int) $existing['connected_at'] : time(),
			],
			false
		);
	}

	/**
	 * Forget the connection. The client registration on ThriveDesk's side is left alone: it
	 * holds no access on its own, and keeping it means a reconnect reuses the same row.
	 */
	public static function clear(): void {
		$existing = self::get();

		if ( empty( $existing['client_id'] ) ) {
			delete_option( self::OPTION );

			return;
		}

		update_option( self::OPTION, [ 'client_id' => (string) $existing['client_id'] ], false );
	}
}
