<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<?php
$td_summary    = \ThriveDesk\Services\WorkspaceService::summary();
$td_workspace  = $td_summary['workspace'];
$td_plan       = $td_summary['plan'];
$td_capability = [
	'account'       => __( 'Account', 'thrivedesk' ),
	'billing'       => __( 'Billing', 'thrivedesk' ),
	'assistants'    => __( 'Assistants', 'thrivedesk' ),
	'inboxes'       => __( 'Inboxes', 'thrivedesk' ),
	'knowledgebase' => __( 'Knowledge base', 'thrivedesk' ),
];
?>
<div class="sidebar space-y-6">
    <!-- workspace  -->
    <div class="td-card space-y-4">
        <?php
        // Uppercasing is a CSS class, never baked into the string: plenty of
        // languages have no case at all, and the ones that do do not all
        // uppercase the way English does.
        $td_label = 'text-[11px] font-semibold uppercase tracking-wider text-slate-400 shrink-0';
        ?>
        <div class="space-y-2">
            <div class="flex items-baseline justify-between gap-3">
                <span class="<?php echo esc_attr( $td_label ); ?>"><?php esc_html_e( 'Workspace:', 'thrivedesk' ); ?></span>
                <span class="text-sm text-slate-800 text-right">
                    <?php echo esc_html( $td_workspace['name'] ? $td_workspace['name'] : __( 'Not connected', 'thrivedesk' ) ); ?>
                </span>
            </div>

            <?php if ( $td_plan ) : ?>
                <div class="flex items-center justify-between gap-3">
                    <span class="<?php echo esc_attr( $td_label ); ?>"><?php esc_html_e( 'Plan:', 'thrivedesk' ); ?></span>
                    <span class="flex items-center justify-end flex-wrap gap-2 text-sm text-slate-800 text-right">
                        <?php echo esc_html( $td_plan['label'] ); ?>
                        <?php if ( $td_plan['billing_type'] ) : ?>
                            <span class="py-0.5 px-2 bg-slate-100 text-slate-600 text-[11px] rounded-full whitespace-nowrap">
                                <?php echo esc_html( $td_plan['billing_type'] ); ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>

                <?php // Answers up front what the Portal tab would otherwise only reveal by being empty. ?>
                <div class="flex items-baseline justify-between gap-3">
                    <span class="<?php echo esc_attr( $td_label ); ?>"><?php esc_html_e( 'Portal:', 'thrivedesk' ); ?></span>
                    <span class="text-sm text-right <?php echo $td_plan['portal'] ? 'text-green-600' : 'text-gray-500'; ?>">
                        <?php echo $td_plan['portal']
                            ? esc_html__( 'Included', 'thrivedesk' )
                            : esc_html__( 'Not included', 'thrivedesk' ); ?>
                    </span>
                </div>

                <?php if ( $td_plan['expired'] ) : ?>
                    <div class="text-[12px] text-rose-600 text-right"><?php esc_html_e( 'Subscription expired', 'thrivedesk' ); ?></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ( $td_summary['api'] ) : ?>
            <div class="pt-3 border-t border-slate-200">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400"><?php esc_html_e( 'API access', 'thrivedesk' ); ?></div>
                <ul class="m-0! p-0! list-none mt-2 space-y-1">
                    <?php foreach ( $td_capability as $td_key => $td_label ) : ?>
                        <?php
                        if ( ! isset( $td_summary['api'][ $td_key ] ) ) {
                            continue;
                        }

                        $td_state = $td_summary['api'][ $td_key ];
                        ?>
                        <li class="flex items-center gap-2 text-[13px]">
                            <span class="<?php echo $td_state['ok'] ? 'text-green-600' : 'text-rose-500'; ?>" aria-hidden="true">
                                <?php echo $td_state['ok'] ? '&#10003;' : '&#10005;'; ?>
                            </span>
                            <span class="text-slate-700"><?php echo esc_html( $td_label ); ?></span>
                            <?php if ( ! $td_state['ok'] ) : ?>
                                <span class="ml-auto text-[11px] text-gray-400">
                                    <?php
                                    // A status beats a word here: 403 is a permission the key lacks,
                                    // 0 is never having reached ThriveDesk at all.
                                    echo esc_html( $td_state['status'] ? (string) $td_state['status'] : __( 'unreachable', 'thrivedesk' ) );
                                    ?>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

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
