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


$td_assistants               = Assistant::assistants();
$td_inboxes                  = Inbox::inboxes();
$td_knowledgebase            = KnowledgeBase::knowledgebase();
// A ?token= only counts when it came back from an authorization this site
// started; see \ThriveDesk\Admin::connect_return_token(). Otherwise the key on
// file is the key.
$td_connect_token            = \ThriveDesk\Admin::connect_return_token();
$td_api_key                  = '' !== $td_connect_token ? $td_connect_token : ($td_helpdesk_selected_option['td_helpdesk_api_key'] ?? '');
$td_user_account_pages       = get_option('td_user_account_pages');
$has_portal_access           = (new PortalService())->has_portal_access();
$wppostsync                  = WPPostSync::instance();

// What the admin is shown in place of the key. Enough to recognise which key
// is on file, not enough to use it.
$td_api_key_preview  = '' === $td_api_key ? '' : substr($td_api_key, 0, 4) . str_repeat('*', 20);

$show_api_key_alert  = empty($td_api_key) ? '' : 'hidden';
$show_portal         = empty($has_portal_access) ? 'hidden' : '';

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
    <div id="td-panel-settings" hidden>
    <!-- inbox selection -->
    <div class="space-y-1" style="display:none;">
        <div class="td-card-heading">
            <div class="text-base font-bold"><?php esc_html_e('Select your inbox', 'thrivedesk'); ?></div>
            <p><?php esc_html_e('Choose which inbox tickets to show in your portal. This helps filter conversations based on your preferred inbox.', 'thrivedesk'); ?></p>
        </div>
        <div class="td-card space-y-2">
            <?php if (!empty($td_inboxes)) : 
                //dd($td_inboxes, $td_helpdesk_selected_option['td_helpdesk_inbox_id'] ?? 'X');
                ?>
                <div class="space-y-2">
                    <label class="font-medium text-black text-sm"><?php esc_html_e('Select Inbox', 'thrivedesk'); ?></label>
                    <select class="mt-1 bg-gray-50 border border-gray-300 rounded px-2 py-1 w-full max-w-full" id="td-inboxes" data-selected="<?php echo esc_attr($td_helpdesk_selected_option['td_helpdesk_inbox_id'] ?? ''); ?>" <?php echo empty($td_api_key) ? 'disabled' : ''; ?>>
                        <option value=""><?php esc_html_e('All inboxes', 'thrivedesk'); ?></option>
                        <?php foreach ($td_inboxes as $inbox) : ?>
                            <option value="<?php echo esc_attr($inbox['id']); ?>" <?php echo ($td_helpdesk_selected_option['td_helpdesk_inbox_id'] ?? '') == $inbox['id'] ? 'selected' : ''; ?>>
                                <?php echo esc_html($inbox['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else : ?>
                <p class="text-lg flex flex-col items-center">
                    <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" color="#000" fill="none">
                            <path d="M2 12C2 8.22876 2 6.34315 3.17157 5.17157C4.34315 4 6.22876 4 10 4H14C17.7712 4 19.6569 4 20.8284 5.17157C22 6.34315 22 8.22876 22 12C22 15.7712 22 17.6569 20.8284 18.8284C19.6569 20 17.7712 20 14 20H10C6.22876 20 4.34315 20 3.17157 18.8284C2 17.6569 2 15.7712 2 12Z" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M6 8L8.1589 9.79908C9.99553 11.3296 10.9139 12.0949 12 12.0949C13.0861 12.0949 14.0045 11.3296 15.8411 9.79908L18 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg></span>
                    <span><?php
                        /* translators: %1$s: opening link tag, %2$s: closing link tag */
                        printf(esc_html__('No inboxes found. Please %1$screate a new inbox%2$s and return at a later time.', 'thrivedesk'), '<a href="' . esc_url(THRIVEDESK_APP_URL . '/inboxes') . '" target="_blank">', '</a>'); ?></span>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($wppostsync && $wppostsync->get_plugin_data('connected')) : ?>
        <!-- WP Post Sync  -->
        <div class="space-y-1">
            <div class="td-card-heading">
                <div class="text-base font-bold"><?php esc_html_e('WP Post Sync', 'thrivedesk'); ?></div>
                <p><?php esc_html_e('Sync your WordPress posts with ThriveDesk for faster support', 'thrivedesk'); ?></p>
            </div>
            <div class="td-card">
                <div class="flex space-x-4" id="td_post_sync">
                    <div class="flex-1">
                        <div class="space-y-2">
                            <div class="flex items-center space-x-2">
                                <?php if ($wppostsync && $wppostsync->get_plugin_data('connected')) : ?>
                                    <?php foreach ($wp_post_sync_types as $post_sync) : ?>
                                        <div>
                                            <input class="td_helpdesk_post_sync" type="checkbox" name="td_helpdesk_post_sync[]" value="<?php echo esc_attr($post_sync); ?>" <?php echo in_array($post_sync, $td_selected_post_sync) ? 'checked' : ''; ?>>
                                            <label for="<?php echo esc_attr($post_sync); ?>"> <?php echo esc_html(ucfirst($post_sync)); ?> </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <div class="w-full text-center text-base tab-link">
                                        <?php esc_html_e('You need to install WordPress Post Sync app to get this feature', 'thrivedesk'); ?>
                                        <?php $nonce = wp_create_nonce('thrivedesk-plugin-action'); ?>
                                        <a data-target="tab-integrations" href="#integrations" class="inline-block py-1 px-3 btn bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white">
                                            <?php esc_html_e('Connect Now', 'thrivedesk'); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- connection  -->
    <div class="space-y-1">
        <div class="td-card-heading">
            <div class="text-base font-bold"><?php esc_html_e('Connection Details', 'thrivedesk'); ?></div>
            <p><?php esc_html_e('Update your api token to change or update the connection to ThriveDesk.', 'thrivedesk'); ?></p>
        </div>
        <div class="td-card">
            <div class="space-y-2">
                <label for="td_helpdesk_api_key" class="block mb-2 text-sm font-medium text-gray-900"><?php esc_html_e('API Key', 'thrivedesk'); ?></label>
                <span>
                    <?php esc_html_e('Login to ThriveDesk app and get your API key from ', 'thrivedesk'); ?>
                    <a class="text-blue-500" href="<?php echo esc_url(THRIVEDESK_APP_URL . '/settings/company/api-key'); ?>" target="_blank">
                        <?php esc_html_e('here', 'thrivedesk'); ?>
                    </a>
                </span>
                <?php
                // type="password" hides the key on screen and nowhere else: it
                // is still in view-source, in the DOM, in password managers, on
                // a screen share, and one selector away from any script on the
                // page. Only a preview is rendered, and the editable field is
                // left blank - submitting it empty means "unchanged".
                ?>
                <div class="flex items-center api-key-preview">
                    <input class="truncate w-2/3 bg-gray-50" type="text" disabled value="<?php echo esc_attr($td_api_key_preview); ?>" />
                    <span class="text-green-500 underline hover:text-green-600 px-2 cursor-pointer trigger"><?php esc_html_e('Update', 'thrivedesk'); ?></span>
                </div>
                <div class="api-key-editable hidden">
                    <input type="password" id="td_helpdesk_api_key" name="td_helpdesk_api_key" value="<?php echo esc_attr($td_connect_token); ?>" placeholder="<?php echo esc_attr($td_api_key_preview); ?>" autocomplete="off" class="block p-2.5 w-full text-sm" />

                    <button type="button" class="btn btn-primary py-1.5 mt-3 bg-green-500 hover:bg-green-600" id="td-api-verification-btn">
                        <?php esc_html_e('Verify', 'thrivedesk'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </div>

    <div id="td-panel-livechat" hidden>
    <div class="td-card space-y-6">

        <div class="flex items-start gap-4 flex-wrap">
            <div class="flex-1 min-w-[16rem]">
                <div class="text-base font-bold"><?php esc_html_e('Live Chat Assistant', 'thrivedesk'); ?></div>
                <p class="mt-1! mb-0! text-gray-500"><?php esc_html_e('Show a chat widget on your site so visitors can start a conversation without leaving the page.', 'thrivedesk'); ?></p>
            </div>
            <a class="td-toolbar__cta shrink-0" href="<?php echo esc_url(THRIVEDESK_APP_URL . '/chat/assistants'); ?>" target="_blank">
                <span><?php esc_html_e('Manage assistants', 'thrivedesk'); ?></span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" aria-hidden="true"><path d="M11.099 3c-3.65.007-5.56.096-6.781 1.318C3 5.636 3 7.757 3 12c0 4.242 0 6.364 1.318 7.682C5.636 21 7.757 21 11.998 21c4.243 0 6.364 0 7.682-1.318 1.22-1.221 1.31-3.133 1.317-6.782M20.556 3.496 11.05 13.06m9.507-9.563c-.494-.494-3.822-.448-4.525-.438m4.525.438c.494.495.448 3.827.438 4.531" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
        </div>

        <?php if (!empty($td_assistants)) : ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 pt-5 border-t border-slate-200">

                <div class="td-field">
                    <label for="td-assistants"><?php esc_html_e('Assistant', 'thrivedesk'); ?></label>
                    <select id="td-assistants" class="w-full max-w-full bg-white border border-slate-300! rounded px-2 py-1.5" <?php echo empty($td_api_key) ? 'disabled' : ''; ?>>
                        <option value=""><?php esc_html_e('Select an assistant', 'thrivedesk'); ?></option>
                        <?php foreach ($td_assistants as $assistant) : ?>
                            <option value="<?php echo esc_attr($assistant['id']); ?>" <?php echo ($td_helpdesk_selected_option['td_helpdesk_assistant_id'] == $assistant['id']) ? 'selected' : ''; ?>>
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
                    <div class="td-multiselect" data-td-multiselect>
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
    </div>

    <div id="td-panel-portal" hidden>
    <!-- portal  -->
    <div class="space-y-1">
        <div class="td-card-heading flex items-center">
            <div class="flex-1 pr-4">
                <div class="text-base font-bold"><?php esc_html_e('Portal', 'thrivedesk'); ?></div>
                <p><?php esc_html_e('Integrate a help center directly into your website. Customers can easily create tickets, access the knowledge base, and much more.', 'thrivedesk'); ?></p>
            </div>
            <?php if($has_portal_access):?>
                <button id="thrivedesk_clear_cache_btn" class="flex items-center space-x-2 bg-white border py-2 px-4 rounded shadow-sm text-sm hover:bg-rose-50 hover:text-rose-500 ml-auto">
                    <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" color="#000" fill="none">
                            <path d="M19.518 11.302c.654-.667 1.197-1.221 1.57-1.72.392-.525.662-1.073.662-1.732s-.27-1.207-.662-1.732c-.372-.499-.915-1.053-1.568-1.72l-.816-.835c-.662-.676-1.21-1.238-1.705-1.623-.52-.406-1.07-.69-1.736-.69-.666 0-1.215.284-1.736.689-.494.385-1.044.946-1.705 1.622L9.325 6.11c-.194.198-.29.297-.29.42 0 .122.096.22.29.42l6.795 6.945c.202.206.303.309.429.309s.227-.103.429-.31l2.54-2.593Z" fill="currentColor" />
                            <path opacity=".4" d="M14.739 15.345c.193.198.29.297.29.42 0 .122-.097.22-.29.419l-1.794 1.833c-.556.569-.937.959-1.402 1.226-.27.154-.557.276-.856.361-.516.147-1.16.147-1.95.147-.788 0-1.432 0-1.948-.147a3.837 3.837 0 0 1-.856-.361c-.465-.267-.846-.657-1.402-1.226-.558-.57-1.274-1.302-1.603-1.726-.345-.445-.6-.907-.66-1.465a2.885 2.885 0 0 1 0-.626c.06-.558.315-1.02.66-1.465.33-.424.793-.899 1.352-1.47L7.086 8.4c.202-.206.302-.31.429-.31.126 0 .227.104.428.31l6.796 6.946Z" fill="currentColor" />
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M8.75 21.75a1 1 0 0 1 1-1h11a1 1 0 1 1 0 2h-11a1 1 0 0 1-1-1Z" fill="currentColor" />
                        </svg></span>
                    <span><?php esc_html_e('Clear portal cache', 'thrivedesk') ?></span>
                </button>
            <?php endif;?>
        </div>
        <div class="td-card">
            <div class="text-center text-base <?php echo esc_attr($show_api_key_alert); ?>" id="api_key_alert">
                <?php esc_html_e('Please insert or verify your ThriveDesk API key to use the Portal feature.', 'thrivedesk'); ?>
            </div>

            <div class="alert alert-danger text-center <?php echo ($show_portal == "hidden") ? '' : 'hidden' ?>" id="portal_feature_alert">
                <?php esc_html_e('Portal feature is available for Plus and upper plan. For plans details click', 'thrivedesk'); ?>
                <a class="text-blue-500" href="https://www.thrivedesk.com/pricing/" target="_blank"><?php esc_html_e('here', 'thrivedesk'); ?></a>.
            </div>

            <div class="<?php echo esc_attr($show_portal); ?>" id="td_portal">
                <div class="md:flex md:space-x-4">
                    <div class="space-y-4 flex-1">
                        <!-- ticket form page selection  -->
	                        <div class="bg-gray-50 border p-4 rounded">
	                            <label for="td_helpdesk_page_id" class="font-medium text-black text-base"><?php esc_html_e('Ticket Submission Form Page', 'thrivedesk'); ?></label>
	                            <div class="text-sm"><?php echo wp_kses_post(__('Create a dedicated page with your ticket form using any form plugin, then select that exact page here. Do not select your general Support or Contact page unless the actual ticket form is embedded on it. Learn how to create the ticket form page <a href="https://help.thrivedesk.com/en/wpportal#create-ticket-page" target="_blank">here</a>.', 'thrivedesk')) ?></div>
	                            <div class="text-sm mt-2 px-3 py-2 rounded bg-blue-50 border border-blue-200 text-slate-700"><?php esc_html_e('Select the page that contains the embedded ticket form visitors will submit.', 'thrivedesk'); ?></div>
	                            <select id="td_helpdesk_page_id" class="mt-3 bg-white border rounded px-2 py-1 w-2/3">
	                                <option value=""> <?php esc_html_e('Select the page with your ticket form', 'thrivedesk'); ?> </option>
	                                <?php foreach (get_pages() as $key => $page) : ?>
	                                    <option value="<?php echo esc_attr($page->ID); ?>" <?php echo (array_key_exists('td_helpdesk_page_id', $td_helpdesk_selected_option) && $td_helpdesk_selected_option['td_helpdesk_page_id'] == $page->ID) ? 'selected' : ''; ?>>
	                                        <?php echo esc_html($page->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- search provider -->
                        <div class="bg-gray-50 border p-4 rounded">
                            <label for="td_helpdesk_post_types" class="font-medium text-black text-base"><?php esc_html_e('Search Provider', 'thrivedesk'); ?></label>
                            <div class="text-sm"><?php esc_html_e('When someone tries to create a ticket from the portal, they will be prompted to search first. You can choose to search from the ThriveDesk knowledge base, post types, or both.', 'thrivedesk'); ?></div>
                            <div class="text-sm mt-1"><?php esc_html_e('Having a well-documented knowledge base and blog posts can help decrease the number of tickets you receive.', 'thrivedesk'); ?></div>
                            <hr class="mt-3">
                            <div class="flex flex-col mt-3 space-y-3">
                                <label for="td_knowledgebase_slug" class="font-medium text-black text-sm"><?php esc_html_e('Knowledge Base ', 'thrivedesk'); ?></label>
                                <select id="td_knowledgebase_slug" class="bg-white border rounded px-2 py-1 w-2/3">
                                    <option value=""> <?php esc_html_e('Select knowledgebase', 'thrivedesk'); ?> </option>
                                    <?php foreach ($td_knowledgebase as $value) : ?>
                                        <option value="<?php echo esc_attr($value['slug']); ?>" <?php echo (array_key_exists('td_knowledgebase_slug', $td_helpdesk_selected_option) && $td_helpdesk_selected_option['td_knowledgebase_slug'] == $value['slug']) ? 'selected' : ''; ?>>
                                            <?php echo esc_html($value['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="flex flex-col mt-3 space-y-3">
                                <label class="font-medium text-black text-sm"><?php esc_html_e('WordPress Post Types ', 'thrivedesk'); ?></label>
                                <?php foreach ($knowledge_base_wp_post_types as $post_type) : ?>
                                    <div>
                                        <label for="<?php echo esc_attr($post_type); ?>">
                                            <input class="td_helpdesk_post_types" type="checkbox" id="<?php echo esc_attr($post_type); ?>" name="td_helpdesk_post_types[]" value="<?php echo esc_attr($post_type); ?>" <?php echo in_array($post_type, $td_selected_post_types) ? 'checked' : ''; ?>>
                                            <?php echo esc_html(ucfirst($post_type)); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <!-- add support tab to woo/edd page  -->
                        <?php if (!empty($td_user_account_pages)) : ?>
                            <div class="bg-gray-50 border p-4 rounded">
                                <label for="td_user_account_pages" class="font-medium text-black text-base"><?php esc_html_e('Add Support Tab', 'thrivedesk'); ?></label>
                                <div class="text-sm"><?php esc_html_e('You can add a Support tab to the WooCommerce and Easy Digital Downloads My Account page depending on the availability of the plugin', 'thrivedesk'); ?></div>
                                <div class="mt-3">
                                    <?php foreach ($td_user_account_pages as $key => $page) : ?>
                                        <div class="mb-1" <?php echo !$woo_plugin_installed ? 'title="' . esc_attr__('You must install and activate WooCommerce plugin to use this feature', 'thrivedesk') . '"' : ''; ?>>
                                            <input class="td_user_account_pages" type="checkbox" name="td_user_account_pages[]" value="<?php echo esc_attr($key); ?>" <?php echo in_array($key, $td_selected_user_account_pages) ? 'checked ' : ''; ?> <?php echo !$woo_plugin_installed ? 'disabled' : ''; ?>>
                                            <label for="<?php echo esc_attr($page); ?>"> <?php echo esc_html($page); ?> </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="md:w-64 mt-4 md:mt-0">
                        <div class="p-4 bg-green-50 border border-green-300 rounded space-y-2">
                            <div class="text-base font-semibold"><?php esc_html_e('Portal Shortcode', 'thrivedesk'); ?></div>
                            <code class="inline-block bg-green-200 rounded">[thrivedesk_portal]</code>
                            <p><?php esc_html_e('Utilize this shortcode on any page to transform it into a help center.', 'thrivedesk'); ?>.</p>
                            <p><?php esc_html_e('The portal is accessible only to logged-in users.', 'thrivedesk'); ?>.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>


    <button type="submit" id="td_setting_btn_submit" class="btn btn-primary">
        <?php esc_html_e('Save', 'thrivedesk'); ?>
    </button>
</form>

<script>
    ! function(t, e, n) {
        function s() {
            var t = e.getElementsByTagName("script")[0],
                n = e.createElement("script");
            n.type = "text/javascript", n.async = !0, n.src = "<?php echo esc_url_raw(THRIVEDESK_ASSISTANT_URL); ?>/bootloader.js?" + Date.now(),
                t.parentNode.insertBefore(n, t)
        }
        if (t.Assistant = n = function(e, n, s) {
                t.Assistant.readyQueue.push({
                    method: e,
                    options: n,
                    data: s
                })
            },
            n.readyQueue = [], "complete" === e.readyState) return s();
        t.attachEvent ? t.attachEvent("onload", s) : t.addEventListener("load", s, !1)
    }
    (window, document, window.Assistant || function() {}), window.Assistant("init", "966fdf96-802e-4bf7-8692-78e01b503819");
    Assistant('identify', {
        name: '<?php echo esc_js($current_user->user_login); ?>',
        email: '<?php echo esc_js($current_user->user_email); ?>',
    })
</script>
