<?php

/**
 * Plugin Name  : Thrive Desk
 * Description  : Thrive Desk description here.
 * Plugin URI   : https://thrivedesk.com/?utm_source=wp-plugins&utm_campaign=plugin-uri&utm_medium=wp-dash
 * Tags         : thrivedesk
 * Version      : 0.0.1
 * Author       : Thrive Desk
 * Author URI   : https://thriveDesk.com/?utm_source=wp-plugins&utm_campaign=author-uri&utm_medium=wp-dash
 * Text Domain  : thrivedesk
 * Domain Path  : languages
 * 
 * Requires PHP      : 7.0.0
 * Requires at least : 4.9
 * Tested up to      : 5.4
 */

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
    public $version = '0.0.1';

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

            // self::$instance->api = Api::instance();

            if (is_admin()) {
                // self::$instance->admin = Admin::instance();
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
        $this->define('TDESK_VERSION', $this->version);
        $this->define('TDESK_FILE', __FILE__);
        $this->define('TDESK_DIR', dirname(__FILE__));
        $this->define('TDESK_INC_DIR', dirname(__FILE__) . '/includes');
        $this->define('TDESK_PLUGIN_ASSETS', plugins_url('assets', __FILE__));
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
add_action('plugins_loaded', 'ThriveDesk');