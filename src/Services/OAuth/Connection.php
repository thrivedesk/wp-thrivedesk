<?php

namespace ThriveDesk\Services\OAuth;

use ThriveDesk\Services\TDApiService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drives the connect flow from the WordPress side.
 *
 * Both legs run on admin_init rather than through admin-ajax: the first leg has to end in a
 * redirect off-site, and the second leg is a browser landing back on the settings page, which
 * is a page load either way.
 */
final class Connection {

	private const START    = 'start';
	private const CALLBACK = 'callback';
	private const NONCE    = 'thrivedesk-oauth-connect';

	/**
	 * Matches the authorization_request_ttl the server parks a consent request for.
	 */
	private const PENDING_TTL = 600;

	private static $instance = null;

	/**
	 * @var OAuthClient
	 */
	private $client;

	private function __construct() {
		$this->client = new OAuthClient();

		add_action( 'admin_init', [ $this, 'handle' ] );
		add_action( 'admin_notices', [ $this, 'notices' ] );
		add_action( 'wp_ajax_thrivedesk_oauth_disconnect', [ $this, 'ajax_disconnect' ] );
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * The URL the Connect button points at.
	 */
	public static function start_url(): string {
		// Not wp_nonce_url(): that html-escapes the separator, so the value only works when it
		// is printed into markup. This one is a plain URL and the view escapes it.
		return add_query_arg(
			[
				'td_oauth' => self::START,
				'_wpnonce' => wp_create_nonce( self::NONCE ),
			],
			admin_url( 'admin.php?page=thrivedesk' )
		);
	}

	public function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only; each leg verifies below
		$leg = isset( $_GET['td_oauth'] ) ? sanitize_key( wp_unslash( $_GET['td_oauth'] ) ) : '';

		if ( self::START === $leg ) {
			$this->start();
		} elseif ( self::CALLBACK === $leg ) {
			$this->finish();
		}
	}

	/**
	 * Register the site if it has no client yet, then send the admin to consent.
	 */
	private function start(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( self::NONCE ) ) {
			wp_die( esc_html__( 'You are not allowed to connect this site.', 'thrivedesk' ), '', [ 'response' => 403 ] );
		}

		$client_id = TokenStore::client_id();

		if ( '' === $client_id || '' === TokenStore::registered_scope() ) {
			$registered = $this->client->register();

			if ( is_wp_error( $registered ) ) {
				$this->bail( $registered->get_error_message() );
			}

			$client_id = $registered['client_id'];
			TokenStore::remember_client( $client_id, $registered['scope'] );
		}

		$verifier = Pkce::verifier();
		$state    = Pkce::state();

		set_transient(
			$this->pending_key( $state ),
			[
				'verifier'  => $verifier,
				'client_id' => $client_id,
				'user_id'   => get_current_user_id(),
			],
			self::PENDING_TTL
		);

		// wp_safe_redirect refuses an off-site host, and consent is by definition off-site.
		wp_redirect(
			$this->client->authorize_url( $client_id, $state, Pkce::challenge( $verifier ), $this->scopes() )
		);
		exit;
	}

	/**
	 * Consume the authorization code ThriveDesk redirected back with.
	 */
	private function finish(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to connect this site.', 'thrivedesk' ), '', [ 'response' => 403 ] );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- state below is the CSRF check; a nonce cannot survive an off-site redirect
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$pending = '' === $state ? false : get_transient( $this->pending_key( $state ) );

		// One shot. A replayed code lands here with nothing to pair it against.
		if ( '' !== $state ) {
			delete_transient( $this->pending_key( $state ) );
		}

		if ( '' !== $error ) {
			$this->bail(
				'access_denied' === $error
					? __( 'The connection was cancelled.', 'thrivedesk' )
					: __( 'ThriveDesk refused the connection.', 'thrivedesk' )
			);
		}

		// An unrecognised state is the whole point of state: something other than this site's
		// own start leg produced this callback.
		if ( ! is_array( $pending ) || empty( $pending['verifier'] ) || empty( $pending['client_id'] ) || '' === $code ) {
			$this->bail( __( 'The connection response could not be matched to a request from this site.', 'thrivedesk' ) );
		}

		if ( (int) ( $pending['user_id'] ?? 0 ) !== get_current_user_id() ) {
			$this->bail( __( 'The connection was started by a different user.', 'thrivedesk' ) );
		}

		$token = $this->client->exchange_code( (string) $pending['client_id'], $code, (string) $pending['verifier'] );

		if ( is_wp_error( $token ) ) {
			$this->bail( $token->get_error_message() );
		}

		TokenStore::save( $token, (string) $pending['client_id'] );

		// The account details the rest of the plugin reads come from /v1/me, and the fresh
		// token is what makes that call answer.
		( new TDApiService() )->clearAllTransients();

		$this->redirect_to_settings( [ 'td_oauth_connected' => '1' ] );
	}

	/**
	 * Report the outcome of a connect attempt on the page it redirects back to.
	 */
	public function notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display of our own redirect
		if ( isset( $_GET['td_oauth_connected'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'This site is now connected to ThriveDesk.', 'thrivedesk' )
			);

			return;
		}

		if ( ! empty( $_GET['td_oauth_error'] ) ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['td_oauth_error'] ) ) ) )
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	public function ajax_disconnect(): void {
		if ( ! current_user_can( 'manage_options' )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'thrivedesk-nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'thrivedesk' ) ], 403 );
		}

		TokenStore::clear();
		( new TDApiService() )->clearAllTransients();

		wp_send_json_success( [ 'message' => __( 'Disconnected from ThriveDesk.', 'thrivedesk' ) ] );
	}

	/**
	 * The scopes to ask consent for.
	 *
	 * Taken from what registration said this client is registered for, so the list lives in
	 * one place (the server). Asking for anything else is refused with invalid_scope, and a
	 * hardcoded copy here would turn any server-side change into a broken Connect button.
	 *
	 * @return string[]
	 */
	private function scopes(): array {
		return array_values( array_filter( explode( ' ', TokenStore::registered_scope() ) ) );
	}

	private function pending_key( string $state ): string {
		// hashed so the transient name cannot be steered by the query string
		return 'td_oauth_pending_' . hash( 'sha256', $state );
	}

	private function bail( string $message ): void {
		$this->redirect_to_settings( [ 'td_oauth_error' => rawurlencode( $message ) ] );
	}

	/**
	 * @param array<string, string> $args
	 */
	private function redirect_to_settings( array $args ): void {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=thrivedesk' ) ) );
		exit;
	}
}
