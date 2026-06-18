<?php
/**
 * PHPUnit bootstrap for the ThriveDesk plugin.
 *
 * Loads the WordPress test framework from WP_TESTS_DIR (the path where the
 * dev environment installs the WP PHPUnit suite), then loads this plugin so
 * its hooks fire inside the test WordPress.
 */

$_tests_dir = getenv('WP_TESTS_DIR');
if (! $_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

$_phpunit_polyfills = dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
if (file_exists($_phpunit_polyfills)) {
    require_once $_phpunit_polyfills;
}

require_once $_tests_dir . '/includes/functions.php';

// Load the plugin before WordPress boots the test suite.
function _td_manually_load_plugin() {
    require dirname(__DIR__) . '/thrivedesk.php';
}
tests_add_filter('muplugins_loaded', '_td_manually_load_plugin');

require $_tests_dir . '/includes/bootstrap.php';
