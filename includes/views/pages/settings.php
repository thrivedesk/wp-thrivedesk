<?php
use ThriveDesk\Assistants\Assistant;
use ThriveDesk\Services\PortalService;
use ThriveDesk\Plugins\WPPostSync;

    $td_helpdesk_selected_option = get_td_helpdesk_options();
    $td_selected_post_types      = $td_helpdesk_selected_option['td_helpdesk_post_types'] ?? [];
    $td_selected_post_sync       = $td_helpdesk_selected_option['td_helpdesk_post_sync'] ?? [];
    $td_assistants               = Assistant::assistants();
    $td_api_key                  = $td_helpdesk_selected_option['td_helpdesk_api_key'] ?? '';
    $td_user_account_pages          = get_option( 'td_user_account_pages' );
    $td_selected_user_account_pages      = $td_helpdesk_selected_option['td_user_account_pages'] ?? [];
    $has_portal_access           = ( new PortalService() )->has_portal_access() ? true : false;
    $wppostsync                  = WPPostSync::instance();

    $show_api_key_alert  = (count($td_assistants) == 0) ? '' : 'hidden';
    $show_portal         = !$has_portal_access ? 'hidden' : '';
    $show_portal_warning = (!$has_portal_access && count($td_assistants) == 0) ? 'hidden' : '';

    $td_user_account_pages = array(
            'woocommerce' => 'Add support to WooCommerce my account page'
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

<div class="hidden tab-settings">
    <form class="space-y-12" id="td_helpdesk_form" action="#" method="POST">
        <!-- connection  -->
        <div class="space-y-1">
            <div class="text-base font-bold"><?php _e( 'Connection Details', 'thrivedesk' ); ?></div>
            <p><?php _e('Update your api token to change or update the connection to ThriveDesk.', 'thrivedesk'); ?></p>
            <div class="td-card">
                <div class="space-y-2">
                    <label for="td_helpdesk_api_key" class="block mb-2 text-sm font-medium text-gray-900"><?php _e( 'API Key', 'thrivedesk' ); ?></label>
                    <span>
                        <?php _e( 'Login to ThriveDesk app and get your API key from ',
                            'thrivedesk' ); ?>
                                <a class="text-blue-500" href="https://app.thrivedesk.com/settings/company/api-key" target="_blank">
                                    <?php _e( 'here', 'thrivedesk' ); ?>
                                </a>
                    </span>
                    <textarea id="td_helpdesk_api_key" rows="3" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500 " placeholder="Enter your API key here." name="td_helpdesk_api_key"><?php echo esc_attr( $td_api_key ); ?></textarea>

                    <button type="button" class="btn-primary py-1.5" id="td-api-verification-btn">
                        <?php _e('Verify', 'thrivedesk')?>
                    </button>
                </div>
            </div>
        </div>

        <!-- assistant  -->
        <div class="space-y-1">
            <div class="text-base font-bold"><?php _e('Assistant', 'thrivedesk'); ?></div>
            <p><?php _e('Add live chat assistant to your website. To create your assistant click <a href="https://app.thrivedesk.com/assistants" target="_blank">
            <span class="text-blue-500">here</span></a>', 'thrivedesk'); ?></p>
            <div class="td-card">
                <div class="space-y-2">
                    <label class="font-medium text-black text-sm"><?php _e( 'Select Assistant', 'thrivedesk' ); ?></label>
                    <select class="mt-1 bg-gray-50 border border-gray-300 rounded px-2 py-1 w-full max-w-full" id="td-assistants" <?php echo empty($td_api_key) ? 'disabled' : ''; ?>> <?php _e( 'Select an assistant', 'thrivedesk' ); ?> </option>
                        <option value=""><?php _e( 'Select an assistant', 'thrivedesk' ); ?>
                        </option>
                        <?php foreach ( $td_assistants as $assistant ) : ?>
                            <option value="<?php echo $assistant['id']; ?>" <?php echo $td_helpdesk_selected_option['td_helpdesk_assistant_id'] == $assistant['id'] ? 'selected' : ''; ?>>
                                <?php echo $assistant['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <!-- WP Post Sync  -->
        <div class="space-y-1">
            <div class="text-base font-bold"><?php _e( 'WP Post Sync', 'thrivedesk' ); ?></div>
            <p><?php _e( 'Sync your WordPress posts with ThriveDesk for faster support',
                    'thrivedesk'); ?></p>
            <div class="td-card">
                <div class="flex space-x-4" id="td_post_sync">
                    <div class="flex-1">
                        <div class="space-y-2">
                            <div class="flex items-center space-x-2">
                                <?php if ($wppostsync && $wppostsync->get_plugin_data('connected')) : ?>
                                    <?php foreach ( $wp_post_sync_types as $post_sync ) : ?>
                                        <div>
                                            <input class="td_helpdesk_post_sync" type="checkbox" name="td_helpdesk_post_sync[]" value="<?php echo esc_attr($post_sync); ?>" <?php echo in_array(
                                                                                                                                                                                    $post_sync,
                                                                                                                                                                                    $td_selected_post_sync
                                                                                                                                                                                ) ? 'checked' : ''; ?>>
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

        <!-- portal  -->
        <div class="space-y-1">
            <div class="text-base font-bold"><?php _e( 'Portal', 'thrivedesk' ); ?></div>
            <p><?php _e('Help center inside your website. Customer can create and reply tickets, access Knowledge base.','thrivedesk'); ?></p>
            <div class="td-card">
                <div class="text-center text-base <?php echo ($show_api_key_alert) ?>" id="api_key_alert">
                    <?php _e('Please insert or verify your ThriveDesk API key ☝️ to use the Portal feature inside your site.', 'thrivedesk'); ?>
                </div>

                <div class="alert alert-danger text-center <?php echo ($show_portal_warning) ?>" id="portal_feature_alert">
                    <?php _e('Portal feature is available from the PRO plan. For plans details click', 'thrivedesk'); ?>
                    <a class="text-blue-500" href="https://app.thrivedesk.com/billing/plans" target="_blank"><?php _e('here', 'thrivedesk'); ?></a>.
                </div>

                <div class="flex space-x-4 <?php echo ($show_portal) ?>" id="td_portal">
                    <div class="space-y-4 flex-1">
                        <div class="space-y-2">
                            <label for="td_helpdesk_page_id" class="font-medium text-black text-sm"><?php _e('New Ticket Page', 'thrivedesk'); ?></label>
                            <select id="td_helpdesk_page_id" class="mt-1 bg-gray-50 border border-gray-300 rounded px-2 py-1 w-full max-w-full">
                                <option value=""> <?php _e( 'Select a page', 'thrivedesk' ); ?> </option>
                                <?php foreach ( get_pages() as $key => $page ) : ?>
                                    <option value="<?php echo $page->ID; ?>" <?php echo array_key_exists( 'td_helpdesk_page_id',
                                    $td_helpdesk_selected_option) && $td_helpdesk_selected_option['td_helpdesk_page_id'] == $page->ID ? 'selected' : '' ?>>
                                        <?php echo $page->post_title; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div><?php _e( 'Use any form plugin to create new ticket page or use existing one. Learn more ','thrivedesk' ); ?><a class="text-blue-500" href="#">here</a>.</div>
                        </div>
                        <div class="space-y-2">
                            <label for="td_helpdesk_post_types" class="font-medium text-black text-sm"><?php _e( 'Search Provider', 'thrivedesk' ); ?></label>
                            <div class="flex items-center space-x-2 flex-wrap">
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
                            <div><?php _e( 'Select a post type where user can search before raise a support ticket', 'thrivedesk' ); ?>.</div>
                        </div>

                        <div class="space-y-2">
                            <label for="td_user_account_pages" class="font-medium text-black text-sm"><?php _e( 'Add Support Page', 'thrivedesk' ); ?></label>
                            <div class="">
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
                    <div class="p-4 bg-stone-100 border rounded w-64">
                        <div class="text-base font-semibold"><?php _e( 'Shortcode', 'thrivedesk' ); ?></div>
                        <code class="my-2 inline-block">[thrivedesk_portal]</code>
                        <p><?php _e( 'Portal can only be accessible by logged in users', 'thrivedesk' ); ?>.</p>
                        <div class="text-base font-semibold mt-3"><?php _e( 'Support Page', 'thrivedesk' ); ?></div>
                        <p><?php _e('You can add Support tab to WooCommerce my account page depending on the plugin availability.', 'thrivedesk')?></p>
                    </div>
                </div>
            </div>
        </div>
        <button type="submit" id="td_setting_btn_submit" class="btn-primary">
            <?php _e( 'Save', 'thrivedesk' ); ?>
        </button>
    </form>
</div>