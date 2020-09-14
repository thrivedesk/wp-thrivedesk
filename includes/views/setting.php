<?php

$edd = ThriveDesk\Plugins\EDD::instance();
$woocommerce = ThriveDesk\Plugins\WooCommerce::instance();
$smartpay = ThriveDesk\Plugins\SmartPay::instance();

$plugins = [
    [
        'name'      => __('Easy Digital Download', 'thrivedesk'),
        'namespace' => 'edd',
        'image'     => 'edd.png',
        'installed' => $edd->is_plugin_active(),
        'connected' => $edd->plugin_data('connected'),
    ],
    [
        'name'      => __('WooCommerce', 'thrivedesk'),
        'namespace' => 'woocommerce',
        'image'     => 'woocommerce.png',
        'installed' => $woocommerce->is_plugin_active(),
        'connected' => $woocommerce->plugin_data('connected'),
    ],
    [
        'name'      => __('SmartPay', 'thrivedesk'),
        'namespace' => 'smartpay',
        'image'     => 'smartpay.png',
        'installed' => $smartpay->is_plugin_active(),
        'connected' => $smartpay->plugin_data('connected'),
    ],
];

// var_dump($plugins);

$nonce = wp_create_nonce('thrivedesk-connect-plugin');
?>

<div class="wrap">
    <h1><?php _e('Thrive Desk Settings', 'thrivedesk') ?></h1>

    <div class="thrivedesk">
        <div class="row">
            <?php foreach ($plugins as $plugin) : ?>
            <div class="col">
                <div class="card">
                    <img src="<?php echo THRIVEDESK_PLUGIN_ASSETS . '/images/' . $plugin['image']; ?>" alt="" />
                    <?php if ($plugin['connected']) : ?>
                    <span class="installed-badge"><?php _e('Connected', 'thrivedesk'); ?></span>
                    <?php endif; ?>
                    <h2><?php echo $plugin['name']; ?></h2>
                    <div class="tooltip">
                        <?php if (!$plugin['installed']) : ?>
                        <span class="tooltip-text"><?php _e('Please install or active this plugin first.', 'thrivedesk') ?></span>
                        <?php endif; ?>

                        <button data-plugin="<?php echo $plugin['namespace']; ?>" data-connected="<?php echo $plugin['connected'] ? '1' : '0'; ?>" data-nonce="<?php echo $nonce; ?>" class="connect-plugin <?php echo $plugin['connected'] ? 'disconnect' : ''; ?>" <?php echo !$plugin['installed'] ? 'disabled' : '' ?>>
                            <?php _e(sprintf('%s', !$plugin['installed'] ? 'Install/Active Now' : ($plugin['connected'] ? 'Disconnect' : 'Connect Now')), 'thrivedesk'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>