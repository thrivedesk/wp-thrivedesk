<?php

$edd         = ThriveDesk\Plugins\EDD::instance();
$woocommerce = ThriveDesk\Plugins\WooCommerce::instance();
$fluentcrm   = ThriveDesk\Plugins\FluentCRM::instance();
$wppostsync  = ThriveDesk\Plugins\WPPostSync::instance();
$autonami    = ThriveDesk\Plugins\Autonami::instance();
// $smartpay = ThriveDesk\Plugins\SmartPay::instance();

$assistants = \ThriveDesk\Assistants\Assistant::get_assistants()['assistants'] ?? [];
$assistant_settings = \ThriveDesk\Assistants\Assistant::get_assistant_settings();
$plugins = [
	[
		'namespace'   => 'woocommerce',
		'name'        => __( 'WooCommerce', 'thrivedesk' ),
		'description' => __( 'Share purchase data, shipping details and license information realtime with ThriveDesk.' ),
		'image'       => 'woocommerce.png',
		'category'    => 'ecommerce',
		'installed'   => $woocommerce->is_plugin_active(),
		'connected'   => $woocommerce->get_plugin_data( 'connected' ),
	],
	[
		'namespace'   => 'edd',
		'name'        => __( 'Easy Digital Downloads', 'thrivedesk' ),
		'description' => __( 'Share customer purchase data, subscription and license information realtime with ThriveDesk.' ),
		'image'       => 'edd.png',
		'category'    => 'ecommerce',
		'installed'   => $edd->is_plugin_active(),
		'connected'   => $edd->get_plugin_data( 'connected' ),
	],
	[
		'namespace'   => 'fluentcrm',
		'name'        => __( 'FluentCRM', 'thrivedesk' ),
		'description' => __( 'Sync your contacts and tickets information with ThriveDesk.' ),
		'image'       => 'fluentcrm.png',
		'category'    => 'crm',
		'installed'   => $fluentcrm->is_plugin_active(),
		'connected'   => $fluentcrm->get_plugin_data( 'connected' ),
	],
	[
		'namespace'   => 'wppostsync',
		'name'        => __( 'WordPress Post Sync', 'thrivedesk' ),
		'description' => __( 'Share your site post data of selected post types for faster support.' ),
		'image'       => 'wppostsync.png',
		'category'    => 'Core',
		'installed'   => $wppostsync->is_plugin_active(),
		'connected'   => $wppostsync->get_plugin_data( 'connected' ),
	],
	[
		'namespace'   => 'autonami',
		'name'        => __( 'Autonami', 'thrivedesk' ),
		'description' => __( 'Share customer data with ThriveDesk.' ),
		'image'       => 'autonami.png',
		'category'    => 'CRM',
		'installed'   => $autonami->is_plugin_active(),
		'connected'   => $autonami->get_plugin_data( 'connected' ),
	],
	// [
	//     'name'      => __('SmartPay', 'thrivedesk'),
	//     'namespace' => 'smartpay',
	//     'image'     => 'smartpay.png',
	//     'installed' => $smartpay->is_plugin_active(),
	//     'connected' => $smartpay->get_plugin_data('connected'),
	// ],
];

$nonce = wp_create_nonce( 'thrivedesk-plugin-action' );
?>

