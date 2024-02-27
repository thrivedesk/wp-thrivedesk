<?php
use ThriveDesk\Assistants\Assistant;
use ThriveDesk\Services\PortalService;
use ThriveDesk\Plugins\WPPostSync;

    $td_helpdesk_selected_option = get_td_helpdesk_options();
    $td_selected_post_types      = $td_helpdesk_selected_option['td_helpdesk_post_types'] ?? [];
    $td_selected_post_sync       = $td_helpdesk_selected_option['td_helpdesk_post_sync'] ?? [];
    $td_assistants               = Assistant::assistants();
    $td_api_key                  = isset($_GET['token']) ? $_GET['token'] : ($td_helpdesk_selected_option['td_helpdesk_api_key'] ?? '');
    $td_user_account_pages          = get_option( 'td_user_account_pages' );
    $td_selected_user_account_pages      = $td_helpdesk_selected_option['td_user_account_pages'] ?? [];
    $has_portal_access           = ( new PortalService() )->has_portal_access() ? true : false;
    $wppostsync                  = WPPostSync::instance();

    $show_api_key_alert  = (count($td_assistants) == 0) ? '' : 'hidden';
    $show_portal         = (!$has_portal_access ||empty($td_api_key))  ? 'hidden' : '';
    $show_portal_warning = (empty($td_api_key) || (!$has_portal_access && count($td_assistants) == 0) ||($has_portal_access && count($td_assistants) != 0))  ? 'hidden' : '';

    $td_user_account_pages = array(
            'woocommerce' => 'Add to WooCommerce'
    );

    $wp_post_sync_types = array_filter( get_post_types( array(
        'public'       => true,
        'show_in_rest' => true
    ) ), function ( $type ) {
        return $type !== 'attachment';
    } );

    $knowledge_base_wp_post_types = array_filter( get_post_types( array(
        'public'       => true
    ) ), function ( $type ) {
        return $type !== 'attachment';
    } );

    $woo_plugin_installed = defined('WC_VERSION');;
?>

