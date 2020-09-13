<?php

use ThriveDesk\Plugins\EDD;
use ThriveDesk\Plugins\SmartPay;
use ThriveDesk\Plugins\WooCommerce;

$thrivedesk_options = thrivedesk_options();
$api_token = $thrivedesk_options['api_token'] ?? '';
$nonce = wp_create_nonce('thrivedesk-connect-plugin');
?>

<div class="wrap">
    <h1><?php _e('Thrive Desk Settings', 'thrivedesk') ?></h1>

    <div class="thrivedesk">
        <table>
            <tbody>
                <tr>
                    <th><?php _e('Plugin', 'thrivedesk') ?></th>
                    <th><?php _e('Activated', 'thrivedesk') ?></th>
                    <th><?php _e('Connected', 'thrivedesk') ?></th>
                </tr>
                <tr class="edd">
                    <td> <?php _e('Easy Digital Download', 'thrivedesk'); ?></td>
                    <td> <?php _e(sprintf('%s', EDD::is_plugin_active() ? 'Yes' : 'No'), 'thrivedesk'); ?></td>
                    <td>
                        <button type="button" data-nonce="<?php echo $nonce; ?>" data-plugin="edd" class="connect-plugin"><?php _e('Connect', 'thrivedesk'); ?></button>
                    </td>
                </tr>
                <tr>
                    <td> <?php _e('WooCommerce', 'thrivedesk'); ?></td>
                    <td> <?php _e(sprintf('%s', WooCommerce::is_plugin_active() ? 'Yes' : 'No'), 'thrivedesk'); ?></td>
                    <td>
                        <button type="button" data-nonce="<?php echo $nonce; ?>" data-plugin="woocommerce" class="connect-plugin"><?php _e('Connect', 'thrivedesk'); ?></button>
                    </td>
                </tr>
                <tr>
                    <td> <?php _e('SmartPay', 'thrivedesk'); ?></td>
                    <td> <?php _e(sprintf('%s', SmartPay::is_plugin_active() ? 'Yes' : 'No'), 'thrivedesk'); ?></td>
                    <td>
                        <button type="button" data-nonce="<?php echo $nonce; ?>" data-plugin="smartpay" class="connect-plugin"><?php _e('Connect', 'thrivedesk'); ?></button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>