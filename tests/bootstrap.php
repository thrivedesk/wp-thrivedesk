<?php
/**
 * PHPUnit bootstrap for the ThriveDesk plugin.
 *
 * @package ThriveDesk
 */

// WP_TESTS_DIR override, then the wp-phpunit Composer package's own
// WP_PHPUNIT__DIR env var, then the bundled vendor path, then the legacy
// install-wp-tests.sh default of /tmp/wordpress-tests-lib.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir || ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    $_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
}

if ( ! $_tests_dir || ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    $_wp_phpunit = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';

    if ( file_exists( $_wp_phpunit . '/includes/functions.php' ) ) {
        $_tests_dir = $_wp_phpunit;
    } elseif ( file_exists( '/tmp/wordpress-tests-lib/includes/functions.php' ) ) {
        $_tests_dir = '/tmp/wordpress-tests-lib';
    }
}

if ( ! $_tests_dir || ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    fwrite(
        STDERR,
        "Could not locate the WordPress test library.\n" .
        "Run `composer install` (provides wp-phpunit), or set WP_TESTS_DIR to an installed suite.\n"
    );
    exit( 1 );
}

$_polyfills = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( ! $_polyfills ) {
    $_polyfills = dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills';
}
if ( file_exists( $_polyfills . '/phpunitpolyfills-autoload.php' ) ) {
    require_once $_polyfills . '/phpunitpolyfills-autoload.php';
}

require_once $_tests_dir . '/includes/functions.php';

function _td_manually_load_plugin() {
    require dirname( __DIR__ ) . '/thrivedesk.php';
}
tests_add_filter( 'muplugins_loaded', '_td_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
