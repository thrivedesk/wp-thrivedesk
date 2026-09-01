<?php

namespace ThriveDesk\Services\OAuth;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Talks to ThriveDesk's authorization server on behalf of this site.
 *
 * The site is a public client: it self-registers (RFC 7591), then runs authorization code
 * with PKCE (RFC 7636). There is no client secret anywhere, because a plugin distributed to
 * every customer cannot hold one.
 */
final class OAuthClient {

	private const TIMEOUT = 30;

	/**
	 * Where ThriveDesk sends the browser back to. Registered once and sent again on every
	 * authorize request, so the two must be produced here and nowhere else: Passport compares
	 * them byte for byte and a mismatch is an unexplained invalid_request.
	 */
	public static function redirect_uri(): string {
		return admin_url( 'admin.php?page=thrivedesk&td_oauth=callback' );
	}

	/**
	 * Register this site.
	 *
	 * Returns the client id and the scope string the server registered it for. The scopes are
	 * read back rather than assumed: the server owns that list, and a plugin that asked for a
	 * scope its client is not registered for is rejected with invalid_scope at consent.
	 *
	 * @return array{client_id: string, scope: string}|WP_Error
	 */
	public function register() {
		$response = wp_remote_post(
			THRIVEDESK_API_URL . '/oauth/wordpress/register',
			[
				'timeout' => self::TIMEOUT,
				'headers' => [
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				],
				'body'    => wp_json_encode( [ 'redirect_uris' => [ self::redirect_uri() ] ] ),
			]
		);

		$body = $this->decode( $response );

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		if ( empty( $body['client_id'] ) ) {
			return new WP_Error(
				'thrivedesk_oauth_register',
				__( 'ThriveDesk did not return a client id for this site.', 'thrivedesk' )
			);
		}

		return [
			'client_id' => (string) $body['client_id'],
			'scope'     => isset( $body['scope'] ) ? (string) $body['scope'] : '',
		];
	}

	/**
	 * The URL to send the administrator to for consent.
	 *
	 * @param string[] $scopes
	 */
	public function authorize_url( string $client_id, string $state, string $challenge, array $scopes ): string {
		return THRIVEDESK_API_URL . '/oauth/authorize?' . http_build_query(
			[
				'client_id'             => $client_id,
				'redirect_uri'          => self::redirect_uri(),
				'response_type'         => 'code',
				'scope'                 => implode( ' ', $scopes ),
				'state'                 => $state,
				'code_challenge'        => $challenge,
				'code_challenge_method' => 'S256',
			]
		);
	}

	/**
	 * Trade an authorization code for tokens.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function exchange_code( string $client_id, string $code, string $verifier ) {
		return $this->token_request(
			[
				'grant_type'    => 'authorization_code',
				'client_id'     => $client_id,
				'redirect_uri'  => self::redirect_uri(),
				'code'          => $code,
				'code_verifier' => $verifier,
			]
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function refresh( string $client_id, string $refresh_token ) {
		return $this->token_request(
			[
				'grant_type'    => 'refresh_token',
				'client_id'     => $client_id,
				'refresh_token' => $refresh_token,
			]
		);
	}

	/**
	 * @param array<string, string> $body
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function token_request( array $body ) {
		$response = wp_remote_post(
			THRIVEDESK_API_URL . '/oauth/token',
			[
				'timeout' => self::TIMEOUT,
				'headers' => [ 'Accept' => 'application/json' ],
				'body'    => $body,
			]
		);

		$decoded = $this->decode( $response );

		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		if ( empty( $decoded['access_token'] ) ) {
			return new WP_Error(
				'thrivedesk_oauth_token',
				__( 'ThriveDesk did not return an access token.', 'thrivedesk' )
			);
		}

		return $decoded;
	}

	/**
	 * @param array|WP_Error $response Raw wp_remote_* return value.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function decode( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			$body = [];
		}

		if ( $code < 200 || $code >= 300 ) {
			// OAuth error bodies are the useful half of a failure (invalid_grant, invalid_scope),
			// so they are surfaced. The token and code never appear in one.
			$detail = '';

			if ( isset( $body['error'] ) ) {
				$detail = (string) $body['error'];
			} elseif ( isset( $body['message'] ) ) {
				$detail = (string) $body['message'];
			}

			return new WP_Error(
				'thrivedesk_oauth_http',
				sprintf(
					/* translators: 1: HTTP status code, 2: error identifier returned by ThriveDesk */
					__( 'ThriveDesk refused the request (HTTP %1$d) %2$s', 'thrivedesk' ),
					(int) $code,
					$detail
				)
			);
		}

		return $body;
	}
}
