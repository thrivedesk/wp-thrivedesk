<?php

/**
 * Plugin Name:         ThriveDesk
 * Description:         Live Chat, Help Desk & Knowledge Base plugin for WordPress
 * Plugin URI:          https://www.thrivedesk.com/?utm_source=wp-plugins&utm_campaign=plugin-uri&utm_medium=wp-dash
 * Tags:                live chat, helpdesk, free live chat, knowledge base, thrivedesk
 * Version:             1.0.4
 * Author:              ThriveDesk
 * Author URI:          https://profiles.wordpress.org/thrivedesk/
 * Text Domain:         thrivedesk
 * Domain Path:         languages
 *
 * Requires PHP:        5.5
 * Requires at least:   4.9
 * Tested up to:        6.1.1
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
use ThriveDesk\Assistants\Assistant;
use ThriveDesk\FluentCrmHooks;
use ThriveDesk\RestRoute;
use ThriveDesk\Conversations\Conversation;
use ThriveDesk\Services\PortalService;

// Exit if accessed directly.
if (! defined('ABSPATH'))
    exit;

// Includes vendor files.
require_once __DIR__ . '/vendor/autoload.php';

final class ThriveDesk
{
    /**
     * Plugin version
     *
     * @var string
     */
    public $version = '1.0.4';

    /**
     * The single instance of this class
     */
    private static $instance = null;

    /**
     * The API class
     */
    public $api = null;

    /**
     * The FluentCRM hooks class
     */
    public $hooks = null;

    /**
     * The REST route class
     */
    public $restroute = null;

    /**
     * The admin class
     */
    public $admin = null;

    /**
     * Construct ThriveDesk class.
     *
     * @since  0.0.1
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
     * @return object|ThriveDesk
     * @access public
     * @since  0.0.1
     */
    public static function instance() : object
    {
        if (! isset(self::$instance) && ! (self::$instance instanceof ThriveDesk)) {

            self::$instance = new self();

            self::$instance->api = Api::instance();

            self::$instance->hooks = FluentCrmHooks::instance();

            self::$instance->restroute = RestRoute::instance();

            if (is_admin()) {
                self::$instance->admin = Admin::instance();
            }

            Conversation::instance();
            Assistant::instance();
			PortalService::instance();
        }

        return self::$instance;
    }

    /**
     * Define the necessary constants.
     *
     * @return void
     * @since  0.0.1
     * @access private
     */
    private function define_constants() : void
    {
        $this->define('THRIVEDESK_VERSION', $this->version);
        $this->define('THRIVEDESK_FILE', __FILE__);
        $this->define('THRIVEDESK_DIR', dirname(__FILE__));
        $this->define('THRIVEDESK_INC_DIR', dirname(__FILE__) . '/includes');
        $this->define('THRIVEDESK_PLUGIN_ASSETS', plugins_url('assets', __FILE__));
        $this->define('THRIVEDESK_PLUGIN_ASSETS_PATH', plugin_dir_path(__FILE__) . 'assets');
        // Url with no ending /
        $this->define('THRIVEDESK_APP_URL', 'https://app.thrivedesk.com');
        $this->define('THRIVEDESK_API_URL', 'https://api.thrivedesk.com');
        $this->define('THRIVEDESK_DB_TABLE_CONVERSATION', 'td_conversations');
        $this->define('THRIVEDESK_DB_VERSION', 1.2);
        $this->define('OPTION_THRIVEDESK_DB_VERSION', 'td_db_version');
    }

    /**
     * Define constant if not already defined
     *
     * @param string      $name
     * @param string|bool $value
     *
     * @return void
     * @since 0.0.1
     */
    private function define($name, $value)
    {
        if (! defined($name)) {
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
