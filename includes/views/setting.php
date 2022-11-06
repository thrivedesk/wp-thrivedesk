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
		'category'    => 'WordPress',
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

<div class="thrivedesk absolute w-full h-full top-0 left-0 flex flex-col overflow-hidden bg-sky-50">
    <!-- header  -->
    <div class="flex items-center py-4 px-6 bg-white border-b">
        <img class="w-32" src="<?php echo THRIVEDESK_PLUGIN_ASSETS . "/images/thrivedesk.png"; ?>"
                alt="ThriveDesk Logo">
        <div class="ml-auto flex space-x-3 text-sm">
            <a class="px-2 py-1 flex items-center space-x-1 border rounded shadow-sm hover:bg-blue-600 hover:text-white hover:border-blue-600"
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
            <a class="px-2 py-1" href="https://status.thrivedesk.com/" target="_blank">
                <?php _e( 'System Status', 'thrivedesk' ) ?>
            </a>
            <span class="py-1 px-2 bg-blue-50 font-medium rounded">
                <?php _e( 'Version', 'thrivedesk' ) ?> <?php echo THRIVEDESK_VERSION;?>
            </span>
        </div>
    </div>

    <!-- body  -->
    <div class="flex flex-1">
        <!-- sidebar  -->
        <div class="w-64 bg-white p-5 border-r flex flex-col gap-1 nav-tabs">
            <a data-target="tab-welcome" href="#welcome">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-signpost-split-fill" viewBox="0 0 16 16">
                    <path d="M7 16h2V6h5a1 1 0 0 0 .8-.4l.975-1.3a.5.5 0 0 0 0-.6L14.8 2.4A1 1 0 0 0 14 2H9v-.586a1 1 0 0 0-2 0V7H2a1 1 0 0 0-.8.4L.225 8.7a.5.5 0 0 0 0 .6l.975 1.3a1 1 0 0 0 .8.4h5v5z"/>
                </svg>
                <span><?php _e( 'Get Started', 'thrivedesk' ) ?></span>
            </a>

            <a data-target="tab-integrations" href="#integrations">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-boxes" viewBox="0 0 16 16">
                    <path d="M7.752.066a.5.5 0 0 1 .496 0l3.75 2.143a.5.5 0 0 1 .252.434v3.995l3.498 2A.5.5 0 0 1 16 9.07v4.286a.5.5 0 0 1-.252.434l-3.75 2.143a.5.5 0 0 1-.496 0l-3.502-2-3.502 2.001a.5.5 0 0 1-.496 0l-3.75-2.143A.5.5 0 0 1 0 13.357V9.071a.5.5 0 0 1 .252-.434L3.75 6.638V2.643a.5.5 0 0 1 .252-.434L7.752.066ZM4.25 7.504 1.508 9.071l2.742 1.567 2.742-1.567L4.25 7.504ZM7.5 9.933l-2.75 1.571v3.134l2.75-1.571V9.933Zm1 3.134 2.75 1.571v-3.134L8.5 9.933v3.134Zm.508-3.996 2.742 1.567 2.742-1.567-2.742-1.567-2.742 1.567Zm2.242-2.433V3.504L8.5 5.076V8.21l2.75-1.572ZM7.5 8.21V5.076L4.75 3.504v3.134L7.5 8.21ZM5.258 2.643 8 4.21l2.742-1.567L8 1.076 5.258 2.643ZM15 9.933l-2.75 1.571v3.134L15 13.067V9.933ZM3.75 14.638v-3.134L1 9.933v3.134l2.75 1.571Z"/>
                </svg>
                <span><?php _e( 'Integrations', 'thrivedesk' ) ?></span>
            </a>
            
            <a data-target="tab-settings" href="#settings">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-gear-wide" viewBox="0 0 16 16">
                    <path d="M8.932.727c-.243-.97-1.62-.97-1.864 0l-.071.286a.96.96 0 0 1-1.622.434l-.205-.211c-.695-.719-1.888-.03-1.613.931l.08.284a.96.96 0 0 1-1.186 1.187l-.284-.081c-.96-.275-1.65.918-.931 1.613l.211.205a.96.96 0 0 1-.434 1.622l-.286.071c-.97.243-.97 1.62 0 1.864l.286.071a.96.96 0 0 1 .434 1.622l-.211.205c-.719.695-.03 1.888.931 1.613l.284-.08a.96.96 0 0 1 1.187 1.187l-.081.283c-.275.96.918 1.65 1.613.931l.205-.211a.96.96 0 0 1 1.622.434l.071.286c.243.97 1.62.97 1.864 0l.071-.286a.96.96 0 0 1 1.622-.434l.205.211c.695.719 1.888.03 1.613-.931l-.08-.284a.96.96 0 0 1 1.187-1.187l.283.081c.96.275 1.65-.918.931-1.613l-.211-.205a.96.96 0 0 1 .434-1.622l.286-.071c.97-.243.97-1.62 0-1.864l-.286-.071a.96.96 0 0 1-.434-1.622l.211-.205c.719-.695.03-1.888-.931-1.613l-.284.08a.96.96 0 0 1-1.187-1.186l.081-.284c.275-.96-.918-1.65-1.613-.931l-.205.211a.96.96 0 0 1-1.622-.434L8.932.727zM8 12.997a4.998 4.998 0 1 1 0-9.995 4.998 4.998 0 0 1 0 9.996z"/>
                </svg>
                <span><?php _e( 'Settings', 'thrivedesk' ); ?></span>
            </a>

            <?php if ( $wppostsync->get_plugin_data( 'connected' ) ) : ?>
                <a data-target="tab-post-types-sync" href="#"><?php _e( 'WP Post Sync', 'thrivedesk' ) ?></a>
            <?php endif; ?>


            <a data-target="tab-resource" href="#resource">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-ui-radios" viewBox="0 0 16 16">
                    <path d="M7 2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5v-1zM0 12a3 3 0 1 1 6 0 3 3 0 0 1-6 0zm7-1.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5v-1zm0-5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm0 8a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zM3 1a3 3 0 1 0 0 6 3 3 0 0 0 0-6zm0 4.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/>
                </svg>
                <span><?php _e( 'Resource', 'thrivedesk' ) ?></span>
            </a>
        </div>
        <!-- body  -->
        <div class="flex-1">
            <div class="mx-auto p-8 max-w-3xl 2xl:max-w-7xl">
                <!-- reposition error info from header -->
                <h1></h1>
                <div id="tab-content" class="px-1.5">
                    <div class="tab-integrations">
                        <div class="mb-4 text-lg"><?php _e( 'Integrations', 'thrivedesk' ) ?></div>
                        <div class="space-y-3 sm:space-y-0 sm:grid sm:grid-cols-3 lg:grid-cols-4 sm:gap-4">
                            <?php foreach ( $plugins as $plugin ) : ?>
                                <div class="td-card">
                                    <!-- title  -->
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-4">
                                            <img class="w-12 h-12"
                                                src="<?php echo THRIVEDESK_PLUGIN_ASSETS . '/images/' . sanitize_file_name( $plugin['image'] ); ?>"
                                                alt="plugin_image"/>
                                            <div class="font-medium text-base"><?php echo esc_html( $plugin['name'] ); ?></div>
                                        </div>
                                        <?php if ( $plugin['connected'] ) : ?>
                                            <span class="p-1 rounded-full bg-green-100 text-green-500" title=<?php _e( 'Connected',
                                                'thrivedesk' ) ?>>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor"
                                                    viewBox="0 0 16 16">
                                                    <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
                                                </svg>
                                            </span>

                                        <?php elseif ( ! $plugin['installed'] ) : ?>
                                            <span class="p-1 rounded-full bg-red-100 text-red-500"
                                                title="<?php _e( 'Plugin not installed or not activated yet', 'thrivedesk' ) ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                                    class="bi bi-exclamation-lg" viewBox="0 0 16 16">
                                                    <path d="M7.005 3.1a1 1 0 1 1 1.99 0l-.388 6.35a.61.61 0 0 1-1.214 0L7.005 3.1ZM7 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0Z"/>
                                                </svg>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <!-- description  -->
                                    <div class="text-gray-500 text-sm my-3">
                                        <?php echo esc_html( $plugin['description'] ); ?>
                                    </div>

                                    <!-- meta  -->
                                    <div class="flex items-center justify-between relative">
                                        <span class="uppercase text-xs text-gray-400"><?php echo esc_html( $plugin['category'] ) ?></span>

                                        <div class="text-xs">
                                            <?php if ( $plugin['connected'] ) : ?>
                                                <button data-plugin="<?php echo esc_attr( $plugin['namespace'] ); ?>"
                                                        data-connected="1" data-nonce="<?php echo esc_attr( $nonce ); ?>"
                                                        class="connect inline-flex items-center space-x-1 py-1.5 pl-2 pr-3 rounded-full bg-red-100 text-red-500 focus:outline-none focus:ring-2 focus:ring-red-600">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="w-4 h-4"
                                                        viewBox="0 0 16 16">
                                                        <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                                                    </svg>
                                                    <span><?php _e( 'Disconnect', 'thrivedesk' ) ?></span>
                                                </button>
                                            <?php else : ?>
                                                <button data-plugin="<?php echo esc_attr( $plugin['namespace'] ); ?>"
                                                        data-connected="0" data-nonce="<?php echo esc_attr( $nonce ); ?>"
                                                        class="connect inline-flex items-center space-x-2 py-1.5 px-3 rounded-full bg-gray-200 text-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-600 <?php echo ! $plugin['installed'] ? 'opacity-50 cursor-not-allowed' : '' ?>" <?php echo ! $plugin['installed'] ? 'disabled' : '' ?>>
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="w-3 h-3"
                                                        viewBox="0 0 16 16">
                                                        <path d="M0 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2h2a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2H2a2 2 0 0 1-2-2V2zm5 10v2a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1h-2v5a2 2 0 0 1-2 2H5zm6-8V2a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h2V6a2 2 0 0 1 2-2h5z"/>
                                                    </svg>
                                                    <span><?php _e( 'Connect', 'thrivedesk' ) ?></span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
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
                                <p><?php _e('Select live chat assistant for your website', 'thrivedesk'); ?></p>
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
                                <p><?php _e( 'Customers can raise and track support requests, access Knowledge base and FAQs to find quick answers to common questions, and engage with support agent',
                                        'thrivedesk' ); ?></p>
                                <div class="td-card flex space-x-4">
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
                                        <p><?php _e( 'Portal can only be accessible by logged in users' ); ?>.</p>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" id="td_setting_btn_submit" class="text-white bg-blue-600 hover:bg-blue-700 font-medium text-base rounded py-2.5 px-6">
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
