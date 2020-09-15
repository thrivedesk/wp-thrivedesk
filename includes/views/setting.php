<?php

$edd = ThriveDesk\Plugins\EDD::instance();
// $woocommerce = ThriveDesk\Plugins\WooCommerce::instance();
// $smartpay = ThriveDesk\Plugins\SmartPay::instance();

$plugins = [
    [
        'name'      => __('Easy Digital Download', 'thrivedesk'),
        'namespace' => 'edd',
        'image'     => 'edd.png',
        'installed' => $edd->is_plugin_active(),
        'connected' => $edd->get_plugin_data('connected'),
    ],
    // [
    //     'name'      => __('WooCommerce', 'thrivedesk'),
    //     'namespace' => 'woocommerce',
    //     'image'     => 'woocommerce.png',
    //     'installed' => $woocommerce->is_plugin_active(),
    //     'connected' => $woocommerce->get_plugin_data('connected'),
    // ],
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

<div class="wrap">
    <h1></h1>

    <div class="thrivedesk">
        <h1><?php _e('Integrations', 'thrivedesk') ?></h1>
        <div class="row">
            <?php foreach ($plugins as $plugin) : ?>
            <div class="col">
                <div class="card card--plugin">
                    <div class="card--plugin__content">
                        <img src="<?php echo THRIVEDESK_PLUGIN_ASSETS . '/images/' . $plugin['image']; ?>" class="card--plugin__img" />
                        <?php if ($plugin['connected']) : ?>
                        <span class="installed-badge"><?php _e('Connected', 'thrivedesk'); ?></span>
                        <?php endif; ?>
                        <h2 class="card--plugin__name"><?php echo $plugin['name']; ?></h2>
                        <p class="card--plugin__excerpt">Realtime customer purchase data inside conversation.</p>
                    </div>
                    <div class="tooltip">
                        <span class="tooltip__text">
                            <?php if (!$plugin['installed']) : ?>
                            <?php _e('Please install or active this plugin first.', 'thrivedesk') ?>
                            <?php else : ?>
                            <?php _e('Connect with ThriveDesk.', 'thrivedesk'); ?>
                            <?php endif; ?>
                        </span>

                        <button data-plugin="<?php echo $plugin['namespace']; ?>" data-connected="<?php echo $plugin['connected'] ? '1' : '0'; ?>" data-nonce="<?php echo $nonce; ?>" class="connect-plugin button <?php echo $plugin['connected'] ? 'button--disconnect' : ''; ?>" <?php echo !$plugin['installed'] ? 'disabled' : '' ?>>
                            <?php _e(sprintf('%s', !$plugin['installed'] ? 'Install/Active Now' : ($plugin['connected'] ? 'Disconnect' : 'Connect Now')), 'thrivedesk'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>