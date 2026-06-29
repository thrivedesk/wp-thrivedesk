<?php
/**
 * Regression tests for the portal i18n helpers.
 *
 * The customer-facing portal showed several English strings in an otherwise
 * translated UI: the relative timestamps ("3 months ago") and the status
 * badges ("ACTIVE"/"CLOSED"). These tests lock in that both helpers route
 * their output through the `thrivedesk` text domain, that the relative-time
 * pluralisation is correct, and that the output localises in real languages —
 * including ones where the "ago" phrase precedes the value (French/Spanish)
 * and ones with more than two plural forms (Russian).
 *
 * @package ThriveDesk
 */

class TD_I18n_Helpers_Test extends WP_UnitTestCase {

    public function set_up() {
        parent::set_up();

        if ( ! function_exists( 'diff_for_humans' ) || ! function_exists( 'td_conversation_status_label' ) ) {
            $this->markTestSkipped( 'Portal helpers are not loaded.' );
        }
    }

    /* ---------------------------------------------------------------------
     * diff_for_humans — English behaviour
     * ------------------------------------------------------------------- */

    public function test_diff_for_humans_empty_returns_unknown_time() {
        $this->assertSame( 'Unknown time', diff_for_humans( '' ) );
    }

    public function test_diff_for_humans_uses_singular_unit() {
        // ~25h ago: a one-hour buffer keeps the leading unit at exactly one day
        // regardless of sub-second drift between strtotime() and the function's
        // own "now".
        $when = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS + HOUR_IN_SECONDS ) );

        $this->assertSame( '1 day ago', diff_for_humans( $when ) );
    }

    public function test_diff_for_humans_uses_plural_unit() {
        $when = gmdate( 'Y-m-d H:i:s', time() - ( 3 * DAY_IN_SECONDS + HOUR_IN_SECONDS ) );

        $this->assertSame( '3 days ago', diff_for_humans( $when ) );
    }

    /* ---------------------------------------------------------------------
     * diff_for_humans — localisation
     * ------------------------------------------------------------------- */

    /**
     * Both the `_n()` unit and the `%s ago` wrapper must resolve against the
     * `thrivedesk` domain. The French/Spanish cases also prove the wrapper has
     * to be a full format string: the old `implode(...) . ' ago'` could never
     * render "il y a 3 jours" / "hace 3 días", where the phrase precedes the
     * value.
     *
     * @dataProvider ago_language_provider
     */
    public function test_diff_for_humans_translates_per_language( string $unit_plural, string $ago_format, string $expected ) {
        $unit = static function ( $translation, $single, $plural ) use ( $unit_plural ) {
            return $plural === '%d days' ? $unit_plural : $translation;
        };
        $ago = static function ( $translation, $text ) use ( $ago_format ) {
            return $text === '%s ago' ? $ago_format : $translation;
        };

        add_filter( 'ngettext_thrivedesk', $unit, 10, 3 );
        add_filter( 'gettext_thrivedesk', $ago, 10, 2 );

        $when = gmdate( 'Y-m-d H:i:s', time() - ( 3 * DAY_IN_SECONDS + HOUR_IN_SECONDS ) );

        $this->assertSame( $expected, diff_for_humans( $when ) );

        remove_filter( 'ngettext_thrivedesk', $unit, 10 );
        remove_filter( 'gettext_thrivedesk', $ago, 10 );
    }

    public function ago_language_provider(): array {
        return [
            'English' => [ '%d days', '%s ago', '3 days ago' ],
            'French'  => [ '%d jours', 'il y a %s', 'il y a 3 jours' ],
            'Spanish' => [ '%d días', 'hace %s', 'hace 3 días' ],
            'German'  => [ '%d Tage', 'vor %s', 'vor 3 Tage' ],
        ];
    }

    /**
     * Languages have different plural rules (Russian has three forms, Arabic
     * six, French treats 1 specially). The only way diff_for_humans() can be
     * correct in all of them is to hand the exact count to _n() and let the
     * active locale pick the form. Verify it passes the true integer for each
     * case — this fails the moment anyone reverts to a hardcoded
     * "n > 1 ? plural : singular" (which calls no _n() at all).
     */
    public function test_diff_for_humans_passes_exact_count_to_ngettext() {
        $captured = [];
        $capture  = static function ( $translation, $single, $plural, $number ) use ( &$captured ) {
            if ( '%d days' === $plural ) {
                $captured[] = $number;
            }
            return $translation;
        };

        add_filter( 'ngettext_thrivedesk', $capture, 10, 4 );

        foreach ( [ 1, 2, 5 ] as $days ) {
            $when = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS + HOUR_IN_SECONDS ) );
            diff_for_humans( $when );
        }

        remove_filter( 'ngettext_thrivedesk', $capture, 10 );

        $this->assertSame( [ 1, 2, 5 ], $captured, 'Each call must forward the real day count to _n() for the locale rule to apply.' );
    }

    /* ---------------------------------------------------------------------
     * td_conversation_status_label — English behaviour
     * ------------------------------------------------------------------- */

    /**
     * @dataProvider known_status_provider
     */
    public function test_status_label_maps_known_statuses( string $status, string $expected ) {
        $this->assertSame( $expected, td_conversation_status_label( $status ) );
    }

    public function known_status_provider(): array {
        return [
            'active'  => [ 'active', 'Active' ],
            'pending' => [ 'pending', 'Pending' ],
            'closed'  => [ 'closed', 'Closed' ],
        ];
    }

    public function test_status_label_is_case_insensitive() {
        $this->assertSame( 'Active', td_conversation_status_label( 'ACTIVE' ) );
        $this->assertSame( 'Closed', td_conversation_status_label( ' Closed ' ) );
    }

    public function test_status_label_handles_empty_and_unknown() {
        $this->assertSame( 'Unknown', td_conversation_status_label( '' ) );
        $this->assertSame( 'Unknown', td_conversation_status_label( 'unknown' ) );
    }

    public function test_status_label_falls_back_to_ucfirst_for_unmapped() {
        $this->assertSame( 'Archived', td_conversation_status_label( 'archived' ) );
    }

    /* ---------------------------------------------------------------------
     * td_conversation_status_label — localisation
     * ------------------------------------------------------------------- */

    /**
     * The badge label must localise in any language and script (Arabic here is
     * RTL/non-Latin), proving the slugs route through the `thrivedesk` domain.
     */
    public function test_status_label_translates_in_many_languages() {
        $en = [ 'active' => 'Active', 'pending' => 'Pending', 'closed' => 'Closed' ];

        $languages = [
            'French'  => [ 'active' => 'Actif', 'pending' => 'En attente', 'closed' => 'Fermé' ],
            'Spanish' => [ 'active' => 'Activo', 'pending' => 'Pendiente', 'closed' => 'Cerrado' ],
            'German'  => [ 'active' => 'Aktiv', 'pending' => 'Ausstehend', 'closed' => 'Geschlossen' ],
            'Arabic'  => [ 'active' => 'نشط', 'pending' => 'قيد الانتظار', 'closed' => 'مغلق' ],
        ];

        $current = [];
        $filter  = static function ( $translation, $text ) use ( &$current, $en ) {
            $slug = array_search( $text, $en, true );
            return ( false !== $slug && isset( $current[ $slug ] ) ) ? $current[ $slug ] : $translation;
        };

        add_filter( 'gettext_thrivedesk', $filter, 10, 2 );

        foreach ( $languages as $language => $map ) {
            $current = $map;
            foreach ( $map as $slug => $expected ) {
                $this->assertSame( $expected, td_conversation_status_label( $slug ), "{$language}: {$slug}" );
            }
        }

        remove_filter( 'gettext_thrivedesk', $filter, 10 );
    }
}
