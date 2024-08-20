<?php

use ThriveDesk\Assistants\Assistant;
use ThriveDesk\KnowledgeBase\KnowledgeBase;
use ThriveDesk\Services\PortalService;
use ThriveDesk\Plugins\WPPostSync;

$td_helpdesk_selected_option = get_td_helpdesk_options();
$td_selected_post_types      = $td_helpdesk_selected_option['td_helpdesk_post_types'] ?? [];
$td_selected_post_sync       = $td_helpdesk_selected_option['td_helpdesk_post_sync'] ?? [];
$td_assistants               = Assistant::assistants();
$td_knowledgebase            = KnowledgeBase::knowledgebase();
$td_api_key                  = isset($_GET['token']) ? $_GET['token'] : ($td_helpdesk_selected_option['td_helpdesk_api_key'] ?? '');
$td_user_account_pages       = get_option('td_user_account_pages');
$has_portal_access           = (new PortalService())->has_portal_access();
$wppostsync                  = WPPostSync::instance();

$show_api_key_alert  = empty($td_api_key) ? '' : 'hidden';
$show_portal         = empty($has_portal_access) ? 'hidden' : '';

$td_selected_user_account_pages = $td_helpdesk_selected_option['td_user_account_pages'] ?? [];
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
    'woocommerce' => 'Add to WooCommerce'
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
    <!-- assistant  -->
    <div class="space-y-1">
        <div class="td-card-heading">
            <div class="text-base font-bold"><?php _e('Live Chat Assistant', 'thrivedesk'); ?></div>
            <p><?php _e('Add live chat assistant to your website. To create your assistant click <a href="' . THRIVEDESK_APP_URL . '/chat/assistants" target="_blank">here</a>. And you can choose the routes where the assistant should not be visible.', 'thrivedesk'); ?></p>
        </div>
        <div class="td-card space-y-2">
            <?php if (!empty($td_assistants)) : ?>
                <div class="space-y-2">
                    <label class="font-medium text-black text-sm"><?php _e('Select Assistant', 'thrivedesk'); ?></label>
                    <select class="mt-1 bg-gray-50 border border-gray-300 rounded px-2 py-1 w-full max-w-full" id="td-assistants" <?php echo empty($td_api_key) ? 'disabled' : ''; ?>>
                        <option value=""><?php _e('Select an assistant', 'thrivedesk'); ?></option>
                        <?php foreach ($td_assistants as $assistant) : ?>
                            <option value="<?php echo $assistant['id']; ?>" <?php echo ($td_helpdesk_selected_option['td_helpdesk_assistant_id'] == $assistant['id']) ? 'selected' : ''; ?>>
                                <?php echo $assistant['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="space-y-2">
                        <label class="font-medium text-black text-sm"><?php _e('Exclude Routes', 'thrivedesk'); ?></label>
                        <select name="td_excluded_routes[]" id="td-excluded-routes" class="mt-1 bg-gray-50 border border-gray-300 rounded px-2 py-1 w-full max-w-full" multiple>
                            <?php
                            $selected_routes = $td_helpdesk_selected_option['td_assistant_route_list'] ?? [];
                            if (!is_array($selected_routes)) {
                                $selected_routes = [];
                            }
                            foreach ($routes as $route) : ?>
                                <option class="hover:text-blue-700" value="<?php echo esc_attr($route); ?>" <?php echo in_array($route, $selected_routes) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($route); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Guidance for selecting multiple options -->
                    <small class="text-gray-600 block mt-1">
                        <?php _e('Hold down the <strong>Ctrl</strong> (or <strong>Cmd</strong> on Mac) key to select multiple routes.', 'thrivedesk'); ?>
                    </small>
                
            <?php else : ?>
                <p class="text-lg flex flex-col items-center">
                    <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" color="#000" fill="none">
                            <path opacity=".4" d="M2 10h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M2 17h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            <path opacity=".4" d="M2 3h17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M19.6 18.6 22 21m-1.2-6.6a5.4 5.4 0 1 0-10.8 0 5.4 5.4 0 0 0 10.8 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg></span>
                    <span><?php _e('No Assistant found. Please <a href="' . THRIVEDESK_APP_URL . '/chat/assistants" target="_blank">create a new Assistant</a> and return at a later time.', 'thrivedesk') ?></span>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($wppostsync && $wppostsync->get_plugin_data('connected')) : ?>
        <!-- WP Post Sync  -->
        <div class="space-y-1">
            <div class="td-card-heading">
                <div class="text-base font-bold"><?php _e('WP Post Sync', 'thrivedesk'); ?></div>
                <p><?php _e('Sync your WordPress posts with ThriveDesk for faster support', 'thrivedesk'); ?></p>
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
                                        <?php _e('You need to install WordPress Post Sync app to get this feature', 'thrivedesk'); ?>
                                        <?php $nonce = wp_create_nonce('thrivedesk-plugin-action'); ?>
                                        <a data-target="tab-integrations" href="#integrations" class="btn-primary py-1 px-3">Connect Now</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- portal  -->
    <div class="space-y-1">
        <div class="td-card-heading flex items-center">
            <div class="flex-1 pr-4">
                <div class="text-base font-bold"><?php _e('Portal', 'thrivedesk'); ?></div>
                <p><?php _e('Integrate a help center directly into your website. Customers can easily create tickets, access the knowledge base, and much more.', 'thrivedesk'); ?></p>
            </div>
            <?php if($has_portal_access):?>
                <button id="thrivedesk_clear_cache_btn" class="flex items-center space-x-2 bg-white border py-2 px-4 rounded shadow-sm text-sm hover:bg-rose-50 hover:text-rose-500 ml-auto">
                    <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" color="#000" fill="none">
                            <path d="M19.518 11.302c.654-.667 1.197-1.221 1.57-1.72.392-.525.662-1.073.662-1.732s-.27-1.207-.662-1.732c-.372-.499-.915-1.053-1.568-1.72l-.816-.835c-.662-.676-1.21-1.238-1.705-1.623-.52-.406-1.07-.69-1.736-.69-.666 0-1.215.284-1.736.689-.494.385-1.044.946-1.705 1.622L9.325 6.11c-.194.198-.29.297-.29.42 0 .122.096.22.29.42l6.795 6.945c.202.206.303.309.429.309s.227-.103.429-.31l2.54-2.593Z" fill="currentColor" />
                            <path opacity=".4" d="M14.739 15.345c.193.198.29.297.29.42 0 .122-.097.22-.29.419l-1.794 1.833c-.556.569-.937.959-1.402 1.226-.27.154-.557.276-.856.361-.516.147-1.16.147-1.95.147-.788 0-1.432 0-1.948-.147a3.837 3.837 0 0 1-.856-.361c-.465-.267-.846-.657-1.402-1.226-.558-.57-1.274-1.302-1.603-1.726-.345-.445-.6-.907-.66-1.465a2.885 2.885 0 0 1 0-.626c.06-.558.315-1.02.66-1.465.33-.424.793-.899 1.352-1.47L7.086 8.4c.202-.206.302-.31.429-.31.126 0 .227.104.428.31l6.796 6.946Z" fill="currentColor" />
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M8.75 21.75a1 1 0 0 1 1-1h11a1 1 0 1 1 0 2h-11a1 1 0 0 1-1-1Z" fill="currentColor" />
                        </svg></span>
                    <span><?php _e('Clear portal cache', 'thrivedesk') ?></span>
                </button>
            <?php endif;?>
        </div>
        <div class="td-card">
            <div class="text-center text-base <?php echo $show_api_key_alert ?>" id="api_key_alert">
                <?php _e('Please insert or verify your ThriveDesk API key to use the Portal feature.', 'thrivedesk'); ?>
            </div>

            <div class="alert alert-danger text-center <?php echo ($show_portal == "hidden") ? '' : 'hidden' ?>" id="portal_feature_alert">
                <?php _e('Portal feature is available for Plus and upper plan. For plans details click', 'thrivedesk'); ?>
                <a class="text-blue-500" href="https://www.thrivedesk.com/pricing/" target="_blank"><?php _e('here', 'thrivedesk'); ?></a>.
            </div>

            <div class="<?php echo $show_portal ?>" id="td_portal">
                <div class="md:flex md:space-x-4">
                    <div class="space-y-4 flex-1">
                        <!-- ticket form page selection  -->
                        <div class="bg-gray-50 border p-4 rounded">
                            <label for="td_helpdesk_page_id" class="font-medium text-black text-base"><?php _e('Ticket Form Page', 'thrivedesk'); ?></label>
                            <div class="text-sm"><?php _e('Use any form plugin for ticket creation page. Learn how to create ticket form using any form plugin <a href="https://help.thrivedesk.com/en/wpportal#create-ticket-page" target="_blank">here</a>', 'thrivedesk') ?></div>
                            <select id="td_helpdesk_page_id" class="mt-3 bg-white border rounded px-2 py-1 w-2/3">
                                <option value=""> <?php _e('Select a page', 'thrivedesk'); ?> </option>
                                <?php foreach (get_pages() as $key => $page) : ?>
                                    <option value="<?php echo $page->ID; ?>" <?php echo (array_key_exists('td_helpdesk_page_id', $td_helpdesk_selected_option) && $td_helpdesk_selected_option['td_helpdesk_page_id'] == $page->ID) ? 'selected' : ''; ?>>
                                        <?php echo $page->post_title; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- search provider -->
                        <div class="bg-gray-50 border p-4 rounded">
                            <label for="td_helpdesk_post_types" class="font-medium text-black text-base"><?php _e('Search Provider', 'thrivedesk'); ?></label>
                            <div class="text-sm"><?php _e('When someone tries to create a ticket from the portal, they will be prompted to search first. You can choose to search from the ThriveDesk knowledge base, post types, or both.', 'thrivedesk'); ?></div>
                            <div class="text-sm mt-1"><?php _e('Having a well-documented knowledge base and blog posts can help decrease the number of tickets you receive.', 'thrivedesk'); ?></div>
                            <hr class="mt-3">
                            <div class="flex flex-col mt-3 space-y-3">
                                <label for="td_knowledgebase_slug" class="font-medium text-black text-sm"><?php _e('Knowledge Base ', 'thrivedesk'); ?></label>
                                <select id="td_knowledgebase_slug" class="bg-white border rounded px-2 py-1 w-2/3">
                                    <option value=""> <?php _e('Select knowledgebase', 'thrivedesk'); ?> </option>
                                    <?php foreach ($td_knowledgebase as $value) : ?>
                                        <option value="<?= $value['slug']; ?>" <?= (array_key_exists('td_knowledgebase_slug', $td_helpdesk_selected_option) && $td_helpdesk_selected_option['td_knowledgebase_slug'] == $value['slug']) ? 'selected' : ''; ?>>
                                            <?= $value['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="flex flex-col mt-3 space-y-3">
                                <label class="font-medium text-black text-sm"><?php _e('WordPress Post Types ', 'thrivedesk'); ?></label>
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
                                <label for="td_user_account_pages" class="font-medium text-black text-base"><?php _e('Add Support Tab', 'thrivedesk'); ?></label>
                                <div class="text-sm"><?php _e('You can add a Support tab to the WooCommerce and Easy Digital Downloads My Account page depending on the availability of the plugin', 'thrivedesk'); ?></div>
                                <div class="mt-3">
                                    <?php foreach ($td_user_account_pages as $key => $page) : ?>
                                        <div class="mb-1" <?php echo !$woo_plugin_installed ? 'title="You must install and activate WooCommerce plugin to use this feature"' : ''; ?>>
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
                            <div class="text-base font-semibold"><?php _e('Portal Shortcode', 'thrivedesk'); ?></div>
                            <code class="inline-block bg-green-200 rounded">[thrivedesk_portal]</code>
                            <p><?php _e('Utilize this shortcode on any page to transform it into a help center.', 'thrivedesk'); ?>.</p>
                            <p><?php _e('The portal is accessible only to logged-in users.', 'thrivedesk'); ?>.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- connection  -->
    <div class="space-y-1">
        <div class="td-card-heading">
            <div class="text-base font-bold"><?php _e('Connection Details', 'thrivedesk'); ?></div>
            <p><?php _e('Update your api token to change or update the connection to ThriveDesk.', 'thrivedesk'); ?></p>
        </div>
        <div class="td-card">
            <div class="space-y-2">
                <label for="td_helpdesk_api_key" class="block mb-2 text-sm font-medium text-gray-900"><?php _e('API Key', 'thrivedesk'); ?></label>
                <span>
                    <?php _e('Login to ThriveDesk app and get your API key from ', 'thrivedesk'); ?>
                    <a class="text-blue-500" href="<?php echo THRIVEDESK_APP_URL . '/settings/company/api-key'; ?>" target="_blank">
                        <?php _e('here', 'thrivedesk'); ?>
                    </a>
                </span>
                <div class="flex items-center api-key-preview">
                    <input class="truncate w-2/3 bg-gray-50" type="password" disabled value="<?php echo esc_attr($td_api_key); ?>" />
                    <span class="text-green-500 underline hover:text-green-600 px-2 cursor-pointer trigger">Update</span>
                </div>
                <div class="api-key-editable hidden">
                    <input type="password" id="td_helpdesk_api_key" name="td_helpdesk_api_key" value="<?php echo esc_attr($td_api_key); ?>" class="block p-2.5 w-full text-sm" />

                    <button type="button" class="btn btn-primary py-1.5 mt-3 bg-green-500 hover:bg-green-600" id="td-api-verification-btn">
                        <?php _e('Verify', 'thrivedesk'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" id="td_setting_btn_submit" class="btn btn-primary">
        <?php _e('Save', 'thrivedesk'); ?>
    </button>
</form>

<script>
    ! function(t, e, n) {
        function s() {
            var t = e.getElementsByTagName("script")[0],
                n = e.createElement("script");
            n.type = "text/javascript", n.async = !0, n.src = "https://assistant.thrivedesk.com/bootloader.js?" + Date.now(),
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
        name: '<?php echo $current_user->user_login; ?>',
        email: '<?php echo $current_user->user_email; ?>',
    })
</script>