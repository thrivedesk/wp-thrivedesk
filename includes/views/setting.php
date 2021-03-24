<?php

$edd = ThriveDesk\Plugins\EDD::instance();
$woocommerce = ThriveDesk\Plugins\WooCommerce::instance();
// $smartpay = ThriveDesk\Plugins\SmartPay::instance();

$plugins = [
    [
         'namespace' => 'woocommerce',
         'name'      => __('WooCommerce', 'thrivedesk'),
         'description'   => __('Share purcahse data, shipping details and license information realtime with ThriveDesk.'),
         'image'     => 'woocommerce.png',
         'category'      => 'ecommerce',
         'installed' => $woocommerce->is_plugin_active(),
         'connected' => $woocommerce->get_plugin_data('connected'),
     ],
    [
        'namespace'     => 'edd',
        'name'          => __('Easy Digital Downloads', 'thrivedesk'),
        'description'   => __('Share customer purcahse data, subscription and license information realtime with ThriveDesk.'),
        'image'         => 'edd.png',
        'category'      => 'ecommerce',
        'installed'     => $edd->is_plugin_active(),
        'connected'     => $edd->get_plugin_data('connected'),
    ],
    // [
    //     'name'      => __('SmartPay', 'thrivedesk'),
    //     'namespace' => 'smartpay',
    //     'image'     => 'smartpay.png',
    //     'installed' => $smartpay->is_plugin_active(),
    //     'connected' => $smartpay->get_plugin_data('connected'),
    // ],
];

$nonce = wp_create_nonce('thrivedesk-connect-plugin');
?>

<div class="wrap thrivedesk">
    <!-- header  -->
    <div class="bg-white -mt-2 -mx-5 shadow mb-6">
        <div class="flex items-center justify-between py-3 px-6 border-b">
            <img class="w-32" src="//www.thrivedesk.com/wp-content/uploads/2021/03/png-logo.png" alt="ThriveDesk Logo">
            <div>
                <a class="px-4 py-1.5 flex items-center space-x-1 border rounded border-blue-500 text-blue-500 hover:bg-blue-50" href="https://www.thrivedesk.com" target="_blank">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z"/>
                        <path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z"/>
                    </svg>
                    <span>Visit Website</span>
                </a>
            </div>
        </div>
        <div class="px-6 space-x-8 flex text-sm font-medium leading-5 text-gray-500 h-12">
            <a class="inline-flex items-center px-1 border-b-2 border-blue-600" href="#">Integrations</a>
        </div>
    </div>
    <!-- body  -->
    <div class="px-1.5">
        <div class="mb-4 text-lg"><?php _e('Integrations', 'thrivedesk') ?></div>
        <div class="sm:grid sm:grid-cols-3 sm:gap-4">
            <?php foreach ($plugins as $plugin) : ?>
            <div class="border rounded-md p-4 bg-white transition hover:shadow-lg">
                <div class="flex items-center space-x-4">
                    <img class="w-12 h-12" src="<?php echo THRIVEDESK_PLUGIN_ASSETS . '/images/' . $plugin['image']; ?>" />
                    <div class="font-medium text-base"><?php echo $plugin['name']; ?></div>
                </div>
                <div class="text-gray-500 text-xs my-3">
                    <?php echo $plugin['description'] ?>
                </div>
                <div class="flex items-center justify-between relative">
                    <span class="uppercase text-xs text-gray-400"><?php echo $plugin['category'] ?></span>
                    <div>
                        <?php if ($plugin['connected']) : ?>
                            <span class="inline-flex items-center space-x-2 py-1.5 px-3 rounded-full bg-green-100 text-green-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
                                </svg>
                                <span>Connected</span>
                            </span>
                        <?php else: ?>
                            <button 
                                data-plugin="<?php echo $plugin['namespace']; ?>" data-connected="<?php echo $plugin['connected'] ? '1' : '0'; ?>" data-nonce="<?php echo $nonce; ?>" 
                                class="connect inline-flex items-center space-x-2 py-1.5 px-3 rounded-full bg-gray-200 text-gray-600 <?php echo !$plugin['installed'] ? 'opacity-50' : '' ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="w-4 h-4" viewBox="0 0 16 16">
                                        <path d="M0 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2h2a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2H2a2 2 0 0 1-2-2V2zm5 10v2a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1h-2v5a2 2 0 0 1-2 2H5zm6-8V2a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h2V6a2 2 0 0 1 2-2h5z"/>
                                    </svg>
                                    <span>Connect</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>