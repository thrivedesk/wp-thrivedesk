<?php
/**
 * Plugin Name:         Agentic Help Desk Plugin for WordPress - Live Chat, AI Chatbot & Ticketing - ThriveDesk
 * Description:         ThriveDesk is a help desk plugin for WordPress that brings live chat, AI chatbot, support ticketing, and a knowledge base into one place. Built for WooCommerce stores and eCommerce businesses. Resolve customer support tickets faster with shared inbox, automation, and AI-powered replies.
 * Plugin URI:          https://www.thrivedesk.com/?utm_source=wp-plugins&utm_campaign=plugin-uri&utm_medium=wp-dash
 * Tags:                live chat, helpdesk, free live chat, knowledge base, thrivedesk
 * Version:             2.7.0
 * Author:              ThriveDesk
 * Author URI:          https://profiles.wordpress.org/thrivedesk/
 * Text Domain:         thrivedesk
 * Domain Path:         /languages
 * License:             GPLv2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package ThriveDesk
 *
 * Requires PHP:        7.4
 * Requires at least:   4.9
 * Tested up to:        6.9
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
use ThriveDesk\Inboxes\Inbox;
use ThriveDesk\FluentCrmHooks;
use ThriveDesk\Portal\UserAccountPages;
use ThriveDesk\RestRoute;
use ThriveDesk\Conversations\Conversation;
use ThriveDesk\KnowledgeBase\KnowledgeBase;
use ThriveDesk\Services\OAuth\Connection as OAuthConnection;
use ThriveDesk\Services\PortalService;
use ThriveDeskDBMigrations\Scripts\MigrationScript;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Includes vendor files.
require_once __DIR__ . '/vendor/autoload.php';

// helpers.php registers hooks at file scope, so it has to load after WP is up.
// Not via Composer's eager files autoload: that runs at vendor/autoload time,
// which under PHPUnit is before WordPress exists.
require_once __DIR__ . '/includes/helper.php';

/**
 * Core plugin bootstrap: wires the plugin's constants and subsystems together.
 */
final class ThriveDesk {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	public $version = '2.7.0';

	/**
	 * The single instance of this class
	 *
	 * @var ThriveDesk|null
	 */
	private static $instance = null;

	/**
	 * The API class
	 *
	 * @var Api|null
	 */
	public $api = null;

	/**
	 * The FluentCRM hooks class
	 *
	 * @var FluentCrmHooks|null
	 */
	public $hooks = null;

	/**
	 * The REST route class
	 *
	 * @var RestRoute|null
	 */
	public $restroute = null;

	/**
	 * The admin class
	 *
	 * @var Admin|null
	 */
	public $admin = null;

	/**
	 * Construct ThriveDesk class.
	 *
	 * @since  0.0.1
	 * @access private
	 */
	private function __construct() {
		// Define constants.
		$this->define_constants();

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load the bundled translations.
	 *
	 * On `init` rather than at load time: since WordPress 6.7 a text domain
	 * loaded before the locale is settled triggers a "translation loading
	 * triggered too early" notice, and the strings it loads can be the wrong
	 * language for the current user.
	 *
	 * Translations installed from wordpress.org land in WP_LANG_DIR and load on
	 * their own. This is for the ones that ship inside the plugin, which is what
	 * Domain Path names and what nothing was reading.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'thrivedesk',
			false,
			dirname( plugin_basename( THRIVEDESK_FILE ) ) . '/languages'
		);
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
	public static function instance(): object {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof ThriveDesk ) ) {

			self::$instance = new self();

			self::$instance->api = Api::instance();

			self::$instance->hooks = FluentCrmHooks::instance();

			self::$instance->restroute = RestRoute::instance();

			if ( is_admin() ) {
				self::$instance->admin = Admin::instance();

				// admin-only: both legs of the connect flow are admin page loads.
				OAuthConnection::instance();
			}

			require_once THRIVEDESK_DIR . '/database/Scripts/MigrationScript.php';

			MigrationScript::instance();
			Conversation::instance();
			Assistant::instance();
			Inbox::instance();
			PortalService::instance();
			UserAccountPages::instance();
			KnowledgeBase::instance();
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
	private function define_constants(): void {
		$this->define( 'THRIVEDESK_VERSION', $this->version );
		$this->define( 'THRIVEDESK_FILE', __FILE__ );
		$this->define( 'THRIVEDESK_DIR', __DIR__ );
		$this->define( 'THRIVEDESK_INC_DIR', __DIR__ . '/includes' );
		$this->define( 'THRIVEDESK_PLUGIN_ASSETS', plugins_url( 'assets', __FILE__ ) );
		$this->define( 'THRIVEDESK_PLUGIN_ASSETS_PATH', plugin_dir_path( __FILE__ ) . 'assets' );
		// Host URLs are stored without a trailing slash.
		$this->define( 'THRIVEDESK_APP_URL', 'https://app.thrivedesk.com' );
		$this->define( 'THRIVEDESK_API_URL', 'https://api.thrivedesk.com' );
		$this->define( 'THRIVEDESK_KB_API_ENDPOINT', 'https://thrivedeskdocs.com' );
		$this->define( 'THRIVEDESK_ASSISTANT_URL', 'https://assistant.thrivedesk.com' );
		$this->define( 'THRIVEDESK_DB_TABLE_CONVERSATION', 'td_conversations' );
		$this->define( 'THRIVEDESK_DB_VERSION', '1.2' );
		$this->define( 'OPTION_THRIVEDESK_DB_VERSION', 'td_db_version' );
	}

	/**
	 * Define constant if not already defined
	 *
	 * @param string      $name  Constant name.
	 * @param string|bool $value Constant value.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}
}

/**
 * Return the singleton ThriveDesk instance, booting the plugin on first call.
 *
 * @return ThriveDesk
 */
function ThriveDesk() {
	return ThriveDesk::instance();
}

ThriveDesk();

// Lifecycle hooks live at file scope (not inside the is_admin()-gated Admin
// singleton) so they register in every context, including WP-CLI activation.
register_activation_hook( __FILE__, array( 'ThriveDesk\\Admin', 'add_option_for_welcome_page_redirection' ) );
register_activation_hook( __FILE__, array( 'ThriveDesk\\Admin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ThriveDesk\\Admin', 'deactivate' ) );
