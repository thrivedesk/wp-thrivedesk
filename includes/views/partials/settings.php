<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use ThriveDesk\Assistants\Assistant;
use ThriveDesk\Inboxes\Inbox;
use ThriveDesk\KnowledgeBase\KnowledgeBase;
use ThriveDesk\Services\PortalService;
use ThriveDesk\Plugins\WPPostSync;

$td_helpdesk_selected_option = get_td_helpdesk_settings();
$td_selected_post_types      = (array) ($td_helpdesk_selected_option['td_helpdesk_post_types'] ?? []);
$td_selected_post_sync       = (array) ($td_helpdesk_selected_option['td_helpdesk_post_sync'] ?? []);


/*
 * Live Chat and Portal are both windows onto a ThriveDesk account; without one
 * they render as a screen of empty selects. See partials/connect-empty.
 *
 * The flag is read before the four lookups below rather than after, because
 * every one of them is an HTTP request to ThriveDesk and none of them can
 * succeed without a working key. Fetching anyway meant an install that had not
 * connected yet - or whose key had just been rejected - paid for a round of
 * failing requests on every admin page load, and the failures are not cached.
 */
$td_connected                = thrivedesk_is_connected();

// Everything on the search card describes what happens on the way to a ticket
// form, so none of it means anything until there is one. See the
// #td-search-card gate in admin.js, which keeps this in step as the select
// changes without a save.
$td_has_ticket_page          = !empty($td_helpdesk_selected_option['td_helpdesk_page_id']);

$td_assistants               = $td_connected ? Assistant::assistants() : [];
$td_inboxes                  = $td_connected ? Inbox::inboxes() : [];
$td_knowledgebase            = $td_connected ? KnowledgeBase::knowledgebase() : [];
// A ?token= only counts when it came back from an authorization this site
// started; see \ThriveDesk\Admin::connect_return_token(). Otherwise the key on
// file is the key.
$td_connect_token            = \ThriveDesk\Admin::connect_return_token();
$td_api_key                  = '' !== $td_connect_token ? $td_connect_token : ($td_helpdesk_selected_option['td_helpdesk_api_key'] ?? '');
$td_user_account_pages       = get_option('td_user_account_pages');
$has_portal_access           = $td_connected && (new PortalService())->has_portal_access();
$wppostsync                  = WPPostSync::instance();

// What the admin is shown in place of the key. Enough to recognise which key
// is on file, not enough to use it.
$td_api_key_preview  = '' === $td_api_key ? '' : substr($td_api_key, 0, 4) . str_repeat('*', 20);


$td_selected_user_account_pages = (array) ($td_helpdesk_selected_option['td_user_account_pages'] ?? []);
$td_helpdesk_selected_option['td_knowledgebase_url'] = THRIVEDESK_KB_API_ENDPOINT;
update_option('td_helpdesk_settings', $td_helpdesk_selected_option);

$wp_post_sync_types = array_filter(get_post_types(array(
    'public'       => true,
    'show_in_rest' => true
)), function ($type) {
    return $type !== 'attachment';
});

$knowledge_base_wp_post_types = array_filter(get_post_types(['public' => true]), function ($type) {
    return $type !== 'attachment';
});
$woo_plugin_installed = defined('WC_VERSION');
$td_user_account_pages = array(
    'woocommerce' => __('Add to WooCommerce', 'thrivedesk')
);

// Fetch all published pages
$pages = get_pages(array(
    'post_status' => 'publish',
));

// Collect routes into an array
$routes = array();

foreach ($pages as $page) {
    $routes[$page->ID] = get_permalink($page->ID);
}

// Get current user
$current_user = wp_get_current_user();
?>

