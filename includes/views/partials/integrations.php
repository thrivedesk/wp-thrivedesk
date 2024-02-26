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
    <div class="space-y-3 sm:space-y-0 sm:grid md:grid-cols-2 2xl:grid-cols-3 sm:gap-4 mt-4">
        <?php foreach ( $plugins as $plugin ) : ?>
            <div class="td-card relative flex items-center">
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
                                class="connect text-sm py-2 px-4 text-center rounded bg-red-50 text-red-500 hover:bg-red-500 hover:text-white">
                                <span><?php _e( 'Disconnect', 'thrivedesk' ) ?></span>
                        </button>
                    <?php else : ?>
                        <button data-plugin="<?php echo esc_attr( $plugin['namespace'] ); ?>"
                                data-connected="0" data-nonce="<?php echo esc_attr( $nonce ); ?>"
                                class="connect text-sm py-2 px-4 text-center rounded bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white <?php echo ! $plugin['installed'] ? 'opacity-50 cursor-not-allowed' : '' ?>" <?php echo ! $plugin['installed'] ? 'disabled' : '' ?>>
                            <span><?php _e( 'Connect', 'thrivedesk' ) ?></span>
                        </button>
                    <?php endif; ?>
                </div>
                <!-- connection status  -->
                <div class="absolute -top-3 left-0">
                    <?php if ( $plugin['connected'] ) : ?>
                    <div class="p-1.5 rounded-full bg-green-100 text-green-600 text-xs flex items-center space-x-1" title=<?php _e( 'Connected',
                        'thrivedesk' ) ?>>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" color="#16a34a" fill="none">
                            <path d="M17.8609 3.79058C17.8557 3.79012 17.8504 3.78966 17.8451 3.78921C17.4896 3.75919 17.0825 3.77072 16.7677 3.77964C16.6437 3.78315 16.534 3.78626 16.4474 3.78626C15.7548 3.78626 15.3063 3.58361 14.7917 3.06904L14.7563 3.03368C14.2535 2.53075 13.8236 2.10089 13.434 1.80358C13.0179 1.48611 12.5607 1.25 11.9995 1.25C11.4384 1.25 10.9811 1.48611 10.5651 1.80358C10.1754 2.10089 9.74557 2.53075 9.2427 3.03367L9.20733 3.06904C8.69267 3.5837 8.244 3.78626 7.55159 3.78626C7.46482 3.78626 7.35531 3.78318 7.23163 3.7797C6.9146 3.77078 6.50448 3.75924 6.14566 3.79027C5.6208 3.83566 4.96483 3.97929 4.46697 4.48134C3.97291 4.97955 3.83278 5.63282 3.78875 6.15439C3.75873 6.50995 3.77026 6.91701 3.77917 7.23178C3.78268 7.35578 3.78579 7.46549 3.78579 7.55206C3.78579 8.24448 3.58322 8.69315 3.06853 9.20784L3.03318 9.24319C2.53026 9.74606 2.1004 10.1759 1.80309 10.5655C1.48563 10.9816 1.24952 11.4388 1.24951 12C1.24953 12.5611 1.48564 13.0183 1.8031 13.4344C2.10046 13.8242 2.53042 14.2541 3.03346 14.7571L3.06857 14.7922C3.40223 15.1258 3.55963 15.3422 3.64884 15.5464C3.7357 15.7453 3.78579 15.9971 3.78579 16.4479C3.78579 16.5347 3.78271 16.6442 3.77923 16.7679C3.77031 17.0849 3.75877 17.495 3.78981 17.8539C3.8352 18.3787 3.97884 19.0347 4.48091 19.5326C4.97912 20.0266 5.63238 20.1667 6.15394 20.2107C6.50949 20.2408 6.91654 20.2292 7.2313 20.2203C7.35532 20.2168 7.46501 20.2137 7.55158 20.2137C7.99279 20.2137 8.24077 20.2581 8.43597 20.3386C8.63098 20.4191 8.83957 20.5632 9.15375 20.8774C9.2208 20.9444 9.30914 21.0391 9.41105 21.1483C9.641 21.3948 9.94022 21.7155 10.2195 21.9596C10.6432 22.33 11.2511 22.75 11.9995 22.75C12.748 22.75 13.3558 22.33 13.7796 21.9596C14.0588 21.7155 14.3578 21.3951 14.5877 21.1486C14.6897 21.0392 14.7782 20.9445 14.8453 20.8773C15.1595 20.5632 15.368 20.4191 15.5631 20.3386C15.7583 20.2581 16.0062 20.2137 16.4474 20.2137C16.534 20.2137 16.6437 20.2168 16.7677 20.2203C17.0825 20.2292 17.4895 20.2408 17.8451 20.2107C18.3666 20.1667 19.0199 20.0266 19.5181 19.5326C20.0202 19.0347 20.1638 18.3787 20.2092 17.8539C20.2403 17.495 20.2287 17.0849 20.2198 16.7679C20.2163 16.6443 20.2132 16.5346 20.2132 16.4479C20.2132 15.9971 20.2633 15.7453 20.3502 15.5464C20.4394 15.3422 20.5968 15.1258 20.9305 14.7922L20.9656 14.7571C21.4686 14.2541 21.8986 13.8242 22.1959 13.4344C22.5134 13.0183 22.7495 12.5611 22.7495 12C22.7495 11.4388 22.5134 10.9816 22.1959 10.5655C21.8986 10.1759 21.4688 9.74607 20.9659 9.24322L20.9305 9.20784C20.5968 8.87416 20.4394 8.65779 20.3502 8.45354C20.2633 8.25468 20.2132 8.00288 20.2132 7.55206C20.2132 7.46534 20.2163 7.35593 20.2198 7.23236C20.2287 6.91533 20.2403 6.50496 20.2092 6.14615C20.1718 5.71318 20.0675 5.191 19.7503 4.74195C19.5255 4.95243 19.2999 5.17598 19.0742 5.41127C17.9564 6.57656 16.8953 7.96605 15.9719 9.31511C15.0504 10.6612 14.2784 11.949 13.7363 12.9012C13.4461 13.411 13.163 13.9251 12.8945 14.4466L12.8923 14.4509C12.7175 14.7963 12.3586 15.0107 11.9716 14.9999C11.5846 14.989 11.2386 14.7558 11.0834 14.4011C10.2768 12.5573 9.41111 11.828 8.86715 11.5335C8.44891 11.307 8.16535 11.302 8.10895 11.3025L8.10297 11.3026C7.5683 11.3585 7.08218 10.9799 7.00882 10.4434C6.93399 9.89626 7.31877 9.39176 7.86596 9.31693C8.53579 9.23653 9.23813 9.45992 9.81954 9.77477C10.5266 10.1577 11.2894 10.8129 12.0054 11.8992C12.5659 10.9151 13.3646 9.5833 14.3215 8.18542C15.2804 6.78448 16.4105 5.29897 17.6309 4.02675C17.707 3.94743 17.7837 3.86867 17.8609 3.79058Z" fill="currentColor" />
                            <path d="M22.3749 2.92721C22.8869 2.72 23.1339 2.13701 22.9267 1.62507C22.7195 1.11313 22.1365 0.866105 21.6245 1.07332C20.288 1.61432 19.0108 2.62857 17.8611 3.79058C18.38 3.83612 19.0251 3.97837 19.5183 4.46747C19.6062 4.55463 19.6831 4.64664 19.7504 4.74195C20.651 3.89883 21.5394 3.26542 22.3749 2.92721Z" fill="currentColor" />
                        </svg>
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