<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="sidebar space-y-6">
    <!-- cloudflare  -->
    <div class="td-card bg-orange-50 border border-orange-400 space-y-2">
        <img class="w-36 ml-auto" src="<?php echo esc_url(THRIVEDESK_PLUGIN_ASSETS . "/images/cloudflare-logo.svg"); ?>">
        <h3 class="text-lg font-medium"><?php esc_html_e('Using Cloudflare?', 'thrivedesk'); ?></h3>
        <p><?php esc_html_e('To ensure seamless integration, add the ThriveDesk IP address to your Cloudflare whitelist; without this, WooCommerce and other plugins may not integrate correctly.', 'thrivedesk');?></p>
        <a href="https://help.thrivedesk.com/en/troubleshooting-with-cloudflare" target="_blank" class="inline-block"><?php esc_html_e('Learn more', 'thrivedesk'); ?></a>
    </div>
    <!-- Assistant  -->
    <div class="td-card">
        <img src="<?php echo esc_url(THRIVEDESK_PLUGIN_ASSETS . '/images/livechat-hero.jpg'); ?>" alt="<?php esc_attr_e('Assistant', 'thrivedesk'); ?>">
        <h3 class="text-lg font-medium my-4"><?php esc_html_e('What is Assistant?', 'thrivedesk'); ?></h3>
        <p><?php esc_html_e('Enable Live Chat, Knowledge base and Contact form in a simple widget called Assistant.', 'thrivedesk'); ?></p>
        <a href="https://www.thrivedesk.com/live-chat/" target="_blank" class="mt-2 inline-block"><?php esc_html_e('Learn more', 'thrivedesk'); ?></a>
    </div>
    <!-- wpportal  -->
    <div class="td-card">
        <div style="position:relative;padding-top:56.25%;"><iframe src="https://iframe.mediadelivery.net/embed/10114/9f38fded-ddd9-44ba-bdfe-7d362235d40c?autoplay=false&loop=true&muted=true&preload=true&responsive=true" loading="lazy" style="border:0;position:absolute;top:0;height:100%;width:100%;" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;" allowfullscreen="true"></iframe></div>
        <h3 class="text-lg font-medium my-4"><?php esc_html_e('What is Portal?', 'thrivedesk'); ?></h3>
        <p><?php esc_html_e('Embed Help Center into your site that won’t make any database calls, no extra plugins dependency.', 'thrivedesk'); ?></p>
        <a href="https://www.thrivedesk.com/wordpress/" target="_blank" class="mt-2 inline-block"><?php esc_html_e('Learn more', 'thrivedesk'); ?></a>
    </div>
</div>
