<?php

namespace ThriveDesk\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * When the desk is staffed, mirrored from ThriveDesk.
 *
 * Read-only on purpose. Business hours and holidays are set in ThriveDesk and
 * used by everything there - routing, auto-replies, reports - so a second place
 * to edit them from WordPress would be a second answer to the same question.
 * This fetches them, normalises them into something a browser can count down
 * against, and stops.
 *
 * The normalising is the point. What the API returns is a flat list of day
 * fragments with times as strings; what the portal needs is a week it can index
 * by weekday, in seconds, with no window crossing midnight. Doing that here
 * rather than in the browser keeps the awkward cases - split shifts, night
 * shifts, a day named twice - in one testable place.
 */
class BusinessHoursService {

	/**
	 * Cached upstream payloads.
	 *
	 * Named under `thrivedesk_` so remove_thrivedesk_all_cache() - which is
	 * what "Clear portal cache" calls - takes them with it. Someone who has
	 * just changed their hours in ThriveDesk and come back here to see why the
	 * portal disagrees will press that button, and it should work.
	 */
	public const PROFILES_TRANSIENT = 'thrivedesk_business_hours';
	public const HOLIDAYS_TRANSIENT = 'thrivedesk_holidays';

	/**
	 * Six hours, matching WorkspaceService. Hours are not changed often, and
	 * the clear-cache button covers the times they are.
	 */
	public const TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Timeout for a lookup that happens inside a page render.
	 *
	 * The portal shortcode blocks on this, so it gets the same short leash
	 * PortalService uses rather than TDApiService::DEFAULT_TIMEOUT. A helpdesk
	 * having a bad day must not become a portal that takes 90 seconds to draw.
	 */
	public const RENDER_TIMEOUT = 5;

	/**
	 * Weekday keys as the API names them, indexed to match PHP's `w` and
	 * JavaScript's getDay(): 0 is Sunday.
	 */
	public const DAYS = [ 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' ];

	private const DAY_SECONDS = 86400;

	/**
	 * Every business-hours profile on the workspace.
	 *
	 * @param bool $refresh Ignore the cache and re-fetch.
	 *
	 * @return array<int, array> Profiles as the API returned them, or [].
	 */
	public static function profiles( bool $refresh = false ): array {
		return self::fetch( '/v1/business-hours', self::PROFILES_TRANSIENT, $refresh );
	}

	/**
	 * Every holiday on the workspace.
	 *
	 * @param bool $refresh Ignore the cache and re-fetch.
	 *
	 * @return array<int, array> Holidays as the API returned them, or [].
	 */
	public static function holidays( bool $refresh = false ): array {
		return self::fetch( '/v1/holidays', self::HOLIDAYS_TRANSIENT, $refresh );
	}

	/**
	 * Does this workspace have business hours set up at all?
	 *
	 * What gates the checkbox on the settings screen. "No profiles" is the
	 * whole reason the control is offered disabled rather than hidden - the
	 * admin needs to be told there is something to go and switch on, not left
	 * wondering where the feature went.
	 */
	public static function is_available(): bool {
		return [] !== self::profiles();
	}

	/**
	 * One profile by id, falling back to the first.
	 *
	 * The fallback is what makes the id optional everywhere else: a workspace
	 * with a single profile never has to store which one it means, and a stored
	 * id that has since been deleted upstream degrades to the first profile
	 * rather than to nothing.
	 *
	 * @param string $id Profile id, or '' for the default.
	 *
	 * @return array|null
	 */
	public static function profile( string $id = '' ) {
		$profiles = self::profiles();

		if ( [] === $profiles ) {
			return null;
		}

		if ( '' !== $id ) {
			foreach ( $profiles as $profile ) {
				if ( is_array( $profile ) && (string) ( $profile['id'] ?? '' ) === $id ) {
					return $profile;
				}
			}
		}

		$first = reset( $profiles );

		return is_array( $first ) ? $first : null;
	}

	/**
	 * Everything the portal bar needs, in one array ready to be JSON-encoded
	 * onto a data attribute.
	 *
	 * `now` is here so the browser can measure its own clock against the
	 * server's and correct for the difference. Without it a visitor whose
	 * machine is an hour out would be told, with total confidence, the wrong
	 * time to expect a reply.
	 *
	 * Returns null when there is nothing worth drawing a bar for: no profile,
	 * or a profile with no usable schedule.
	 *
	 * @param string $id Profile id, or '' for the default.
	 *
	 * @return array|null
	 */
	public static function payload( string $id = '' ) {
		$profile = self::profile( $id );

		if ( null === $profile ) {
			return null;
		}

		$timezone = self::timezone( $profile );
		$now      = time();
		$always   = self::is_always_open( $profile );
		$week     = $always ? [] : self::week( $profile );

		// A profile that is neither 24/7 nor has a single open window is not a
		// schedule, it is a misconfiguration. Saying "closed, indefinitely" on
		// the strength of it would be worse than saying nothing.
		if ( ! $always && ! self::has_any_window( $week ) ) {
			return null;
		}

		return [
			'name'     => (string) ( $profile['name'] ?? '' ),
			'always'   => $always,
			'week'     => $week,
			'offset'   => $timezone->getOffset( new \DateTimeImmutable( '@' . $now ) ),
			'now'      => $now,
			'holidays' => self::holiday_ranges( $timezone, $now ),
		];
	}

	/**
	 * Is this profile open around the clock?
	 *
	 * Matched loosely rather than against a fixed string: the documented value
	 * is `calendar_24_7`, and a mode that spells the same idea differently
	 * should still not be counted down against.
	 *
	 * @param array $profile Profile payload.
	 */
	private static function is_always_open( array $profile ): bool {
		return false !== strpos( (string) ( $profile['mode'] ?? '' ), '24_7' );
	}

	/**
	 * The profile's week as seconds-from-midnight windows, indexed by weekday.
	 *
	 * Three things are sorted out here, all of which would otherwise land in
	 * the browser:
	 *
	 * - a day named more than once (a split shift) keeps both windows;
	 * - a window that ends before it starts is a night shift, and is cut at
	 *   midnight into a window on each day, so nothing the browser sees ever
	 *   spans a day boundary;
	 * - windows are sorted, so "the next one" is the first one that starts
	 *   after now.
	 *
	 * @param array $profile Profile payload.
	 *
	 * @return array<int, array<int, array{0:int,1:int}>>
	 */
	private static function week( array $profile ): array {
		$week = array_fill( 0, 7, [] );

		foreach ( (array) ( $profile['schedule'] ?? [] ) as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['enabled'] ) ) {
				continue;
			}

			$day = array_search( strtolower( (string) ( $entry['day'] ?? '' ) ), self::DAYS, true );

			if ( false === $day ) {
				continue;
			}

			$start = self::seconds( $entry['start'] ?? '' );
			$end   = self::seconds( $entry['end'] ?? '' );

			if ( null === $start || null === $end || $start === $end ) {
				continue;
			}

			if ( $end > $start ) {
				$week[ $day ][] = [ $start, $end ];
				continue;
			}

			// Ends before it starts: open overnight. Split at midnight so the
			// browser only ever deals in same-day windows.
			$week[ $day ][]                = [ $start, self::DAY_SECONDS ];
			$week[ ( $day + 1 ) % 7 ][] = [ 0, $end ];
		}

