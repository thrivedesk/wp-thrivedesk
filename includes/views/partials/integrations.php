<?php
$edd         = ThriveDesk\Plugins\EDD::instance();
$woocommerce = ThriveDesk\Plugins\WooCommerce::instance();
$fluentcrm   = ThriveDesk\Plugins\FluentCRM::instance();
$wppostsync  = ThriveDesk\Plugins\WPPostSync::instance();
$autonami    = ThriveDesk\Plugins\Autonami::instance();
// $smartpay = ThriveDesk\Plugins\SmartPay::instance();

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
		'name'        => __( 'FunnelKit Automations', 'thrivedesk' ),
		'description' => __( 'Broadcast and automated email campaigns without leaving WordPress.' ),
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
?>

<div class="integrations td-card">
    <div class="text-lg font-bold"><?php _e( 'Integrations', 'thrivedesk' ) ?></div>
    <div class="space-y-3 sm:space-y-0 sm:grid md:grid-cols-3 sm:gap-4 mt-4">
        <?php foreach ( $plugins as $plugin ) : ?>
            <div class="td-card relative flex">
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
                <!-- CTA  -->
                <div class="ml-auto">
                    <?php if ( $plugin['connected'] ) : ?>
                        <button data-plugin="<?php echo esc_attr( $plugin['namespace'] ); ?>"
                                data-connected="1" data-nonce="<?php echo esc_attr( $nonce ); ?>"
                                class="connect w-full py-2 px-4 text-center rounded bg-red-50 text-red-500 hover:bg-red-500 hover:text-white">
                            
                            <span><?php _e( 'Disconnect', 'thrivedesk' ) ?></span>
                        </button>
                    <?php else : ?>
                        <button data-plugin="<?php echo esc_attr( $plugin['namespace'] ); ?>"
                                data-connected="0" data-nonce="<?php echo esc_attr( $nonce ); ?>"
                                class="connect w-full py-2 text-center rounded bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white <?php echo ! $plugin['installed'] ? 'opacity-50 cursor-not-allowed' : '' ?>" <?php echo ! $plugin['installed'] ? 'disabled' : '' ?>>
                            <span><?php _e( 'Connect', 'thrivedesk' ) ?></span>
                        </button>
                    <?php endif; ?>
                </div>
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