<?php

/**
 * Plugin Name: ThriveDesk
 * Description: Customer Support & Helpdesk Software for WordPress Startups
 * Plugin URI:  https://thrivedesk.io/?utm_source=wp-plugins&utm_campaign=plugin-uri&utm_medium=wp-dash
 * Tags:        thrivedesk,
 * Version:     0.0.5
 * Author:      ThriveDesk
 * Author URI:  https://profiles.wordpress.org/thrivedesk/
 * Text Domain: thrivedesk
 * Domain Path: languages
 * 
 * Requires PHP      : 7.0.0
 * Requires at least : 4.9
 * Tested up to      : 5.5.3
 *
 * ThriveDesk is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * ThriveDesk is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

use ThriveDesk\Admin;
use ThriveDesk\Api;

// Exit if accessed directly.
if (!defined('ABSPATH'))  exit;

// Includes vendor files.
require_once __DIR__ . '/vendor/autoload.php';

final class ThriveDesk
{
    /**
     * Plugin version
     *
     * @var string
     */
    public $version = '0.0.5';

    /**
     * The single instance of this class
     */
    private static $instance = null;

    /**
     * Construct ThriveDesk class.
     *
     * @since 0.0.1
     * @access private
     */
    private function __construct()
    {
        // Define constants.
        $this->define_constants();
    }

    /**
     * Main ThriveDesk Instance.
     *
     * Ensures that only one instance of ThriveDesk exists in memory at any one
     * time. Also prevents needing to define globals all over the place.
     *
     * @since 0.0.1
     * @return object|ThriveDesk
     * @access public
     */
    public static function instance(): object
    {
        if (!isset(self::$instance) && !(self::$instance instanceof ThriveDesk)) {

            self::$instance = new self();

            self::$instance->api = Api::instance();

            if (is_admin()) {
                self::$instance->admin = Admin::instance();
            }
        }

        return self::$instance;
    }

    /**
     * Define the necessary constants.
     *
     * @since 0.0.1
     * @access private
     * @return void
     */
    private function define_constants(): void
    {
        $this->define('THRIVEDESK_VERSION', $this->version);
        $this->define('THRIVEDESK_FILE', __FILE__);
        $this->define('THRIVEDESK_DIR', dirname(__FILE__));
        $this->define('THRIVEDESK_INC_DIR', dirname(__FILE__) . '/includes');
        $this->define('THRIVEDESK_PLUGIN_ASSETS', plugins_url('assets', __FILE__));

        $this->define('THRIVEDESK_APP_URL', 'http://localhost:4200');
    }

    /**
     * Define constant if not already defined
     *
     * @since 0.0.1
     * @param string $name
     * @param string|bool $value
     * @return void
     */
    private function define($name, $value)
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}

// Initialize ThriveDesk.
function ThriveDesk()
{
    return ThriveDesk::instance();
}
ThriveDesk();