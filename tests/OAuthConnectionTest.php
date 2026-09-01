<?php
/**
 * The OAuth connect flow: PKCE, the token store, and which credential TDApiService picks.
 *
 * Deliberately on WP_UnitTestCase rather than the ajax case: WP_Ajax_UnitTestCase turns
 * E_WARNING off, which hides exactly the undefined-index mistakes this code could make.
 *
 * @package ThriveDesk\Tests
 */

use ThriveDesk\Services\OAuth\Connection;
use ThriveDesk\Services\OAuth\OAuthClient;
use ThriveDesk\Services\OAuth\Pkce;
use ThriveDesk\Services\OAuth\TokenStore;
use ThriveDesk\Services\TDApiService;

class OAuthConnectionTest extends WP_UnitTestCase {

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		delete_option( TokenStore::OPTION );
		delete_option( 'td_helpdesk_settings' );

		parent::tear_down();
	}

	public function test_pkce_challenge_is_unpadded_base64url_sha256_of_the_verifier(): void {
		$verifier = Pkce::verifier();

		// RFC 7636: 43 characters is the floor, and the alphabet excludes + / =
		$this->assertGreaterThanOrEqual( 43, strlen( $verifier ) );
		$this->assertSame( 1, preg_match( '#^[A-Za-z0-9\-_]+$#', $verifier ) );

		$expected = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );

		$this->assertSame( $expected, Pkce::challenge( $verifier ) );
		$this->assertNotSame( $verifier, Pkce::challenge( $verifier ), 'S256 must not be plain' );
	}

	public function test_verifiers_are_not_reused(): void {
		$this->assertNotSame( Pkce::verifier(), Pkce::verifier() );
		$this->assertNotSame( Pkce::state(), Pkce::state() );
	}

	public function test_the_authorize_url_sends_the_exact_registered_callback_and_s256(): void {
		$url = ( new OAuthClient() )->authorize_url( 'client-123', 'st', 'ch', [ 'profile:read', 'inboxes:read' ] );

		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		// Passport compares redirect_uri byte for byte against the registration, so these two
		// must come from the same place or consent fails with an unexplained invalid_request.
		$this->assertSame( OAuthClient::redirect_uri(), $query['redirect_uri'] );
		$this->assertSame( 'S256', $query['code_challenge_method'] );
		$this->assertSame( 'code', $query['response_type'] );
		$this->assertSame( 'profile:read inboxes:read', $query['scope'] );
		$this->assertStringStartsWith( THRIVEDESK_API_URL . '/oauth/authorize?', $url );
	}

	public function test_the_callback_url_is_inside_wp_admin_and_carries_the_marker(): void {
		$this->assertStringStartsWith( admin_url(), OAuthClient::redirect_uri() );
		$this->assertStringContainsString( 'td_oauth=callback', OAuthClient::redirect_uri() );
	}

	public function test_a_refresh_response_without_a_refresh_token_keeps_the_existing_one(): void {
		TokenStore::save(
			[
				'access_token'  => 'first',
				'refresh_token' => 'the-only-way-back',
				'expires_in'    => 3600,
			],
			'client-123'
		);

		// Passport does not always reissue the refresh token; dropping it here would end the
		// connection at the next expiry with nothing to renew from.
		TokenStore::save( [ 'access_token' => 'second', 'expires_in' => 3600 ] );

		$this->assertSame( 'second', TokenStore::access_token() );
		$this->assertSame( 'the-only-way-back', TokenStore::refresh_token() );
		$this->assertSame( 'client-123', TokenStore::client_id() );
	}

	public function test_needs_refresh_fires_before_the_token_actually_expires(): void {
		TokenStore::save( [ 'access_token' => 'a', 'expires_in' => 3600 ], 'c' );
		$this->assertFalse( TokenStore::needs_refresh() );

		// inside the skew: a request that takes a moment to arrive must not land expired
		TokenStore::save( [ 'access_token' => 'a', 'expires_in' => 30 ], 'c' );
		$this->assertTrue( TokenStore::needs_refresh() );
	}

	public function test_disconnect_drops_the_tokens_but_keeps_the_registration(): void {
		TokenStore::save( [ 'access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3600 ], 'client-123' );

		TokenStore::clear();

		$this->assertFalse( TokenStore::is_connected() );
		$this->assertSame( '', TokenStore::refresh_token() );
		$this->assertSame( 'client-123', TokenStore::client_id(), 'reconnecting should reuse the client row' );
	}

	public function test_the_oauth_token_is_preferred_over_a_pasted_key(): void {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'PASTED-KEY' ] );
		TokenStore::save( [ 'access_token' => 'OAUTH-TOKEN', 'refresh_token' => 'r', 'expires_in' => 3600 ], 'c' );

		$this->assertSame( 'Bearer OAUTH-TOKEN', $this->authorizationHeaderOf( THRIVEDESK_API_URL . '/v1/me' ) );
	}

	public function test_a_site_without_an_oauth_connection_still_uses_its_pasted_key(): void {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'PASTED-KEY' ] );

		$this->assertSame( 'Bearer PASTED-KEY', $this->authorizationHeaderOf( THRIVEDESK_API_URL . '/v1/me' ) );
	}

	public function test_an_expired_token_is_refreshed_and_the_request_retried(): void {
		TokenStore::save( [ 'access_token' => 'STALE', 'refresh_token' => 'r', 'expires_in' => 3600 ], 'client-123' );

		$seen = [];

		add_filter(
			'pre_http_request',
			static function ( $pre, $args, $url ) use ( &$seen ) {
				if ( false !== strpos( $url, '/oauth/token' ) ) {
					return [
						'response' => [ 'code' => 200 ],
						'body'     => wp_json_encode(
							[ 'access_token' => 'FRESH', 'refresh_token' => 'r2', 'expires_in' => 3600 ]
						),
					];
				}

				$seen[] = $args['headers']['Authorization'];

				// first call rejects the stale token, second accepts the refreshed one
				return 1 === count( $seen )
					? [ 'response' => [ 'code' => 401 ], 'body' => wp_json_encode( [ 'message' => 'Unauthenticated.' ] ) ]
					: [ 'response' => [ 'code' => 200 ], 'body' => wp_json_encode( [ 'ok' => true ] ) ];
			},
			10,
			3
		);

		$response = ( new TDApiService() )->getRequest( THRIVEDESK_API_URL . '/v1/me' );

		$this->assertSame( [ 'Bearer STALE', 'Bearer FRESH' ], $seen );
		$this->assertSame( [ 'ok' => true ], $response );
		$this->assertSame( 'FRESH', TokenStore::access_token() );
	}

	public function test_a_network_failure_is_not_treated_as_an_expired_token(): void {
		TokenStore::save( [ 'access_token' => 'GOOD', 'refresh_token' => 'r', 'expires_in' => 3600 ], 'client-123' );

		$calls = 0;

		add_filter(
			'pre_http_request',
			static function () use ( &$calls ) {
				++$calls;

				return new WP_Error( 'http_request_failed', 'cURL error 28' );
			}
		);

		$response = ( new TDApiService() )->getRequest( THRIVEDESK_API_URL . '/v1/me' );

		// one attempt, no refresh: a timeout says nothing about whether the token is valid
		$this->assertSame( 1, $calls );
		$this->assertSame( 'network', $response['error_type'] );
		$this->assertSame( 'GOOD', TokenStore::access_token() );
	}

	public function test_an_explicitly_set_key_is_never_swapped_for_the_oauth_token(): void {
		TokenStore::save( [ 'access_token' => 'OAUTH-TOKEN', 'refresh_token' => 'r', 'expires_in' => 3600 ], 'c' );

		$seen = [];

		add_filter(
			'pre_http_request',
			static function ( $pre, $args ) use ( &$seen ) {
				$seen[] = $args['headers']['Authorization'];

				return [ 'response' => [ 'code' => 401 ], 'body' => wp_json_encode( [ 'message' => 'Unauthenticated.' ] ) ];
			},
			10,
			2
		);

		$service = new TDApiService();
		$service->setApiKey( 'KEY-BEING-VERIFIED' );
		$service->getRequest( THRIVEDESK_API_URL . '/v1/me' );

		// verifying a pasted key must report on that key, not silently retry as the connection
		$this->assertSame( [ 'Bearer KEY-BEING-VERIFIED' ], $seen );
	}

	public function test_registration_scopes_come_from_the_server_not_a_local_copy(): void {
		// the server owns the scope list; a hardcoded copy here would make any server-side
		// change land as an invalid_scope failure on the Connect button
		add_filter(
			'pre_http_request',
			static fn() => [
				'response' => [ 'code' => 201 ],
				'body'     => wp_json_encode(
					[
						'client_id' => 'client-abc',
						'scope'     => 'profile:read inboxes:read business_hours:read',
					]
				),
			]
		);

		$registered = ( new OAuthClient() )->register();

		$this->assertSame( 'client-abc', $registered['client_id'] );

		TokenStore::remember_client( $registered['client_id'], $registered['scope'] );

		$this->assertSame( 'profile:read inboxes:read business_hours:read', TokenStore::registered_scope() );
		$this->assertSame( 'client-abc', TokenStore::client_id() );
	}

	public function test_the_connect_button_is_nonce_protected(): void {
		$url = Connection::start_url();

		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		$this->assertSame( 'start', $query['td_oauth'] );
		$this->assertArrayHasKey( '_wpnonce', $query );
	}

	private function authorizationHeaderOf( string $url ): string {
		$seen = '';

		add_filter(
			'pre_http_request',
			static function ( $pre, $args ) use ( &$seen ) {
				$seen = $args['headers']['Authorization'];

				return [ 'response' => [ 'code' => 200 ], 'body' => wp_json_encode( [] ) ];
			},
			10,
			2
		);

		( new TDApiService() )->getRequest( $url );

		return $seen;
	}
}
