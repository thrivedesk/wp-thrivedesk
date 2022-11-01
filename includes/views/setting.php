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
    <div class="bg-white border-b">
        <div class="flex items-center py-4 px-6 border-b">
            <img class="w-32" src="<?php echo THRIVEDESK_PLUGIN_ASSETS . "/images/thrivedesk.png"; ?>"
                 alt="ThriveDesk Logo">
            <div class="ml-auto flex space-x-3 text-sm">
                <a class="px-2 py-1 flex items-center space-x-1 border rounded shadow-sm hover:bg-blue-600 hover:text-white hover:border-blue-600"
                   href="https://www.thrivedesk.com" target="_blank">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor"
                         viewBox="0 0 16 16">
                        <path fill-rule="evenodd"
                              d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z"/>
                        <path fill-rule="evenodd"
                              d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z"/>
                    </svg>
                    <span><?php _e( 'Visit ThriveDesk', 'thrivedesk' ) ?></span>
                </a>
                <a class="px-2 py-1" href="https://status.thrivedesk.com" target="_blank">
                    <?php _e( 'System Status', 'thrivedesk' ) ?>
                </a>
                <span class="py-1 px-2 bg-blue-50 font-medium rounded">
                    <?php _e( 'Version', 'thrivedesk' ) ?> <?php echo THRIVEDESK_VERSION;?>
                </span>
            </div>
        </div>
    </div>

    <!-- body  -->
    <div class="flex flex-1">
        <!-- sidebar  -->
        <div class="w-64 bg-white p-5 border-r flex flex-col gap-1 nav-tabs">
            <a data-target="tab-welcome" href="#welcome"><?php _e( 'Get Started', 'thrivedesk' ) ?></a>

            <a data-target="tab-integrations" href="#integrations"><?php _e( 'Integrations', 'thrivedesk' ) ?></a>
            
            <a data-target="tab-settings" href="#portal">
                <?php _e( 'Portal', 'thrivedesk' ); ?>
                <span class="bg-blue-50 text-blue-500 border border-blue-200 uppercase text-xs ml-1
                px-1.5 py-0.5 rounded"><?php _e( 'Beta', 'thrivedesk' ); ?>
                </span>
            </a>
            <a data-target="tab-assistant" href="#assistant"><?php _e( 'Assistant', 'thrivedesk' ) ?></a>

            <?php if ( $wppostsync->get_plugin_data( 'connected' ) ) : ?>
                <a data-target="tab-post-types-sync" href="#"><?php _e( 'WP Post Sync', 'thrivedesk' ) ?></a>
            <?php endif; ?>


            <a data-target="tab-resource" href="#resource"><?php _e( 'Resource', 'thrivedesk' ) ?></a>
        </div>
        <!-- body  -->
        <div class="flex-1">
            <div class="mx-auto p-8 max-w-3xl 2xl:max-w-7xl">
                <!-- reposition error info from header -->
                <h1></h1>
                <div id="tab-content" class="px-1.5 prose max-w-none prose-img:my-0">
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

                        <div class="td-card td-settings md:flex p-0 2xl:max-w-7xl mx-auto">
                            <form class="md:flex-1 space-y-6 p-10" id="td_helpdesk_form" action="#" method="POST">
                                <div>
                                    <div class="md:grid md:grid-cols-4 md:gap-10">
                                        <div class="md:col-span-2">
                                            <div>
                                                <h3 class="text-lg font-semibold leading-6 text-gray-900 mt-0">
                                                    <?php _e( 'Ticket', 'thrivedesk' ); ?>
                                                </h3>
                                                <div class="mt-1 text-sm text-gray-600">
                                                    <p><strong><?php _e( 'API Keys', 'thrivedesk' ); ?>:</strong>
                                                        <?php _e( 'Login to ThriveDesk app and get your API key from ',
                                                            'thrivedesk' ); ?>
                                                        <a class="text-blue-500" href="#" target="_blank">
                                                            <?php _e( 'here', 'thrivedesk' ); ?>
                                                        </a>.
                                                    </p>
                                                    <p><strong><?php _e( 'New Ticket Page', 'thrivedesk' ); ?>:</strong>
                                                        <?php _e( 'Use any form plugin to create new ticket page or use existing one. Learn more ',
                                                            'thrivedesk' ); ?>
                                                        <a class="text-blue-500" href="#">here</a>.</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-5 md:mt-0 md:col-span-2 space-y-4">
                                            <div>
                                                <label for="td_helpdesk_api_key"
                                                    class="font-medium text-black text-sm"><?php _e( 'API Key', 'thrivedesk' ); ?></label>
                                                <input id="td_helpdesk_api_key" type="text" name="td_helpdesk_api_key"
                                                    class="mt-1 block w-full"
                                                    value="<?php echo esc_attr( $td_helpdesk_selected_option['td_helpdesk_api_key'] ?? '' ); ?>"
                                                    required/>
                                            </div>
                                            <div>
                                                <label for="td_helpdesk_page_id"
                                                    class="font-medium text-black text-sm"><?php _e( 'New Ticket Page',
                                                        'thrivedesk' ); ?>
                                                </label>
                                                <select id="td_helpdesk_page_id" class="mt-1 bg-gray-50 border border-gray-300
                                                rounded
                                                px-2 py-1 w-full max-w-full" required>
                                                    <option value=""> <?php _e( 'Select a page', 'thrivedesk' ); ?> </option>
                                                    <?php foreach ( get_pages() as $key => $page ) : ?>
                                                        <option value="<?php echo $page->ID; ?>" <?php echo array_key_exists( 'td_helpdesk_page_id',
                                                            $td_helpdesk_selected_option ) && $td_helpdesk_selected_option['td_helpdesk_page_id'] == $page->ID ? 'selected' : '' ?> >
                                                            <?php echo $page->post_title; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div>
                                    <div class="md:grid md:grid-cols-4 md:gap-10">
                                        <div class="md:col-span-2">
                                            <div>
                                                <h3 class="text-lg font-semibold leading-6 text-gray-900"><?php _e( 'Knowledge 
                                                base', 'thrivedesk' ); ?>
                                                </h3>
                                                <div class="mt-1 text-sm text-gray-600">
                                                    <p><strong><?php _e( 'Search Provider', 'thrivedesk' ); ?>:</strong>
                                                        <?php _e( 'Select a post type where user can search before raise a support ticket',
                                                            'thrivedesk' ); ?>.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-5 md:mt-0 md:col-span-2">
                                            <div class="mb-6">
                                                <label for="td_helpdesk_post_types"
                                                    class="font-medium text-black text-sm"><?php _e( 'Search Provider',
                                                        'thrivedesk' ); ?>
                                                </label>
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
                                            </div>


                                            <button type="submit" id="td_setting_btn_submit"
                                                    class="text-white bg-blue-600 hover:bg-blue-700 font-semibold text-base rounded w-full py-2.5"><?php _e( 'Save',
                                                    'thrivedesk' ); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            <div class="bg-gray-100 px-6 py-2 rounded-r-md md:w-80">
                                <h3 class="text-xl font-semibold mb-3"><?php _e( 'Portal', 'thrivedesk' ); ?></h3>
                                <p><?php _e( 'With ThriveDesk Portal, your customers can raise and track support requests, access Knowledge base and FAQs to find quick answers to common questions, and engage with support agent',
                                        'thrivedesk' ); ?>.</p>
                                <p><?php _e( 'This tickets portal can only be accessible by logged in users' ); ?>.</p>
                                <h3 class="text-xl font-semibold my-3"><?php _e( 'Shortcodes', 'thrivedesk' ); ?></h3>
                                <p>
                                    <code>[thrivedesk_portal]</code>
                                    - <?php _e( 'Customer ticket dashboard only accessible after login', 'thrivedesk' ); ?>.
                                </p>
                            </div>
                        </div> <!-- td-card-end  -->
                    </div>

                    <div class="hidden tab-assistant">
                        <div class="td-card td-settings md:flex p-0 2xl:max-w-7xl mx-auto">
                            <form class="md:flex-1 space-y-6 p-10" id="td_helpdesk_form" action="#" method="POST">
                                <div>
                                    <div class="border-b-2">
                                        <h3 class="text-lg font-semibold leading-6 text-gray-900 mt-0">
                                            <?php _e( 'Assistants', 'thrivedesk' ); ?>
                                        </h3>
                                    </div>
                                    <ul class="list-none">
                                        <?php foreach ($assistants as $assistant): ?>
                                            <li>
                                                <label for="td-assistant-checked-toggle-<?php echo $assistant['id']; ?>"
                                                    class="inline-flex relative items-center mb-4 cursor-pointer">
                                                    <input type="checkbox" value="<?php echo $assistant['id']; ?>"
                                                        id="td-assistant-checked-toggle-<?php echo $assistant['id']; ?>"
                                                        name="td-assistant" class="sr-only peer td-assistant-item"
                                                        <?php echo ($assistant_settings && $assistant_settings['status']) &&
                                                                $assistant_settings['id'] == $assistant['id'] ? 'checked' : ''; ?>>
                                                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                                    <span class="ml-3 text-sm font-medium text-gray-900
                                                    dark:text-gray-300"><?php echo $assistant['name']; ?></span>
                                                </label>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>

                                    <?php if (count($assistants) <=0 ): ?>
                                        <p>No Assistant found.
                                            <a href="https://app.thrivedesk.com/assistants" target="_blank">Click</a>
                                            to set up your assistant.
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <hr>
                            </form>
                        </div>
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
