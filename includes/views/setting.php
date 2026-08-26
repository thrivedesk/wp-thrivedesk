<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use ThriveDesk\Assistants\Assistant;
use ThriveDesk\Conversations\Conversation;

$assistant_settings = Assistant::get_assistant_settings();
$api_key = get_option('td_helpdesk_settings')['td_helpdesk_api_key'] ?? '';
/*
 * Not for display - the sidebar card reads td_helpdesk_system_info, and this is
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
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" aria-hidden="true"><path d="M11.099 3c-3.65.007-5.56.096-6.781 1.318C3 5.636 3 7.757 3 12c0 4.242 0 6.364 1.318 7.682C5.636 21 7.757 21 11.998 21c4.243 0 6.364 0 7.682-1.318 1.22-1.221 1.31-3.133 1.317-6.782M20.556 3.496 11.05 13.06m9.507-9.563c-.494-.494-3.822-.448-4.525-.438m4.525.438c.494.495.448 3.827.438 4.531" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
        </nav>
    </div>

    <!-- body  -->
    <div class="p-10 grid grid-cols-1 md:grid-cols-4 gap-12">
        <div class="col-span-3 space-y-6">
            <?php
            /*
             * The React app mounts here and owns the tabs. Integrations is
             * rendered entirely by it; the settings form below is still server
             * rendered and gets adopted into the Settings tab on mount.
             *
             * `hidden` matters: without it the form paints unstyled in the page
             * flow for a frame before React moves it. It is cleared by
             * HostedPanel once the node is in place, so a JavaScript failure
             * leaves the form reachable rather than invisible.
             */
            ?>
            <div id="td-admin-app"></div>

            <?php thrivedesk_view( 'partials/settings' ); ?>

            <noscript>
                <style>[id^="td-panel-"] { display: block !important; }</style>
            </noscript>
        </div>
        <div class="col-span-1">
            <!-- include the sidebar -->
            <?php thrivedesk_view( 'partials/sidebar' ); ?>
        </div>
    </div>
</div>