<div class="thrivedesk absolute w-full h-full top-0 left-0 flex flex-col overflow-hidden bg-slate-50">
    <!-- header  -->
    <div class="flex items-center py-5 px-9">
        <img class="w-32" src="<?php echo THRIVEDESK_PLUGIN_ASSETS . "/images/thrivedesk.png"; ?>"
                alt="ThriveDesk Logo">
        <div class="ml-auto flex space-x-2 text-sm top-nav">
            <a class="rounded bg-gradient-to-b from-white to-neutral-100 shadow hover:from-blue-500 hover:to-blue-600 hover:text-white"
                href="https://www.thrivedesk.com/" target="_blank">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor"
                        viewBox="0 0 16 16">
                    <path fill-rule="evenodd"
                            d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z"/>
                    <path fill-rule="evenodd"
                            d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z"/>
                </svg>
                <span><?php _e( 'Visit ThriveDesk', 'thrivedesk' ) ?></span>
            </a>
            <a href="https://help.thrivedesk.com/en" target="_blank">
                <?php _e( 'Help Center', 'thrivedesk' ) ?>
            </a>
            <a href="https://status.thrivedesk.com/" target="_blank">
                <?php _e( 'System Status', 'thrivedesk' ) ?>
            </a>
            <a href="#" onclick="Assistant('contact', {
                subject: 'Issue/Feedback from WP Plugin',
                body: 'Write your issue/feedback details here...',
            })"><?php _e( 'Support', 'thrivedesk' ) ?></a>
            <span class="py-1 px-2 bg-slate-200 font-medium rounded">
                <?php _e( 'Version', 'thrivedesk' ) ?> <?php echo THRIVEDESK_VERSION;?>
            </span>
        </div>
    </div>

    <!-- body  -->
    <div class="flex flex-1">
        <!-- sidebar  -->
        <div class="w-64 p-5 flex flex-col gap-1 sidebar-nav tab-link">
            <a data-target="tab-welcome" href="#welcome">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-signpost-split-fill" viewBox="0 0 16 16">
                    <path d="M7 16h2V6h5a1 1 0 0 0 .8-.4l.975-1.3a.5.5 0 0 0 0-.6L14.8 2.4A1 1 0 0 0 14 2H9v-.586a1 1 0 0 0-2 0V7H2a1 1 0 0 0-.8.4L.225 8.7a.5.5 0 0 0 0 .6l.975 1.3a1 1 0 0 0 .8.4h5v5z"/>
                </svg>
                <span><?php _e( 'Get Started', 'thrivedesk' ) ?></span>
            </a>

            <a data-target="tab-integrations" href="#integrations">
                <?php thrivedesk_view('icons/integration'); ?>
                <span><?php _e( 'Integrations', 'thrivedesk' ) ?></span>
            </a>
            
            <a data-target="tab-settings" href="#settings">
                <?php thrivedesk_view('icons/settings'); ?>
                <span><?php _e( 'Settings', 'thrivedesk' ); ?></span>
            </a>

            <?php if ( $wppostsync->get_plugin_data( 'connected' ) ) : ?>
                <a data-target="tab-post-types-sync" href="#"><?php _e( 'WP Post Sync', 'thrivedesk' ) ?></a>
            <?php endif; ?>


            <a data-target="tab-resource" href="#resource">
                <?php thrivedesk_view('icons/resource'); ?>
                <span><?php _e( 'Resource', 'thrivedesk' ) ?></span>
            </a>
        </div>
        <!-- body  -->
        <div class="main-wrap">
            <div class="mx-auto px-8 py-12 2xl:max-w-7xl h-full">
                <!-- reposition error info from header -->
                <h1></h1>
                <div id="tab-content" class="px-1.5 h-full">
                    <div class="hidden tab-integrations">
                        <div class="text-lg font-bold"><?php _e( 'Integrations', 'thrivedesk' ) ?></div>
                        <div class="space-y-3 sm:space-y-0 sm:grid md:grid-cols-3 sm:gap-4 mt-4">
                            <?php foreach ( $plugins as $plugin ) : ?>
                                <div class="td-card relative">
                                    <!-- title  -->
                                    <div class="flex space-x-4">
                                        <img class="w-12 h-12 rounded"
                                            src="<?php echo THRIVEDESK_PLUGIN_ASSETS . '/images/' . sanitize_file_name( $plugin['image'] ); ?>"
                                            alt="plugin_image"/>
                                        <div>
                                            <div class="font-medium text-base"><?php echo esc_html( $plugin['name'] ); ?></div>
                                            <span class="uppercase text-xs text-gray-400"><?php echo esc_html( $plugin['category'] ) ?></span>
                                        </div>
                                    </div>
                                    <!-- description  -->
                                    <div class="text-gray-500 text-sm my-3">
                                        <?php echo esc_html( $plugin['description'] ); ?>
                                    </div>

                                    <!-- CTA  -->
                                    <?php if ( $plugin['connected'] ) : ?>
                                        <button data-plugin="<?php echo esc_attr( $plugin['namespace'] ); ?>"
                                                data-connected="1" data-nonce="<?php echo esc_attr( $nonce ); ?>"
                                                class="connect w-full py-2 text-center rounded bg-red-50 text-red-500 hover:bg-red-500 hover:text-white">
                                            
                                            <span><?php _e( 'Disconnect', 'thrivedesk' ) ?></span>
                                        </button>
                                    <?php else : ?>
                                        <button data-plugin="<?php echo esc_attr( $plugin['namespace'] ); ?>"
                                                data-connected="0" data-nonce="<?php echo esc_attr( $nonce ); ?>"
                                                class="connect w-full py-2 text-center rounded bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white <?php echo ! $plugin['installed'] ? 'opacity-50 cursor-not-allowed' : '' ?>" <?php echo ! $plugin['installed'] ? 'disabled' : '' ?>>
                                            <span><?php _e( 'Connect', 'thrivedesk' ) ?></span>
                                        </button>
                                    <?php endif; ?>
                                    <!-- connection status  -->
                                    <div class="absolute -top-3 right-1">
                                        <?php if ( $plugin['connected'] ) : ?>
                                        <div class=" py-1 pl-1.5 pr-3 rounded-full bg-green-100 text-green-600 text-xs flex items-center space-x-1" title=<?php _e( 'Connected',
                                            'thrivedesk' ) ?>>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor"
                                                viewBox="0 0 16 16">
                                                <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
                                            </svg>
                                            <span><?php _e('Connected', 'thrivedesk')?></span>
                                        </div>
                                        <?php elseif ( ! $plugin['installed'] ) : ?>
                                        <div class="p-1 rounded-full bg-red-100 text-red-500"
                                            title="<?php _e( 'Plugin not installed or not activated yet', 'thrivedesk' ) ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                                class="bi bi-exclamation-lg" viewBox="0 0 16 16">
                                                <path d="M7.005 3.1a1 1 0 1 1 1.99 0l-.388 6.35a.61.61 0 0 1-1.214 0L7.005 3.1ZM7 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0Z"/>
                                            </svg>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="hidden tab-settings">
                        <?php $td_helpdesk_selected_option = get_td_helpdesk_options(); ?>
                        <?php $td_selected_post_types = $td_helpdesk_selected_option['td_helpdesk_post_types'] ?? []; ?>
                        <form class="space-y-8" id="td_helpdesk_form" action="#" method="POST">
                            <!-- connection  -->
                            <div class="space-y-1">
                                <div class="text-base font-bold"><?php _e( 'Connection Details', 'thrivedesk' ); ?></div>
                                <p><?php _e('Update your api token to change or update the connection to ThriveDesk.', 'thrivedesk'); ?></p>
                                <div class="td-card">
                                    <div class="space-y-2">
                                        <label for="td_helpdesk_api_key" class="font-medium text-black text-sm"><?php _e( 'API Key', 'thrivedesk' ); ?></label>
                                        <input id="td_helpdesk_api_key" type="text" name="td_helpdesk_api_key" class="block w-full py-1 px-2 shadow-sm border-gray-300"
                                            value="<?php echo esc_attr( $td_helpdesk_selected_option['td_helpdesk_api_key'] ?? '' ); ?>" required/>
                                        <div>
                                            <?php _e( 'Login to ThriveDesk app and get your API key from ',
                                                'thrivedesk' ); ?>
                                            <a class="text-blue-500" href="#" target="_blank">
                                                <?php _e( 'here', 'thrivedesk' ); ?>
                                            </a>.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- assistant  -->
                            <div class="space-y-1">
                                <div class="text-base font-bold"><?php _e( 'Assistant', 'thrivedesk' ); ?></div>
                                <p><?php _e('Add live chat assistant to your website', 'thrivedesk'); ?></p>
                                <div class="td-card">
                                    <div class="space-y-2">
                                        <label class="font-medium text-black text-sm"><?php _e( 'Select Assistant', 'thrivedesk' ); ?></label>
                                        <select class="mt-1 bg-gray-50 border border-gray-300 rounded px-2 py-1 w-full max-w-full">
                                            <option value=""> <?php _e( 'Select an assistant', 'thrivedesk' ); ?> </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <!-- portal  -->
                            <div class="space-y-1">
                                <div class="text-base font-bold"><?php _e( 'Portal', 'thrivedesk' ); ?></div>
                                <p><?php _e( 'Help center inside your website. Customer can create and reply tickets, access Knowledge base.',
                                        'thrivedesk' ); ?></p>
                                <div class="td-card">

                                    <?php if (empty($td_helpdesk_selected_option)): ?>
                                        <div class="alert alert-info">
                                            <?php _e('You need to add the API key above ☝️ to use the Portal feature inside your site.', 'thrivedesk');?>
                                        </div>
                                    <?php endif; ?>

                                    <?php // check the plan, if not PRO or above then, show the below warning ?>
                                    <div class="alert alert-danger">
                                        <?php _e('Portal feature is available from the PRO plan and above. Please upgrade your subscription', 'thrivedesk');?>  
                                            <a class="text-blue-500" href="https://app.thrivedesk.com/billing/plans" target="_blank"><?php _e( 'here', 'thrivedesk' ); ?></a>.
                                    </div>
                                    <div class="flex space-x-4">
                                        <div class="space-y-4 flex-1">
                                            <div class="space-y-2">
                                                <label for="td_helpdesk_page_id" class="font-medium text-black text-sm"><?php _e( 'New Ticket Page', 'thrivedesk' ); ?></label>
                                                <select id="td_helpdesk_page_id" class="mt-1 bg-gray-50 border border-gray-300 rounded px-2 py-1 w-full max-w-full" required>
                                                    <option value=""> <?php _e( 'Select a page', 'thrivedesk' ); ?> </option>
                                                    <?php foreach ( get_pages() as $key => $page ) : ?>
                                                        <option value="<?php echo $page->ID; ?>" <?php echo array_key_exists( 'td_helpdesk_page_id',
                                                            $td_helpdesk_selected_option ) && $td_helpdesk_selected_option['td_helpdesk_page_id'] == $page->ID ? 'selected' : '' ?> >
                                                            <?php echo $page->post_title; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div><?php _e( 'Use any form plugin to create new ticket page or use existing one. Learn more ','thrivedesk' ); ?><a class="text-blue-500" href="#">here</a>.</div>
                                            </div>
                                            <div class="space-y-2">
                                                <label for="td_helpdesk_post_types" class="font-medium text-black text-sm"><?php _e( 'Search Provider', 'thrivedesk' ); ?></label>
                                                <?php
                                                    $wp_post_types = array_filter( get_post_types( array(
                                                        'public'       => true,
                                                        'show_in_rest' => true
                                                    ) ), function ( $type ) {
                                                        return $type !== 'attachment';
                                                    } ); ?>
                                                <div class="flex items-center space-x-2">
                                                    <?php foreach ( $wp_post_types as $post_type ) : ?>
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
                                        </div>
                                        <div class="p-4 bg-stone-100 border rounded w-64">
                                            <div class="text-base font-semibold"><?php _e( 'Shortcode', 'thrivedesk' ); ?></div>
                                            <code class="my-2 inline-block">[thrivedesk_portal]</code>
                                            <p><?php _e( 'Portal can only be accessible by logged in users', 'thrivedesk' ); ?>.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" id="td_setting_btn_submit" class="btn-primary">
                                <?php _e( 'Save', 'thrivedesk' ); ?>
                            </button>
                        </form>
                    </div>

                    <!-- include the welcome page -->
                    <?php thrivedesk_view( 'pages/welcome' ); ?>

                    <!-- include the resource page -->
                    <?php thrivedesk_view( 'pages/resource' ); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!--<script>-->
<!--    !function(t,e,n){function s(){-->
<!--        var t=e.getElementsByTagName("script")[0],n=e.createElement("script");-->
<!--        n.type="text/javascript",n.async=!0,n.src="https://assistant.thrivedesk.io/bootloader.js?"+Date.now(),-->
<!--        t.parentNode.insertBefore(n,t)}if(t.Assistant=n=function(e,n,s){t.Assistant.readyQueue.push({method:e,options:n,data:s})},-->
<!--        n.readyQueue=[],"complete"===e.readyState)return s();-->
<!--    t.attachEvent?t.attachEvent("onload",s):t.addEventListener("load",s,!1)}-->
<!--    (window,document,window.Assistant||function(){}),window.Assistant("init","954831e8-9cf3-4a06-b9b3-d324b2f7dc8b");-->
<!--    -->
<!--    Assistant('identify', {-->
<!--        name: '--><?php //echo wp_get_current_user()->display_name?>//',
//        email: '<?php //echo wp_get_current_user()->user_email?>//',
//    });
//</script>