		foreach ( $week as $day => $windows ) {
			usort(
				$windows,
				static function ( $a, $b ) {
					return $a[0] <=> $b[0];
				}
			);

			$week[ $day ] = self::merge( $windows );
		}

		return $week;
	}

	/**
	 * Fuse windows that touch or overlap.
	 *
	 * A desk listed as open 09:00-12:00 and 12:00-17:00 is open 09:00-17:00, and
	 * the difference shows: without this the portal counts down to a "close" at
	 * noon that never happens, then jumps back to five hours remaining.
	 *
	 * @param array<int, array{0:int,1:int}> $windows Sorted windows for one day.
	 *
	 * @return array<int, array{0:int,1:int}>
	 */
	private static function merge( array $windows ): array {
		$merged = [];

		foreach ( $windows as $window ) {
			$last = count( $merged ) - 1;

			if ( $last >= 0 && $window[0] <= $merged[ $last ][1] ) {
				$merged[ $last ][1] = max( $merged[ $last ][1], $window[1] );
				continue;
			}

			$merged[] = $window;
		}

		return $merged;
	}

	/**
	 * @param array<int, array> $week Normalised week.
	 */
	private static function has_any_window( array $week ): bool {
		foreach ( $week as $windows ) {
			if ( [] !== $windows ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A time string as seconds from midnight.
	 *
	 * Accepts `9:00`, `09:00` and `09:00:00`, because the documented type for
	 * these fields is only "string" and all three are things a helpdesk stores.
	 * Anything else is not a time and is dropped rather than guessed at.
	 *
	 * @param mixed $value Raw time.
	 *
	 * @return int|null
	 */
	private static function seconds( $value ) {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return null;
		}

		if ( ! preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim( (string) $value ), $parts ) ) {
			return null;
		}

		$hours   = (int) $parts[1];
		$minutes = (int) $parts[2];
		$seconds = isset( $parts[3] ) ? (int) $parts[3] : 0;

		if ( $hours > 24 || $minutes > 59 || $seconds > 59 ) {
			return null;
		}

		return min( $hours * HOUR_IN_SECONDS + $minutes * MINUTE_IN_SECONDS + $seconds, self::DAY_SECONDS );
	}

	/**
	 * Which timezone the profile's times are in.
	 *
	 * The documented list payload carries no timezone, so this reads one if the
	 * live API sends it and falls back to the site's own. That fallback is the
	 * right guess rather than a safe one - a desk whose hours are kept in a
	 * different zone from the site running its portal would need the API to say
	 * so - but it is the only guess available, and it is correct for a site and
	 * a helpdesk run by the same people, which is nearly all of them.
	 *
	 * @param array $profile Profile payload.
	 */
	private static function timezone( array $profile ): \DateTimeZone {
		foreach ( [ 'timezone', 'time_zone', 'tz' ] as $key ) {
			$value = $profile[ $key ] ?? '';

			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}

			try {
				return new \DateTimeZone( $value );
			} catch ( \Exception $e ) {
				// Not a zone this PHP knows. Fall through to the site's.
				continue;
			}
		}

		return wp_timezone();
	}

	/**
	 * Holidays as epoch ranges, with the ones already over dropped.
	 *
	 * Ranges rather than a single "is today a holiday" answer, so a portal left
	 * open across midnight starts and stops announcing one at the right moment
	 * instead of at the next page load. `end_date` is inclusive upstream - a
	 * one-day holiday has the same start and end - so the range runs to the end
	 * of that day.
	 *
	 * @param \DateTimeZone $timezone Zone the dates are stated in.
	 * @param int           $now      Server epoch.
	 *
	 * @return array<int, array{name:string, from:int, to:int}>
	 */
	private static function holiday_ranges( \DateTimeZone $timezone, int $now ): array {
		$ranges = [];

		foreach ( self::holidays() as $holiday ) {
			if ( ! is_array( $holiday ) ) {
				continue;
			}

			$from = self::day_start( $holiday['start_date'] ?? '', $timezone );
			$to   = self::day_start( $holiday['end_date'] ?? ( $holiday['start_date'] ?? '' ), $timezone );

			if ( null === $from || null === $to ) {
				continue;
			}

			$to += self::DAY_SECONDS;

			if ( $to <= $now ) {
				continue;
			}

			$ranges[] = [
				'name' => (string) ( $holiday['name'] ?? '' ),
				'from' => $from,
				'to'   => $to,
			];
		}

		usort(
			$ranges,
			static function ( $a, $b ) {
				return $a['from'] <=> $b['from'];
			}
		);

		return $ranges;
	}

	/**
	 * Midnight on a Y-m-d date, in the given zone, as an epoch.
	 *
	 * @param mixed         $date     Raw date.
	 * @param \DateTimeZone $timezone Zone to read it in.
	 *
	 * @return int|null
	 */
	private static function day_start( $date, \DateTimeZone $timezone ) {
		if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', trim( $date ) ) ) {
			return null;
		}

		$parsed = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', trim( $date ) . ' 00:00:00', $timezone );

		return $parsed instanceof \DateTimeImmutable ? $parsed->getTimestamp() : null;
	}

	/**
	 * One cached GET against the API.
	 *
	 * Returns [] for everything that is not a list of things: not connected, a
	 * transport error, a typed error array from TDApiService, or a body that is
	 * not shaped like the documented `{"data": [...]}`. Every caller treats []
	 * as "no hours to show", which is the correct behaviour for all of those.
	 *
	 * @param string $path      Path under the API root.
	 * @param string $transient Cache key.
	 * @param bool   $refresh   Skip the cache.
	 *
	 * @return array<int, array>
	 */
	private static function fetch( string $path, string $transient, bool $refresh ): array {
		// Guarded before the cache is even consulted: an install that has not
		// connected has no key to send, and the portal renders on every page
		// view. A round of failing requests per render is exactly the load the
		// portal has been bitten by before.
		if ( ! thrivedesk_is_connected() ) {
			return [];
		}

		if ( ! $refresh ) {
			$cached = get_transient( $transient );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$response = ( new TDApiService() )->getRequest( THRIVEDESK_API_URL . $path, self::RENDER_TIMEOUT );

		// Failures are cached too, and for the full TTL. An empty list and a
		// list we could not fetch look identical to every caller, so re-asking
		// on the next render would buy nothing and cost a round trip per page
		// view. "Clear portal cache" is the way back.
		$list = ( is_array( $response ) && ! isset( $response['wp_error'] ) && isset( $response['data'] ) && is_array( $response['data'] ) )
			? array_values( array_filter( $response['data'], 'is_array' ) )
			: [];

		set_transient( $transient, $list, self::TTL );

		return $list;
	}
}
