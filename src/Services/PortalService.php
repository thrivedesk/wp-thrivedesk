<?php

namespace ThriveDesk\Services;

if (!defined('ABSPATH')) {
	exit;
}

class PortalService {
	/**
	 * Transient holding the cached entitlement answer.
	 *
	 * TDApiService::clearAllTransients() deletes this same key.
	 */
	public const PORTAL_ACCESS_TRANSIENT = 'thrivedesk_portal_access';

	/**
	 * How long a cached answer lives. A "no" expires sooner so an upgrade, or
	 * a plan lookup that failed for transient reasons, isn't locked out for
	 * the whole positive window.
	 */
	public const ACCESS_TTL_GRANTED = 6 * HOUR_IN_SECONDS;
	public const ACCESS_TTL_DENIED  = 5 * MINUTE_IN_SECONDS;

	/**
	 * Timeout for the plan lookup when it happens inside a page render. The
	 * portal shortcode blocks on this call, so a slow or hanging API must not
	 * be able to hold a PHP worker for TDApiService::DEFAULT_TIMEOUT.
	 */
	public const RENDER_TIMEOUT = 5;

	private static $instance = null;

	public $plans = [
		'founder-ltd-pro',
		'business_ltd_23',
		'business_ltd_22',
		'agency_plus_ltd',
		'agency_20_workspaces_ltd',
		'agency-ltd',
        'pro',
		'founder-ltd-business',
		'pro_annual',
		'plus_annual_july_2023',
		'plus_july_2023',
        'starter_july_23',
        'starter_annual_july_23',
		'business_ltd',
		'business-ltd',
		'appsumo_tier_4'
	];

	public function __construct(  ) {
		add_action('wp_ajax_thrivedesk_check_portal_access', [$this, 'check_portal_access']);
	}

	public static function instance(): PortalService
	{
		if (!isset(self::$instance) && !(self::$instance instanceof PortalService)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function check_portal_access(  ) {
		if (
			! isset( $_POST['nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'thrivedesk-nonce' )
			|| ! current_user_can( 'manage_options' )
		) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'thrivedesk' ) ], 403 );
		}

		$apiKey = sanitize_text_field( wp_unslash( $_POST['data']['td_helpdesk_api_key'] ?? '' ) );
		if (empty( $apiKey ) ) {
			echo wp_json_encode( [
				'code' => 422,
				'status' => 'error',
				'data' => [
					'message' => 'API Key is required'
				]
			] );
			die();
		}

		$cached = self::cached_access();

		if ( true === $cached ) {
			echo wp_json_encode( [
				'code' => 200,
				'status' => 'success',
				'data' => true
			] );
			die();
		}

		$plan = $this->get_plan( $apiKey );

		if ( $this->plan_grants_access( $plan ) ) {
			self::cache_access( true );
			echo wp_json_encode( [
				'code' => 200,
				'status' => 'success',
				'data' => true
			] );
		} else {
			self::cache_access( false );
			echo wp_json_encode( [
				'code' => 422,
				'status' => 'error',
				'data' => false
			] );
		}
		die();
	}

	/**
	 * @param string $apiKey  Override key, or '' for the stored one.
	 * @param int    $timeout Request timeout in seconds.
	 *
	 * @return array
	 */
	public function get_plan( $apiKey = '', int $timeout = TDApiService::DEFAULT_TIMEOUT ) {
		$apiService = new TDApiService();
		if (!empty( $apiKey )) {
			$apiService->setApiKey( sanitize_text_field( (string) $apiKey ) );
		}

		return $apiService->getRequest( THRIVEDESK_API_URL . '/v1/billing/plans/current', $timeout );
	}

	/**
	 * Does this plan payload entitle the store to the portal?
	 *
	 * @param mixed $plan Decoded plan response.
	 */
	private function plan_grants_access( $plan ): bool {
		return is_array( $plan )
			&& isset( $plan['overview']['slug'] )
			&& $this->is_portal_plan( (string) $plan['overview']['slug'] );
	}

	/**
	 * Does this plan slug entitle the store to the portal?
	 *
	 * Public so the settings screen can say so up front rather than letting the
	 * feature turn out to be missing. Kept as the one place the allowlist is
	 * read, so the card and the gate can never disagree.
	 *
	 * @param string $slug Plan slug from the billing payload.
	 */
	public function is_portal_plan( string $slug ): bool {
		return in_array( $slug, $this->plans, true );
	}

	/**
	 * The cached entitlement answer, or null when nothing is cached.
	 *
	 * Stored as a 'yes'/'no' sentinel rather than a boolean: a transient
	 * holding false is indistinguishable from a miss, so caching false would
	 * cache nothing at all.
	 *
	 * @return bool|null
	 */
	private static function cached_access() {
		$cached = get_transient( self::PORTAL_ACCESS_TRANSIENT );

		if ( 'yes' === $cached ) {
			return true;
		}

		if ( 'no' === $cached ) {
			return false;
		}

		return null;
	}

	private static function cache_access( bool $has_access ): void {
		set_transient(
			self::PORTAL_ACCESS_TRANSIENT,
			$has_access ? 'yes' : 'no',
			$has_access ? self::ACCESS_TTL_GRANTED : self::ACCESS_TTL_DENIED
		);
	}

	/**
	 * Is the portal available to this store?
	 *
	 * Runs on every portal render, so both halves matter: a miss must cache
	 * whatever it learns (including "no"), and the lookup behind a miss must
	 * use the short render timeout. Without either, any logged-in user could
	 * pin a PHP worker per page view by hitting the portal.
	 */
	public function has_portal_access(  ): bool {
		$cached = self::cached_access();

		if ( null !== $cached ) {
			return $cached;
		}

		$has_access = $this->plan_grants_access( $this->get_plan( '', self::RENDER_TIMEOUT ) );

		self::cache_access( $has_access );

		return $has_access;
	}
}