<form class="space-y-6" id="td_helpdesk_form" action="#" method="POST">
    <?php wp_nonce_field('thrivedesk-nonce', 'td_nonce'); ?>
    <?php
    /*
     * Panels, not sections. The React app adopts each of these into a tab on
     * mount, which is why they carry ids and start hidden.
     *
     * They stay inside the form so a save still submits from one place, and
     * they can leave it without consequence when adopted: the submit handler
     * reads every field by id rather than serialising the form, so DOM
     * containment is not what makes saving work.
     */
    ?>
    <?php // Overview leads: what this site is connected to, and the key that connects it. ?>
    <div id="td-panel-overview" hidden>
        <?php thrivedesk_view( 'partials/overview', [
            'td_api_key_preview' => $td_api_key_preview,
            'td_connect_token'   => $td_connect_token,
        ] ); ?>
    </div>


    <div id="td-panel-livechat" hidden>
    <?php if ( ! $td_connected ) : ?>
        <?php thrivedesk_view( 'partials/connect-empty', [
            'td_empty_title' => __( 'Live Chat needs a ThriveDesk account', 'thrivedesk' ),
            'td_empty_text'  => __( 'The chat widget is configured from the assistants in your workspace, so there is nothing to choose from until this site is connected.', 'thrivedesk' ),
        ] ); ?>
    <?php else : ?>
    <?php // The settings are a handful of controls; the preview is the thing worth the room. ?>
    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-6 items-start">
    <div class="td-card space-y-6">

        <div class="flex items-start gap-4 flex-wrap">
            <div class="flex-1 min-w-[16rem]">
                <div class="text-base font-bold"><?php esc_html_e('Live Chat Assistant', 'thrivedesk'); ?></div>
                <p class="mt-1! mb-0! text-gray-500"><?php esc_html_e('Show a chat widget on your site so visitors can start a conversation without leaving the page.', 'thrivedesk'); ?></p>
            </div>
            <a class="td-toolbar__cta shrink-0" href="<?php echo esc_url(THRIVEDESK_APP_URL . '/chat/assistants'); ?>" target="_blank">
                <span><?php esc_html_e('Manage assistants', 'thrivedesk'); ?></span>
                <?php thrivedesk_view( 'icons/external' ); ?>
            </a>
        </div>

        <?php if (!empty($td_assistants)) : ?>
            <div class="space-y-5 pt-5 border-t border-slate-200">

                <div class="td-field">
                    <label for="td-assistants"><?php esc_html_e('Assistant', 'thrivedesk'); ?></label>
                    <select id="td-assistants" class="w-full max-w-full bg-white border border-slate-300! rounded px-2 py-1.5" <?php echo empty($td_api_key) ? 'disabled' : ''; ?>>
                        <option value=""><?php esc_html_e('Select an assistant', 'thrivedesk'); ?></option>
                        <?php foreach ($td_assistants as $assistant) : ?>
                            <option value="<?php echo esc_attr($assistant['id']); ?>" <?php echo (($td_helpdesk_selected_option['td_helpdesk_assistant_id'] ?? '') == $assistant['id']) ? 'selected' : ''; ?>>
                                <?php echo esc_html($assistant['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="td-field-help"><?php esc_html_e('Which assistant this site loads.', 'thrivedesk'); ?></p>
                </div>

                <div class="td-field">
                    <label for="td-excluded-routes"><?php esc_html_e('Hide on these pages', 'thrivedesk'); ?></label>
                    <?php
                    /*
                     * Still a multiple <select>, and still the source of truth:
                     * the save handler reads $('#td-excluded-routes').val() and
                     * relies on getting an array back. admin.js builds a
                     * dropdown over it and writes selections straight onto these
                     * options, so the contract never changes - and if that script
                     * fails to run, what is left is a working list box rather
                     * than a control with no UI.
                     */
                    ?>
                    <div
                        class="td-multiselect"
                        data-td-multiselect
                        data-td-empty="<?php esc_attr_e( 'Shown on every page', 'thrivedesk' ); ?>"
                        data-td-many="<?php
                            /* translators: %d: how many pages the widget is hidden on. */
                            esc_attr_e( '%d pages hidden', 'thrivedesk' );
                        ?>"
                    >
                        <select name="td_excluded_routes[]" id="td-excluded-routes" size="6" multiple class="td-multiselect__source w-full max-w-full bg-white border border-slate-300! rounded px-2 py-1.5">
                            <?php
                            $selected_routes = (array)( $td_helpdesk_selected_option['td_assistant_route_list'] ?? []);

                            /*
                             * The value stays the permalink: Assistant::should_render()
                             * compares it against the current URL, so changing it would
                             * stop every saved exclusion matching. Only the label changes.
                             *
                             * The path rides along in a data attribute because two pages
                             * can share a title, and "Support" twice with nothing to tell
                             * them apart is worse than the URLs were.
                             */
                            foreach ($routes as $route_id => $route) :
                                $route_title = get_the_title($route_id);
                                $route_path  = wp_parse_url($route, PHP_URL_PATH);
                                ?>
                                <option
                                    value="<?php echo esc_attr($route); ?>"
                                    data-td-path="<?php echo esc_attr($route_path ? $route_path : $route); ?>"
                                    <?php echo in_array($route, $selected_routes) ? 'selected' : ''; ?>>
                                    <?php echo esc_html('' !== $route_title ? $route_title : $route); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="td-field-help">
                        <?php esc_html_e('The chat widget shows on every page of your site by default. Pick any pages it should stay off - checkout, account pages, or anywhere a chat bubble would get in the way. Leave empty to show it everywhere.', 'thrivedesk'); ?>
                    </p>
                </div>

            </div>
        <?php else : ?>
            <?php // An empty state with the way out of it, rather than a sentence explaining that nothing is here. ?>
            <div class="flex flex-col items-center text-center gap-3 py-10 border-t border-slate-200">
                <span class="text-slate-300">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="40" height="40" fill="none" aria-hidden="true">
                        <path d="M12 21c4.97 0 9-3.582 9-8s-4.03-8-9-8-9 3.582-9 8c0 1.6.53 3.09 1.44 4.34L3.5 21l4.03-1.2A10.2 10.2 0 0 0 12 21Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <path d="M8.5 12h.01M12 12h.01M15.5 12h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <div class="text-base font-semibold text-slate-700"><?php esc_html_e('No assistant yet', 'thrivedesk'); ?></div>
                <p class="m-0! text-gray-500 max-w-sm"><?php esc_html_e('Create one in ThriveDesk, then come back here to choose which assistant this site loads.', 'thrivedesk'); ?></p>
                <a class="btn btn-primary text-white! mt-1" href="<?php echo esc_url(THRIVEDESK_APP_URL . '/chat/assistants'); ?>" target="_blank">
                    <?php esc_html_e('Create an assistant', 'thrivedesk'); ?>
                </a>
            </div>
        <?php endif; ?>

    </div>

        <?php
        /*
         * The preview runs in an iframe, not on this page. The admin screen has
         * already called Assistant("init") with ThriveDesk's own support widget
         * - that is what the toolbar Support link opens - and the bootloader
         * keeps one queue per window, so a second init here would fight it. An
         * iframe gets its own window, which is also what makes the widget sit
         * inside the box rather than floating over the whole screen.
         */
        ?>
        <?php // Not a card: the preview is a surface, and a white box with a shadow
              // around it was one frame too many. ?>
        <div>

            <div
                class="td-assistant-preview"
                data-td-assistant-preview
                data-bootloader="<?php echo esc_url( THRIVEDESK_ASSISTANT_URL . '/bootloader.js' ); ?>"
                data-name="<?php echo esc_attr( $current_user->display_name ); ?>"
                data-email="<?php echo esc_attr( $current_user->user_email ); ?>"
            >
                <span class="td-assistant-preview__pill"><?php esc_html_e( 'Preview', 'thrivedesk' ); ?></span>
                <p class="td-assistant-preview__empty"><?php esc_html_e( 'Choose an assistant to preview it.', 'thrivedesk' ); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    </div>

    <div id="td-panel-portal" hidden>
    <?php if ( ! $td_connected ) : ?>
        <?php thrivedesk_view( 'partials/connect-empty', [
            'td_empty_title' => __( 'Portal needs a ThriveDesk account', 'thrivedesk' ),
            'td_empty_text'  => __( 'Portal serves your inboxes and knowledge base on your own site, so it has nothing to serve until this site is connected.', 'thrivedesk' ),
        ] ); ?>
    <?php else : ?>
    <?php // The inbox select is hidden but must stay in the DOM: the save handler reads it by id. ?>
    <div style="display:none;">
        <select id="td-inboxes" data-selected="<?php echo esc_attr($td_helpdesk_selected_option['td_helpdesk_inbox_id'] ?? ''); ?>">
            <option value=""><?php esc_html_e('All inboxes', 'thrivedesk'); ?></option>
            <?php foreach ($td_inboxes as $inbox) : ?>
                <option value="<?php echo esc_attr($inbox['id']); ?>" <?php echo ($td_helpdesk_selected_option['td_helpdesk_inbox_id'] ?? '') == $inbox['id'] ? 'selected' : ''; ?>>
                    <?php echo esc_html($inbox['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="space-y-6">

        <?php if (!$has_portal_access) : ?>
            <?php // The one thing worth saying before any of the settings: none of them do anything on this plan. ?>
            <div class="td-notice td-notice--warn" id="portal_feature_alert">
                <span class="td-notice__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none"><path d="M12 8.5v4.5M12 16.5h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10.24 4.03 3.1 16.4C2.4 17.6 3.27 19.1 4.66 19.1h14.28c1.39 0 2.26-1.5 1.56-2.7L13.36 4.03c-.7-1.2-2.43-1.2-3.12 0Z" stroke="currentColor" stroke-width="1.5"/></svg>
                </span>
                <div>
                    <?php esc_html_e('Portal is part of the Plus plan and above. The settings below are saved, but nothing is served until the plan covers it.', 'thrivedesk'); ?>
                    <a class="td-inline-link" href="https://www.thrivedesk.com/pricing/" target="_blank"><?php esc_html_e('Compare plans', 'thrivedesk'); ?></a>
                </div>
            </div>
        <?php endif; ?>

        <?php // Where the portal lives, and how to put it there. ?>
        <div class="td-card space-y-6" id="td_portal">

            <div class="flex items-start gap-4 flex-wrap">
                <div class="flex-1 min-w-[16rem]">
                    <div class="text-base font-bold"><?php esc_html_e('Portal', 'thrivedesk'); ?></div>
                    <p class="mt-1! mb-0! text-gray-500"><?php esc_html_e('A help centre on your own site: customers open tickets, read the knowledge base and follow their replies without leaving your domain.', 'thrivedesk'); ?></p>
                </div>
                <?php if ($has_portal_access) : ?>
                    <button type="button" id="thrivedesk_clear_cache_btn" class="btn-ghost shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" fill="none" aria-hidden="true"><path d="M3.5 7.5h17M9 7.5V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v1.5M6 7.5l.8 11a2.5 2.5 0 0 0 2.5 2.3h5.4a2.5 2.5 0 0 0 2.5-2.3l.8-11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        <span><?php esc_html_e('Clear portal cache', 'thrivedesk'); ?></span>
                    </button>
                <?php endif; ?>
            </div>

            <?php // The form on the left, and what to paste where beside it. ?>
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] gap-6 items-start pt-5 border-t border-slate-200">

                <div class="space-y-5">

                    <div class="td-field">
                        <label for="td_helpdesk_page_id"><?php esc_html_e('Ticket creation form', 'thrivedesk'); ?></label>
                        <select id="td_helpdesk_page_id" class="w-full bg-white border border-slate-300! rounded px-2 py-1.5">
                            <option value=""><?php esc_html_e('Select the page with your ticket form', 'thrivedesk'); ?></option>
                            <?php foreach (get_pages() as $page) : ?>
                                <option value="<?php echo esc_attr($page->ID); ?>" <?php echo (array_key_exists('td_helpdesk_page_id', $td_helpdesk_selected_option) && $td_helpdesk_selected_option['td_helpdesk_page_id'] == $page->ID) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="td-field-help">
                            <?php esc_html_e('You can use your existing form plugin, or any free one, to build the ticket page. All you have to do is set a ThriveDesk inbox address as the form\'s submission email.', 'thrivedesk'); ?>
                            <a class="td-inline-link" href="https://help.thrivedesk.com/en/wpportal#create-ticket-page" target="_blank"><?php esc_html_e('How to build one', 'thrivedesk'); ?></a>
                        </p>
                    </div>

                <?php $td_form_plugins = thrivedesk_detected_form_plugins(); ?>
                <?php if ($td_form_plugins) : ?>
                    <?php
                    /*
                     * The step above says "use your existing form plugin". Most sites
                     * have one, so rather than leave that as an instruction, what is
                     * here is named - with the button that opens its builder wherever
                     * we know where that lives.
                     *
                     * All of them, not the best one: a site with three form plugins
                     * has three because someone chose each of them, and picking a
                     * winner would hide the one they actually build with.
                     */
                    ?>
                    <?php // Leads the grid rather than following it: it is the instruction the tiles carry out, and the field above has already said what the form is for. ?>
                    <p class="td-field-help"><?php esc_html_e('Build it with one of these, then select its page above.', 'thrivedesk'); ?></p>

                    <?php // Two abreast once there is more than one; a lone tile in a two column grid is a tile and a hole. ?>
                    <div class="grid grid-cols-1 <?php echo count($td_form_plugins) > 1 ? 'sm:grid-cols-2' : ''; ?> gap-3 mt-3">
                        <?php foreach ($td_form_plugins as $td_form_plugin) : ?>
                            <div class="td-plugin">
                                <?php
                                // The letter shows through when none of the candidate
                                // icons load - a plugin whose icon wordpress.org does
                                // not have, or a site that cannot reach it at all. See
                                // the img error handler in admin.js, which walks
                                // data-td-icons.
                                ?>
                                <span class="td-plugin__logo" data-letter="<?php echo esc_attr(mb_strtoupper(mb_substr($td_form_plugin['name'], 0, 1))); ?>">
                                    <img
                                        class="td-plugin__icon"
                                        src="<?php echo esc_url($td_form_plugin['icons'][0]); ?>"
                                        data-td-icons="<?php echo esc_attr(wp_json_encode(array_slice($td_form_plugin['icons'], 1))); ?>"
                                        alt=""
                                        width="40"
                                        height="40"
                                    >
                                </span>
            
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-slate-800 truncate" title="<?php echo esc_attr($td_form_plugin['name']); ?>"><?php echo esc_html($td_form_plugin['name']); ?></div>
                                    <div class="text-[12px] <?php echo $td_form_plugin['active'] ? 'text-green-600' : 'text-gray-500'; ?>">
                                        <?php echo $td_form_plugin['active']
                                            ? esc_html__('Active', 'thrivedesk')
                                            : esc_html__('Not active', 'thrivedesk'); ?>
                                    </div>
                                </div>
            
                                <?php if (!$td_form_plugin['active']) : ?>
                                    <a class="btn-ghost btn-sm shrink-0" href="<?php echo esc_url(admin_url('plugins.php?s=' . rawurlencode($td_form_plugin['slug']) . '&plugin_status=all')); ?>">
                                        <?php esc_html_e('Activate', 'thrivedesk'); ?>
                                    </a>
                                <?php elseif ($td_form_plugin['new_form_url']) : ?>
                                    <?php
                                    /*
                                     * A new tab, because building a form is a long
                                     * detour away from a page in the middle of being
                                     * set up. The external icon says so before it is
                                     * clicked - the same one every other new-tab link
                                     * on this screen carries.
                                     */
                                    ?>
                                    <a class="btn-solid btn-sm shrink-0" href="<?php echo esc_url(admin_url($td_form_plugin['new_form_url'])); ?>" target="_blank" rel="noopener">
                                        <span><?php esc_html_e('Create a form', 'thrivedesk'); ?></span>
                                        <?php thrivedesk_view('icons/external'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
            
                    <?php endif; ?>

                </div>

                <?php // Notes, not fields: nothing in this column is set, it is read and pasted somewhere else. ?>
                <div class="space-y-4">

                <aside class="td-info">
                    <div class="td-info__title">
                        <span class="td-info__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none"><circle cx="12" cy="12" r="9.25" stroke="currentColor" stroke-width="1.5"/><path d="M12 16.5v-5M12 8h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        </span>
                        <?php esc_html_e('Portal shortcode', 'thrivedesk'); ?>
                    </div>

                    <div class="td-ip-row mt-3">
                        <code class="td-key">[thrivedesk_portal]</code>
                        <button
                            type="button"
                            class="td-copy"
                            data-td-copy="[thrivedesk_portal]"
                            title="<?php esc_attr_e('Copy the portal shortcode', 'thrivedesk'); ?>"
                            aria-label="<?php esc_attr_e('Copy the portal shortcode', 'thrivedesk'); ?>"
                        >
                            <span class="td-copy-idle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" fill="none" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2.5" stroke="currentColor" stroke-width="1.5"/><path d="M15 6V5.5A2.5 2.5 0 0 0 12.5 3h-6A2.5 2.5 0 0 0 4 5.5v6A2.5 2.5 0 0 0 6.5 14H7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                            <span class="td-copy-done"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" fill="none" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </button>
                    </div>

                    <p class="mt-3! mb-0! text-[12px] text-slate-600"><?php esc_html_e('Put this on any page to turn it into the help centre. Only logged-in visitors can see it.', 'thrivedesk'); ?></p>
                </aside>

                <?php
                /*
                 * The address a ticket form has to submit to, beside the shortcode
                 * because both are things read here and pasted somewhere else. The
                 * mailbox connected to the inbox where there is one, the
                 * ThriveDesk-hosted address otherwise - see thrivedesk_inbox_address().
                 */
                $td_addressed = array_filter($td_inboxes, static function ($td_inbox) {
                    return '' !== thrivedesk_inbox_address((array) $td_inbox);
                });
                ?>
                <?php if ($td_addressed) : ?>
                    <aside class="td-info td-info--plain">
                        <div class="td-info__title">
                            <span class="td-info__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none"><path d="M2 12c0-3.771 0-5.657 1.172-6.828C4.343 4 6.229 4 10 4h4c3.771 0 5.657 0 6.828 1.172C22 6.343 22 8.229 22 12c0 3.771 0 5.657-1.172 6.828C19.657 20 17.771 20 14 20h-4c-3.771 0-5.657 0-6.828-1.172C2 17.657 2 15.771 2 12Z" stroke="currentColor" stroke-width="1.5"/><path d="m6 8 2.159 1.799c1.836 1.53 2.755 2.296 3.841 2.296 1.086 0 2.005-.765 3.841-2.296L18 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                            </span>
                            <?php esc_html_e('Your inbox addresses', 'thrivedesk'); ?>
                        </div>
                
                        <?php // Name over address rather than beside it: this column is narrow and an address is long. ?>
                        <ul class="mt-3! mb-0! p-0! list-none space-y-2">
                            <?php foreach ($td_addressed as $td_inbox) : ?>
                                <?php
                                $td_inbox         = (array) $td_inbox;
                                $td_inbox_address = thrivedesk_inbox_address($td_inbox);
                                ?>
                                <li>
                                    <div class="text-[12px] font-medium text-slate-700"><?php echo esc_html($td_inbox['name'] ?? ''); ?></div>
                                    <div class="td-ip-row">
                                        <code class="td-key"><?php echo esc_html($td_inbox_address); ?></code>
                                        <button
                                            type="button"
                                            class="td-copy"
                                            data-td-copy="<?php echo esc_attr($td_inbox_address); ?>"
                                            title="<?php echo esc_attr(
                                                /* translators: %s: an email address */
                                                sprintf(__('Copy %s', 'thrivedesk'), $td_inbox_address)
                                            ); ?>"
                                            aria-label="<?php echo esc_attr(
                                                /* translators: %s: an email address */
                                                sprintf(__('Copy %s', 'thrivedesk'), $td_inbox_address)
                                            ); ?>"
                                        >
                                            <span class="td-copy-idle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" fill="none" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2.5" stroke="currentColor" stroke-width="1.5"/><path d="M15 6V5.5A2.5 2.5 0 0 0 12.5 3h-6A2.5 2.5 0 0 0 4 5.5v6A2.5 2.5 0 0 0 6.5 14H7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                                            <span class="td-copy-done"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" fill="none" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                                        </button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                
                        <p class="mt-3! mb-0! text-[12px] text-slate-600"><?php esc_html_e('Point your form at one of these and its submissions become conversations.', 'thrivedesk'); ?></p>
                    </aside>
                <?php endif; ?>

                </div>

            </div>

            <?php // Announces a copy to screen readers; the icon swap alone is silent. ?>
            <span id="td-copy-status" class="sr-only" role="status" aria-live="polite"></span>
        </div>

        <?php // What the portal searches before it lets anyone open a ticket. ?>
        <div class="td-card space-y-6 td-gated<?php echo $td_has_ticket_page ? '' : ' is-locked'; ?>" id="td-search-card">

            <div>
                <div class="text-base font-bold"><?php esc_html_e('Connect with Help Center', 'thrivedesk'); ?></div>
                <p class="mt-1! mb-0! text-gray-500"><?php esc_html_e('Anyone opening a ticket is asked to search first. The better your Help Center is stocked, the fewer tickets reach you.', 'thrivedesk'); ?></p>
            </div>

            <?php
            /*
             * Said once, at the top, rather than as a tooltip on each control
             * someone has already tried to use. Hidden rather than absent so
             * the gate can be opened without a reload - see admin.js.
             */
            ?>
            <p class="td-gated__hint"<?php echo $td_has_ticket_page ? ' hidden' : ''; ?>>
                <?php esc_html_e('Select a ticket creation form above to set this up.', 'thrivedesk'); ?>
            </p>

            <?php // The two selects on the left, what they add up to on the right. ?>
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] gap-6 items-start pt-5 border-t border-slate-200">

                <div class="space-y-5">
                    <div class="td-field">
                        <label for="td_knowledgebase_slug"><?php esc_html_e('Help Center', 'thrivedesk'); ?></label>
                        <select id="td_knowledgebase_slug" class="w-full max-w-md bg-white border border-slate-300! rounded px-2 py-1.5" <?php disabled(!$td_has_ticket_page); ?>>
                            <option value=""><?php esc_html_e('Do not search a Help Center', 'thrivedesk'); ?></option>
                            <?php foreach ($td_knowledgebase as $value) : ?>
                                <option value="<?php echo esc_attr($value['slug']); ?>" <?php echo (array_key_exists('td_knowledgebase_slug', $td_helpdesk_selected_option) && $td_helpdesk_selected_option['td_knowledgebase_slug'] == $value['slug']) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($value['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="td-field-help"><?php esc_html_e('Which ThriveDesk Help Center the portal searches.', 'thrivedesk'); ?></p>
                    </div>

                    <div class="td-field">
                        <label for="td_helpdesk_post_types"><?php esc_html_e('WordPress content', 'thrivedesk'); ?></label>
                        <?php
                        /*
                         * Same shape as "Hide on these pages": a real multiple <select> that
                         * stays the source of truth, with admin.js building a dropdown over
                         * it. The save handler reads $('#td_helpdesk_post_types').val() and
                         * relies on getting an array back, so the contract never changes -
                         * and if that script fails to run, what is left is a working list box
                         * rather than a control with no UI.
                         */
                        ?>
                        <div
                            class="td-multiselect max-w-md"
                            data-td-multiselect
                            data-td-empty="<?php esc_attr_e( 'Help Center only', 'thrivedesk' ); ?>"
                            data-td-many="<?php
                                /* translators: %d: how many post types are searched alongside the Help Center. */
                                esc_attr_e( '%d content types', 'thrivedesk' );
                            ?>"
                        >
                            <select name="td_helpdesk_post_types[]" id="td_helpdesk_post_types" size="6" multiple class="td-multiselect__source w-full max-w-md bg-white border border-slate-300! rounded px-2 py-1.5" <?php disabled(!$td_has_ticket_page); ?>>
                                <?php foreach ($knowledge_base_wp_post_types as $post_type) : ?>
                                    <?php
                                    // The label the site itself uses - "Posts", "Products" -
                                    // rather than the slug with a capital letter on it.
                                    $td_type_object = get_post_type_object($post_type);
                                    $td_type_label  = $td_type_object && !empty($td_type_object->labels->name)
                                        ? $td_type_object->labels->name
                                        : ucfirst($post_type);
                                    ?>
                                    <option value="<?php echo esc_attr($post_type); ?>" <?php selected(in_array($post_type, $td_selected_post_types, true)); ?>>
                                        <?php echo esc_html($td_type_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="td-field-help"><?php esc_html_e('Post types on this site to search alongside the Help Center. Leave empty to search the Help Center only.', 'thrivedesk'); ?></p>
                    </div>

                    <div class="td-field">
                        <label class="td-check<?php echo $td_has_ticket_page ? '' : ' is-disabled'; ?>" for="td_helpdesk_search_required">
                            <input type="checkbox" id="td_helpdesk_search_required" name="td_helpdesk_search_required" value="1" <?php checked(!empty($td_helpdesk_selected_option['td_helpdesk_search_required'])); ?> <?php disabled(!$td_has_ticket_page); ?>>
                            <span><?php esc_html_e('Make searching compulsory', 'thrivedesk'); ?></span>
                        </label>
                        <p class="td-field-help"><?php esc_html_e('Hides the new ticket button until a search has run. People who find their answer never open the ticket; people who do not still can.', 'thrivedesk'); ?></p>
                    </div>
                </div>

                <?php
                /*
                 * Deliberately not a .td-field, so the gate does not dim it:
                 * while this card is locked the explanation is the only part
                 * of it still worth reading.
                 */
                ?>
                <aside class="td-info">
                    <div class="td-info__title">
                        <span class="td-info__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none"><circle cx="12" cy="12" r="9.25" stroke="currentColor" stroke-width="1.5"/><path d="M12 16.5v-5M12 8h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        </span>
                        <?php esc_html_e('What this does', 'thrivedesk'); ?>
                    </div>

                    <p class="mt-3! mb-0! text-[12px] text-slate-600">
                        <?php esc_html_e('With a Help Center and some post types chosen, anyone opening a ticket on the portal is asked to search first, and ThriveDesk shows the answer if it is already published in either place.', 'thrivedesk'); ?>
                    </p>
                    <p class="mt-2! mb-0! text-[12px] text-slate-600">
                        <?php esc_html_e('The ones who find it never open the ticket, which is the point: fewer of the same question, asked and answered again.', 'thrivedesk'); ?>
                    </p>
                </aside>

            </div>
        </div>

        <?php if (!empty($td_user_account_pages) || ($wppostsync && $wppostsync->get_plugin_data('connected'))) : ?>
            <div class="td-card space-y-6">

                <div>
                    <div class="text-base font-bold"><?php esc_html_e('Elsewhere on this site', 'thrivedesk'); ?></div>
                    <p class="mt-1! mb-0! text-gray-500"><?php esc_html_e('What ThriveDesk adds to pages it does not own.', 'thrivedesk'); ?></p>
                </div>

                <div class="space-y-5 pt-5 border-t border-slate-200">

                    <?php if (!empty($td_user_account_pages)) : ?>
                        <div class="td-field">
                            <span class="td-field__label"><?php esc_html_e('Support tab', 'thrivedesk'); ?></span>
                            <div class="space-y-2">
                                <?php foreach ($td_user_account_pages as $key => $page) : ?>
                                    <label
                                        class="td-check<?php echo $woo_plugin_installed ? '' : ' is-disabled'; ?>"
                                        for="td-account-<?php echo esc_attr($key); ?>"
                                        <?php echo !$woo_plugin_installed ? 'title="' . esc_attr__('You must install and activate WooCommerce plugin to use this feature', 'thrivedesk') . '"' : ''; ?>
                                    >
                                        <input class="td_user_account_pages" type="checkbox" id="td-account-<?php echo esc_attr($key); ?>" name="td_user_account_pages[]" value="<?php echo esc_attr($key); ?>" <?php echo in_array($key, $td_selected_user_account_pages) ? 'checked ' : ''; ?> <?php echo !$woo_plugin_installed ? 'disabled' : ''; ?>>
                                        <span><?php echo esc_html($page); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="td-field-help"><?php esc_html_e('Adds a Support tab to the My Account page, so customers reach their tickets where they already are.', 'thrivedesk'); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($wppostsync && $wppostsync->get_plugin_data('connected')) : ?>
                        <div class="td-field" id="td_post_sync">
                            <span class="td-field__label"><?php esc_html_e('Sync posts to ThriveDesk', 'thrivedesk'); ?></span>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2">
                                <?php foreach ($wp_post_sync_types as $post_sync) : ?>
                                    <label class="td-check" for="td-sync-<?php echo esc_attr($post_sync); ?>">
                                        <input class="td_helpdesk_post_sync" type="checkbox" id="td-sync-<?php echo esc_attr($post_sync); ?>" name="td_helpdesk_post_sync[]" value="<?php echo esc_attr($post_sync); ?>" <?php echo in_array($post_sync, $td_selected_post_sync) ? 'checked' : ''; ?>>
                                        <span><?php echo esc_html(ucfirst($post_sync)); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="td-field-help"><?php esc_html_e('Agents can search these from inside a conversation without leaving ThriveDesk.', 'thrivedesk'); ?></p>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>
    </div>


</form>
