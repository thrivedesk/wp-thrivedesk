<?php

$edd = ThriveDesk\Plugins\EDD::instance();
$woocommerce = ThriveDesk\Plugins\WooCommerce::instance();
$fluentcrm = ThriveDesk\Plugins\FluentCRM::instance();
$wppostsync = ThriveDesk\Plugins\WPPostSync::instance();
$autonami = ThriveDesk\Plugins\Autonami::instance();
// $smartpay = ThriveDesk\Plugins\SmartPay::instance();
$conversation = ThriveDesk\Plugins\Conversation::instance();

$plugins = [
    [
        'namespace'     => 'woocommerce',
        'name'          => __('WooCommerce', 'thrivedesk'),
        'description'   => __('Share purchase data, shipping details and license information realtime with ThriveDesk.'),
        'image'         => 'woocommerce.png',
        'category'      => 'ecommerce',
        'installed'     => $woocommerce->is_plugin_active(),
        'connected'     => $woocommerce->get_plugin_data('connected'),
    ],
    [
        'namespace'     => 'edd',
        'name'          => __('Easy Digital Downloads', 'thrivedesk'),
        'description'   => __('Share customer purchase data, subscription and license information realtime with ThriveDesk.'),
        'image'         => 'edd.png',
        'category'      => 'ecommerce',
        'installed'     => $edd->is_plugin_active(),
        'connected'     => $edd->get_plugin_data('connected'),
    ],
    [
        'namespace'     => 'fluentcrm',
        'name'          => __('FluentCRM', 'thrivedesk'),
        'description'   => __('Sync your contacts and tickets information with ThriveDesk.'),
        'image'         => 'fluentcrm.png',
        'category'      => 'crm',
        'installed'     => $fluentcrm->is_plugin_active(),
        'connected'     => $fluentcrm->get_plugin_data('connected'),
    ],
    [
        'namespace'     => 'wppostsync',
        'name'          => __('WordPress Post Sync', 'thrivedesk'),
        'description'   => __('Share your site post data of selected post types for faster support.'),
        'image'         => 'wppostsync.png',
        'category'      => 'WordPress',
        'installed'     => $wppostsync->is_plugin_active(),
        'connected'     => $wppostsync->get_plugin_data('connected'),
    ],
    [
        'namespace'     => 'autonami',
        'name'          => __('Autonami', 'thrivedesk'),
        'description'   => __('Share customer data with ThriveDesk.'),
        'image'         => 'autonami.png',
        'category'      => 'CRM',
        'installed'     => $autonami->is_plugin_active(),
        'connected'     => $autonami->get_plugin_data('connected'),
    ],
    // [
    //     'name'      => __('SmartPay', 'thrivedesk'),
    //     'namespace' => 'smartpay',
    //     'image'     => 'smartpay.png',
    //     'installed' => $smartpay->is_plugin_active(),
    //     'connected' => $smartpay->get_plugin_data('connected'),
    // ],
];

$nonce = wp_create_nonce('thrivedesk-plugin-action');
?>

