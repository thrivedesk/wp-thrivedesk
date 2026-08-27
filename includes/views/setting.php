<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use ThriveDesk\Assistants\Assistant;
use ThriveDesk\Conversations\Conversation;

$assistant_settings = Assistant::get_assistant_settings();
$api_key = get_option('td_helpdesk_settings')['td_helpdesk_api_key'] ?? '';
/*
 * Not for display - the Overview card reads td_helpdesk_system_info, and this is
 * what fills it on the first admin visit after connecting. Dropping this call
 * along with the header that used to show it would leave the card blank until
 * something else happened to fetch it.
 */
if ( $api_key && ! get_option('td_helpdesk_system_info') ) {
    Conversation::get_system_info($api_key);
}
?>

<div class="thrivedesk">
    <div class="td-toolbar">
        <a href="https://www.thrivedesk.com/" target="_blank" class="shrink-0">
            <img class="w-32 block" src="<?php echo esc_url(THRIVEDESK_PLUGIN_ASSETS . "/images/thrivedesk.png"); ?>" alt="ThriveDesk">
        </a>
        <span class="py-0.5 px-2 bg-slate-100 text-slate-600 text-[11px] rounded-full whitespace-nowrap">
            <?php esc_html_e( 'Version', 'thrivedesk' ); ?> <?php echo esc_html(THRIVEDESK_VERSION); ?>
        </span>

        <nav class="ml-auto flex items-center gap-1">
            <a class="td-toolbar__link" href="https://help.thrivedesk.com/en" target="_blank"><?php esc_html_e( 'Help Center', 'thrivedesk' ); ?></a>
            <a class="td-toolbar__link" href="https://status.thrivedesk.com/" target="_blank"><?php esc_html_e( 'System Status', 'thrivedesk' ); ?></a>

            <?php // A button, not an <a href="#"> with an inline onclick: this opens a widget rather than going anywhere, and the handler lives in admin.js so it can check the widget actually loaded. ?>
            <button type="button" class="td-toolbar__link" data-td-assistant="contact"><?php esc_html_e( 'Support', 'thrivedesk' ); ?></button>

            <a class="td-toolbar__cta" href="https://www.thrivedesk.com/wordpress/" target="_blank">
                <span><?php esc_html_e( 'Visit ThriveDesk', 'thrivedesk' ); ?></span>
                <?php thrivedesk_view( 'icons/external' ); ?>
            </a>
        </nav>
    </div>

    <!-- body  -->
    <?php // Full width: the cards that used to sit in a right rail are the Overview tab now. ?>
    <div class="p-10">
        <div id="td-admin-app"></div>

        <?php thrivedesk_view( 'partials/settings' ); ?>

        <noscript>
            <style>[id^="td-panel-"] { display: block !important; }</style>
        </noscript>
    </div>
</div>
