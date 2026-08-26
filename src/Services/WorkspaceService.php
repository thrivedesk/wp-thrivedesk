<?php

namespace ThriveDesk\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * What ThriveDesk says about this workspace: who it is, what it pays for, and
 * which parts of the API the stored key can actually reach.
 *
 * The last of those exists because the plugin cannot otherwise tell "you have
 * no assistants" from "your key may not read assistants" - both arrive as an
 * empty array, and the settings screen has been telling people to create
 * things they may already have.
 */
class WorkspaceService {

	/**
	 * An option, not a transient, because this is served stale on purpose: a
	 * transient that has expired is gone, and gone means blocking the next page
	 * render on five HTTP requests. Stored with autoload off - only the
	 * ThriveDesk screens read it.
	 */
	const SUMMARY_OPTION = 'td_workspace_summary';

	/** Cron hook that refreshes the stored summary out of band. */
	const REFRESH_HOOK = 'thrivedesk_refresh_workspace_summary';

	/**
	 * Six hours. Long enough that this is not a per-pageview cost, short enough
	 * that a plan upgrade shows up the same working day.
	 */
	const SUMMARY_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Per-request timeout for a probe.
	 *
	 * Deliberately short. These run while an admin page is rendering, and five
	 * endpoints at the default timeout would be a long time to stare at a blank
	 * screen if ThriveDesk is having a bad day.
	 */
	const PROBE_TIMEOUT = 3;

	/**
	 * The endpoints worth reporting on, in the order they are shown.
	 *
	 * `account` is first because it is the gate: if the key cannot read /v1/me
	 * there is no point probing the rest.
	 */
	const CAPABILITIES = [
		'account'       => '/v1/me',
		'billing'       => '/v1/billing/plans/current',
		'assistants'    => '/v1/assistants',
		'inboxes'       => '/v1/inboxes',
		'knowledgebase' => '/v1/knowledgebases',
	];

	/**
	 * The cached summary, refreshing it when cold.
	 *
	 * @param bool $refresh Ignore whatever is cached and probe again.
	 *
	 * @return array
	 */
	public static function summary( bool $refresh = false ): array {
		$stored = $refresh ? false : get_option( self::SUMMARY_OPTION );

		if ( ! is_array( $stored ) ) {
			// Nothing to serve, so this one caller pays for the probes. Happens
			// once per key, not once per page.
			return self::store( self::build() );
		}

		if ( time() - (int) ( $stored['checked_at'] ?? 0 ) > self::SUMMARY_TTL ) {
			// Stale, not useless. Hand back what we have and let cron replace
			// it, rather than making whoever loaded this page wait for five
			// round trips to ThriveDesk.
			self::schedule_refresh();
		}

		return $stored;
	}

	/**
	 * Refresh now, in the background. Bound to the cron hook.
	 */
	public static function refresh(): void {
		self::store( self::build() );
	}

	/**
	 * Drop what is stored. Call after anything that could change the answer - a
	 * new API key above all.
	 */
	public static function flush(): void {
		delete_option( self::SUMMARY_OPTION );
	}

	/**
	 * @param array $summary Freshly built summary.
	 *
	 * @return array
	 */
	private static function store( array $summary ): array {
		update_option( self::SUMMARY_OPTION, $summary, false );

		return $summary;
	}

	private static function schedule_refresh(): void {
		if ( ! wp_next_scheduled( self::REFRESH_HOOK ) ) {
			wp_schedule_single_event( time(), self::REFRESH_HOOK );
		}
	}

	/**
	 * @return array
	 */
	private static function build(): array {
		$api_key = get_option( 'td_helpdesk_settings' )['td_helpdesk_api_key'] ?? '';

		$summary = [
			'connected'  => false,
			'workspace'  => self::workspace(),
			'plan'       => null,
			'api'        => [],
			'checked_at' => time(),
		];

		if ( empty( $api_key ) ) {
			return $summary;
		}

		$account = self::probe( $api_key, self::CAPABILITIES['account'] );

		$summary['api']['account'] = self::capability( $account );
		$summary['connected']      = $summary['api']['account']['ok'];

		// No point probing four more endpoints with a key the account endpoint
		// already rejected; they will all say the same thing, slowly.
		if ( ! $summary['connected'] ) {
			return $summary;
		}

		foreach ( self::CAPABILITIES as $name => $path ) {
			if ( 'account' === $name ) {
				continue;
			}

			$response = self::probe( $api_key, $path );

			$summary['api'][ $name ] = self::capability( $response );

			if ( 'billing' === $name ) {
				$summary['plan'] = self::plan( $response['body'] );
			}
		}

		return $summary;
	}

	/**
	 * The workspace identity, read from what the connect handshake already
	 * stored. No request: this does not change often enough to pay for one.
	 *
	 * @return array
	 */
	private static function workspace(): array {
		$info = get_option( 'td_helpdesk_system_info' );
		$info = is_array( $info ) ? $info : [];

		return [
			'name' => (string) ( $info['company'] ?? '' ),
		];
	}

	/**
	 * Reduce a plan payload to what the card shows.
	 *
	 * `portal` is not a field ThriveDesk returns - it is whether this plan's
	 * slug appears in PortalService's allowlist, which is the same question the
	 * portal itself asks. Showing it here means the answer is visible before a
	 * feature turns out to be missing.
	 *
	 * @param mixed $body Decoded billing response.
	 *
	 * @return array|null
	 */
	private static function plan( $body ) {
		$overview = is_array( $body ) ? ( $body['overview'] ?? null ) : null;

		if ( ! is_array( $overview ) ) {
			return null;
		}

		return [
			'label'        => (string) ( $overview['label'] ?? '' ),
			'slug'         => (string) ( $overview['slug'] ?? '' ),
			'billing_type' => (string) ( $overview['billing_type'] ?? '' ),
			'expired'      => (bool) ( $overview['is_subscription_expired'] ?? false ),
			'portal'       => PortalService::instance()->is_portal_plan( (string) ( $overview['slug'] ?? '' ) ),
		];
	}

	/**
	 * @param array $response Raw probe result.
	 *
	 * @return array
	 */
	private static function capability( array $response ): array {
		return [
			'ok'     => 200 === $response['status'],
			'status' => $response['status'],
		];
	}

	/**
	 * One GET, reporting the status code rather than swallowing it.
	 *
	 * TDApiService is not used here on purpose: it maps everything non-200 onto
	 * an error array without the code, and the code is the entire point - a 403
	 * means the key cannot do this, a 404 means the endpoint moved, and a 0
	 * means we never reached ThriveDesk at all.
	 *
	 * @param string $api_key Bearer token.
	 * @param string $path    Path under the API root.
	 *
	 * @return array{status:int, body:mixed}
	 */
	private static function probe( string $api_key, string $path ): array {
		$response = wp_remote_get(
			THRIVEDESK_API_URL . $path,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				],
				'timeout' => self::PROBE_TIMEOUT,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [ 'status' => 0, 'body' => null ];
		}

		return [
			'status' => (int) wp_remote_retrieve_response_code( $response ),
			'body'   => json_decode( wp_remote_retrieve_body( $response ), true ),
		];
	}
}