<div class="wrap thrivedesk">
    <!-- header  -->
    <div class="bg-white -mt-2 -mx-5 shadow mb-6">
        <div class="flex items-center justify-between py-3 px-6 border-b">
            <img class="w-32" src="<?php echo THRIVEDESK_PLUGIN_ASSETS . "/images/thrivedesk.png"; ?>" alt="ThriveDesk Logo">
            <div>
                <a class="px-4 py-1.5 flex items-center space-x-1 border rounded border-blue-500 text-blue-500 hover:bg-blue-50" href="https://www.thrivedesk.com" target="_blank">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z" />
                        <path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z" />
                    </svg>
                    <span><?php _e('Visit Website', 'thrivedesk') ?></span>
                </a>
            </div>
        </div>
        <div class="pl-6 flex justify-between text-sm font-medium leading-5 text-gray-500 h-12">
            <div class="flex space-x-8 admin-tabs">
                <a data-target="tab-integrations" class="inline-flex items-center px-1 border-blue-600" href="#"><?php _e('Integrations', 'thrivedesk') ?></a>
                <a data-target="tab-settings" class="inline-flex items-center px-1 border-blue-600" href="#"><?php _e('HelpDesk Settings', 'thrivedesk') ?></a>
                <?php if ($wppostsync->get_plugin_data('connected')) : ?>
                    <a data-target="tab-post-types-sync" class="inline-flex items-center px-1" href="#"><?php _e('WP Post Sync', 'thrivedesk') ?></a>
                <?php endif; ?>
            </div>
            <div class="flex">
                <a class="inline-flex items-center px-4 space-x-1 bg-gray-100 hover:bg-blue-50" href="https://youtu.be/ODV2Hi2MabI" target="_blank">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-lightbulb" viewBox="0 0 16 16">
                        <path d="M2 6a6 6 0 1 1 10.174 4.31c-.203.196-.359.4-.453.619l-.762 1.769A.5.5 0 0 1 10.5 13a.5.5 0 0 1 0 1 .5.5 0 0 1 0 1l-.224.447a1 1 0 0 1-.894.553H6.618a1 1 0 0 1-.894-.553L5.5 15a.5.5 0 0 1 0-1 .5.5 0 0 1 0-1 .5.5 0 0 1-.46-.302l-.761-1.77a1.964 1.964 0 0 0-.453-.618A5.984 5.984 0 0 1 2 6zm6-5a5 5 0 0 0-3.479 8.592c.263.254.514.564.676.941L5.83 12h4.342l.632-1.467c.162-.377.413-.687.676-.941A5 5 0 0 0 8 1z" />
                    </svg>
                    <span>
                        <?php _e('How to connect with ThriveDesk', 'thrivedesk') ?>
                    </span>
                </a>
            </div>
        </div>
    </div>

    <!-- reposition error info from header -->
    <h1></h1>

    <!-- body  -->
    <div id="tab-content" class="px-1.5">
        <div class="tab-integrations">
            <div class="mb-4 text-lg"><?php _e('Integrations', 'thrivedesk') ?></div>
            <div class="sm:grid sm:grid-cols-3 lg:grid-cols-4 sm:gap-4">
                <?php foreach ($plugins as $plugin) : ?>
                    <div class="border rounded-md p-4 transition hover:shadow-lg bg-white">
                        <!-- title  -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <img class="w-12 h-12" src="<?php echo THRIVEDESK_PLUGIN_ASSETS . '/images/' . sanitize_file_name($plugin['image']); ?>" />
                                <div class="font-medium text-base"><?php echo esc_html($plugin['name']); ?></div>
                            </div>
                            <?php if ($plugin['connected']) : ?>
                                <span class="p-1 rounded-full bg-green-100 text-green-500" title=<?php _e('Connected', 'thrivedesk') ?>>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z" />
                                    </svg>
                                </span>

                            <?php elseif (!$plugin['installed']) : ?>
                                <span class="p-1 rounded-full bg-red-100 text-red-500" title="<?php _e('Plugin not installed or not activated yet', 'thrivedesk') ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-lg" viewBox="0 0 16 16">
                                        <path d="M7.005 3.1a1 1 0 1 1 1.99 0l-.388 6.35a.61.61 0 0 1-1.214 0L7.005 3.1ZM7 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0Z" />
                                    </svg>
                                </span>
                            <?php endif; ?>
                        </div>
                        <!-- description  -->
                        <div class="text-gray-500 text-xs my-3">
                            <?php echo esc_html($plugin['description']); ?>
                        </div>

                        <!-- meta  -->
                        <div class="flex items-center justify-between relative">
                            <span class="uppercase text-xs text-gray-400"><?php echo esc_html($plugin['category']) ?></span>

                            <div>
                                <?php if ($plugin['connected']) : ?>
                                    <button data-plugin="<?php echo esc_attr($plugin['namespace']); ?>" data-connected="1" data-nonce="<?php echo esc_attr($nonce); ?>" class="connect inline-flex items-center space-x-1 py-1.5 pl-2 pr-3 rounded-full bg-red-100 text-red-500 focus:outline-none focus:ring-2 focus:ring-red-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="w-4 h-4" viewBox="0 0 16 16">
                                            <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z" />
                                        </svg>
                                        <span><?php _e('Disconnect', 'thrivedesk') ?></span>
                                    </button>
                                <?php else : ?>
                                    <button data-plugin="<?php echo esc_attr($plugin['namespace']); ?>" data-connected="0" data-nonce="<?php echo esc_attr($nonce); ?>" class="connect inline-flex items-center space-x-2 py-1.5 px-3 rounded-full bg-gray-200 text-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-600 <?php echo !$plugin['installed'] ? 'opacity-50 cursor-not-allowed' : '' ?>" <?php echo !$plugin['installed'] ? 'disabled' : '' ?>>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="w-3 h-3" viewBox="0 0 16 16">
                                            <path d="M0 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2h2a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2H2a2 2 0 0 1-2-2V2zm5 10v2a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1h-2v5a2 2 0 0 1-2 2H5zm6-8V2a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h2V6a2 2 0 0 1 2-2h5z" />
                                        </svg>
                                        <span><?php _e('Connect', 'thrivedesk') ?></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="hidden tab-settings">
            <div class="rounded-lg shadow-md sm:rounded-lg bg-white border">
                <div class="px-6 py-4">
                    <h1 class="pb-3 text-left text-lg font-extrabold border-b">HelpDesk Settings</h1>
                    <?php $td_helpdesk_selected_option = $conversation->getSelectedHelpdeskOptions(); ?>
                    <?php $td_selected_post_types = $td_helpdesk_selected_option['td_helpdesk_post_types'] ?? []; ?>
                    <div class="w-full text-sm text-left py-5">
                        <div class="flex items-center justify-center">
                            <div class="max-w-md w-full space-y-8">
                                <form class="mt-8 space-y-6" id="td_helpdesk_form" action="#" method="POST">
                                    <div class="mb-6">
                                        <label for="td_helpdesk_api_key" class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-400">API key</label>
                                        <input id="td_helpdesk_api_key" type="text" name="td_helpdesk_api_key"
                                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm
                                               rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2
                                               .5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400
                                               dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                               value="<?php echo esc_attr($td_helpdesk_selected_option['td_helpdesk_api_key']) ?? ''; ?>"
                                               required />
                                    </div>
                                    <div class="mb-6">
                                        <label for="td_form_page_id" class="block mb-2 text-sm font-medium
                                        text-gray-900 dark:text-gray-400">Select your Page where you will redirect on create new Ticket page</label>
                                        <select id="td_form_page_id" class="bg-gray-50 border border-gray-300
                                        text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500
                                        block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600
                                        dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" required >
                                            <option value=""> Please choose a page</option>
                                            <?php foreach (get_pages()  as $key => $page) : ?>
                                                <option value="<?php echo $page->ID; ?>" <?php echo $td_helpdesk_selected_option && $td_helpdesk_selected_option['td_form_page_id'] == $page->ID ? 'selected' : '' ?> >
                                                    <?php echo $page->post_title; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-6">
                                        <label for="td_helpdesk_post_types" class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-400">Post types to search</label>
                                        <?php
                                        $wp_post_types = array_filter(get_post_types(array('public' => true, 'show_in_rest' => true)), function ($type) {
                                            return $type !== 'attachment';
                                        });

                                        foreach ($wp_post_types as $post_type) :
                                            ?>
                                            <div>
                                                <input class="td_helpdesk_post_types" type="checkbox" name="td_helpdesk_post_types[]"
                                                       value="<?php echo esc_attr($post_type); ?>" id="<?php echo
                                                esc_attr($post_type); ?>" <?php echo in_array($post_type,
                                                    $td_selected_post_types) ? 'checked' : '';  ?>>
                                                <label for="<?php echo esc_attr($post_type); ?>"> <?php echo esc_html(ucfirst($post_type)); ?> </label>
                                            </div>
                                        <?php
                                        endforeach;
                                        ?>
                                    </div>
                                    <div class="mb-6">
                                        <label for="td_helpdesk_form_style" class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-400">Choose your style (for default, left it blank)</label>
                                        <select id="td_helpdesk_form_style" class="bg-gray-50 border border-gray-300
                                        text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" required>
                                            <option value="">Please choose a style</option>
                                            <option value="no_style" <?php echo $td_helpdesk_selected_option['td_form_style'] == 'no_style' ? 'selected' : ''; ?>>No
                                                Style</option>
                                            <option value="modern" <?php echo $td_helpdesk_selected_option['td_form_style'] == 'modern' ? 'selected' : ''; ?>>Modern</option>
                                        </select>
                                    </div>

                                    <button type="submit" id="td_setting_btn_submit" class="text-white bg-blue-700
                                    hover:bg-blue-800
                                    focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-full sm:w-auto px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">Save</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($wppostsync && $wppostsync->get_plugin_data('connected')) : ?>
            <div class="hidden tab-post-types-sync">
                <div>
                    <?php
                    $post_type_sync_nonce = $_POST['post_type_sync_nonce'] ?? null;

                    if ($post_type_sync_nonce && wp_verify_nonce($post_type_sync_nonce, 'thrivedesk-post-type-sync-nonce')) {
                        $post_type_sync_options =  array_map('sanitize_text_field', $_POST['post_types']) ?? [];

                        update_option('thrivedesk_post_type_sync_option', $post_type_sync_options);
                    } else {
                        $post_type_sync_options = get_option('thrivedesk_post_type_sync_option', []);
                    }
                    ?>
                    <p class="mb-4 text-lg">
                        <?php _e('Post types to index.', 'thrivedesk'); ?>
                    </p>

                    <form action="#" method="POST">
                        <input type="hidden" name="post_type_sync_nonce" value="<?php echo wp_create_nonce('thrivedesk-post-type-sync-nonce') ?>">
                        <?php
                        $wp_post_types = array_filter(get_post_types(array('public' => true, 'show_in_rest' => true)), function ($type) {
                            return $type !== 'attachment';
                        });

                        foreach ($wp_post_types as $post_type) :
                        ?>
                            <div>
                                <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($post_type); ?>" id="<?php echo esc_attr($post_type); ?>" <?php echo in_array($post_type, $post_type_sync_options) ? 'checked' : '';  ?>>
                                <label for="<?php echo esc_attr($post_type); ?>"> <?php echo esc_html(ucfirst($post_type)); ?> </label>
                            </div>
                        <?php
                        endforeach;
                        ?>
                        <div class="submit">
                            <?php submit_button(); ?>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