<form class="space-y-6" id="td_helpdesk_form" action="#" method="POST">
    <!-- assistant  -->
    <div class="space-y-1">
        <div class="td-card-heading">
            <div class="text-base font-bold"><?php _e('Live Chat Assistant', 'thrivedesk'); ?></div>
            <p><?php _e('Add live chat assistant to your website. To create your assistant click <a href="'. THRIVEDESK_APP_URL . '/assistants" target="_blank">here</a>', 'thrivedesk'); ?></p>
        </div>
        <div class="td-card">
            <?php if( count($td_assistants) != 0 ) :?>
            <div class="space-y-2">
                <label class="font-medium text-black text-sm"><?php _e( 'Select your Live Chat Assistant', 'thrivedesk' ); ?></label>
                <select class="mt-1 bg-gray-50 border border-gray-300 rounded px-2 py-1 w-full max-w-full" id="td-assistants" <?php echo empty($td_api_key) ? 'disabled' : ''; ?>> <?php _e( 'Select an assistant', 'thrivedesk' ); ?> </option>
                    <option value=""><?php _e( 'Select an assistant', 'thrivedesk' ); ?></option>
                    <?php foreach ( $td_assistants as $assistant ) : ?>
                        <option value="<?php echo $assistant['id']; ?>" <?php echo $td_helpdesk_selected_option['td_helpdesk_assistant_id'] == $assistant['id'] ? 'selected' : ''; ?>>
                            <?php echo $assistant['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <p class="text-lg flex flex-col items-center">
                    <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" color="#000" fill="none"><path opacity=".4" d="M2 10h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 17h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path opacity=".4" d="M2 3h17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M19.6 18.6 22 21m-1.2-6.6a5.4 5.4 0 1 0-10.8 0 5.4 5.4 0 0 0 10.8 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <span><?php _e('No Assistant found. Please <a href="https://app.thrivedesk.com/chat/assistants" target="_blank">create a new Assistant</a> and return at a later time.', 'thrivedesk')?></span>
                </p>
            <?php endif;?>
        </div>
    </div>
    
    <?php if ($wppostsync && $wppostsync->get_plugin_data('connected')) : ?>
    <!-- WP Post Sync  -->
    <div class="space-y-1">
        <div class="td-card-heading">
            <div class="text-base font-bold"><?php _e( 'WP Post Sync', 'thrivedesk' ); ?></div>
            <p><?php _e( 'Sync your WordPress posts with ThriveDesk for faster support','thrivedesk'); ?></p>
        </div>
        <div class="td-card">
            <div class="flex space-x-4" id="td_post_sync">
                <div class="flex-1">
                    <div class="space-y-2">
                        <div class="flex items-center space-x-2">
                            <?php if ($wppostsync && $wppostsync->get_plugin_data('connected')) : ?>
                                <?php foreach ( $wp_post_sync_types as $post_sync ) : ?>
                                    <div>
                                        <input class="td_helpdesk_post_sync" type="checkbox" 
                                        name="td_helpdesk_post_sync[]" value="<?php echo esc_attr($post_sync); ?>" <?php echo in_array($post_sync,$td_selected_post_sync) ? 'checked' : ''; ?>>
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
        <div class="td-card-heading">
            <div class="text-base font-bold"><?php _e( 'Portal', 'thrivedesk' ); ?></div>
            <p><?php _e('Integrate a help center directly into your website. Customers can easily create tickets, access the knowledge base, and much more.','thrivedesk'); ?></p>
        </div>
        <div class="td-card">
            <div class="text-center text-base <?php echo ($show_api_key_alert) ?>" id="api_key_alert">
                <?php _e('Please insert or verify your ThriveDesk API key ☝️ to use the Portal feature inside your site.', 'thrivedesk'); ?>
            </div>

            <div class="alert alert-danger text-center <?php echo ($show_portal_warning) ?>" id="portal_feature_alert">
                <?php _e('Portal feature is available for Plus and upper plan. For plans details click', 'thrivedesk'); ?>
                <a class="text-blue-500" href="https://app.thrivedesk.com/billing/plans" target="_blank"><?php _e('here', 'thrivedesk'); ?></a>.
            </div>

            <div class="md:flex md:space-x-4 <?php echo ($show_portal) ?>" id="td_portal">
                <div class="space-y-4 flex-1">
                    <div class="bg-gray-50 border p-4 rounded">
                        <label for="td_helpdesk_page_id" class="font-medium text-black text-base"><?php _e('Ticket Form Page', 'thrivedesk'); ?></label>
                        <div class="text-sm"><?php _e('Use any form plugin for ticket creation page. Learn how to create ticket form using any form plugin <a href="https://help.thrivedesk.com/en/wpportal#create-ticket-page" target="_blank">here</a>', 'thrivedesk')?></div>
                        <select id="td_helpdesk_page_id" class="mt-3 bg-white border rounded px-2 py-1 w-2/3">
                            <option value=""> <?php _e( 'Select a page', 'thrivedesk' ); ?> </option>
                            <?php foreach ( get_pages() as $key => $page ) : ?>
                                <option value="<?php echo $page->ID; ?>" <?php echo array_key_exists( 'td_helpdesk_page_id',
                                $td_helpdesk_selected_option) && $td_helpdesk_selected_option['td_helpdesk_page_id'] == $page->ID ? 'selected' : '' ?>>
                                    <?php echo $page->post_title; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bg-gray-50 border p-4 rounded">
                        <label for="td_helpdesk_post_types" class="font-medium text-black text-base"><?php _e( 'Search Provider', 'thrivedesk' ); ?></label>
                        <div class="text-sm"><?php _e('Select the post types that are likely to contain answers to most customer inquiries. When this feature is enabled, customers will be prompted to search before opening a ticket, which can help reduce the number of tickets.', 'thrivedesk')?></div>
                        <div class="flex flex-col flex-wrap mt-3">
                            <?php foreach ( $knowledge_base_wp_post_types as $post_type ) : ?>
                                <div>
                                    <input class="td_helpdesk_post_types" type="checkbox"
                                            name="td_helpdesk_post_types[]"
                                            value="<?php echo esc_attr( $post_type ); ?>" <?php echo in_array( $post_type,
                                        $td_selected_post_types ) ? 'checked' : ''; ?>>
                                    <label for="<?php echo esc_attr( $post_type ); ?>"> <?php echo esc_html( ucfirst( $post_type ) ); ?> </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="bg-gray-50 border p-4 rounded">
                        <label for="td_user_account_pages" class="font-medium text-black text-base"><?php _e( 'Add Support Tab', 'thrivedesk' ); ?></label>
                        <div class="text-sm"><?php _e('You can add a Support tab to the WooCommerce and Easy Digital Downloads My Account page depending on the availability of the plugin', 'thrivedesk');?></div>
                        <div class="mt-3">
                            <?php foreach ( $td_user_account_pages as $key => $page ) : ?>
                                <div class="mb-1" <?php echo !$woo_plugin_installed ? 'title = "You must install and activate WooCommerce plugin to use this feature"' : ''; ?>>
                                    <input class="td_user_account_pages" type="checkbox"
                                            name="td_user_account_pages[]"
                                            value="<?php echo esc_attr( $key ); ?>" <?php echo in_array( $key,
                                        $td_selected_user_account_pages ) ? 'checked ' : ''; echo !$woo_plugin_installed ? 'disabled' : ''; ?>>
                                    <label for="<?php echo esc_attr( $page ); ?>"> <?php echo esc_html( $page ); ?> </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="md:w-64 mt-4 md:mt-0">
                    <div class="p-4 bg-green-50 border border-green-300 rounded space-y-2">
                        <div class="text-base font-semibold"><?php _e( 'Portal Shortcode', 'thrivedesk' ); ?></div>
                        <code class="inline-block bg-green-200 rounded">[thrivedesk_portal]</code>
                        <p><?php _e( 'Utilize this shortcode on any page to transform it into a help center.', 'thrivedesk' ); ?>.</p>
                        <p><?php _e( 'The portal is accessible only to logged-in users.', 'thrivedesk' ); ?>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- connection  -->
        <div class="space-y-1">
        <div class="td-card-heading">
            <div class="text-base font-bold"><?php _e( 'Connection Details', 'thrivedesk' ); ?></div>
            <p><?php _e('Update your api token to change or update the connection to ThriveDesk.', 'thrivedesk'); ?></p>
        </div>
        <div class="td-card">
            <div class="space-y-2">
                <label for="td_helpdesk_api_key" class="block mb-2 text-sm font-medium text-gray-900"><?php _e( 'API Key', 'thrivedesk' ); ?></label>
                <span>
                    <?php _e( 'Login to ThriveDesk app and get your API key from ',
                        'thrivedesk' ); ?>
                            <a class="text-blue-500" href="<?php echo THRIVEDESK_APP_URL.'/settings/company/api-key' ?>" target="_blank">
                                <?php _e( 'here', 'thrivedesk' ); ?>
                            </a>
                </span>
                <div class="flex items-center api-key-preview">
                    <input class="truncate w-2/3 bg-gray-50" type="password" disabled value="<?php echo esc_attr( $td_api_key ); ?>" />
                    <span class="text-green-500 underline hover:text-green-600 px-2 cursor-pointer trigger">Update</span>
                </div>
                <div class="api-key-editable hidden">
                    <input type="password" id="td_helpdesk_api_key" name="td_helpdesk_api_key" value="<?php echo esc_attr( $td_api_key ); ?>" class="block p-2.5 w-full text-sm" />

                    <button type="button" class="btn-primary py-1.5 mt-3" id="td-api-verification-btn">
                        <?php _e('Verify', 'thrivedesk');?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" id="td_setting_btn_submit" class="btn-primary">
        <?php _e( 'Save', 'thrivedesk' ); ?>
    </button>
</form>