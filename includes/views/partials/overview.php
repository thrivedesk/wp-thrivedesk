<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

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

/*
 * Three states, not two. "Connected" is not the same as "connected but the key
 * cannot read half the API" - the second used to render as empty lists with no
 * explanation, which is what the API access rows exist to make visible.
 */
$td_reachable = array_filter( $td_summary['api'], static function ( $capability ) {
	return $capability['ok'];
} );

$td_degraded = $td_summary['connected'] && count( $td_reachable ) < count( $td_summary['api'] );

if ( ! $td_summary['connected'] ) {
	$td_status = [ 'label' => __( 'Not connected', 'thrivedesk' ), 'dot' => 'bg-rose-500', 'text' => 'text-rose-600' ];
} elseif ( $td_degraded ) {
	$td_status = [ 'label' => __( 'Connected, with limits', 'thrivedesk' ), 'dot' => 'bg-amber-500', 'text' => 'text-amber-600' ];
} else {
	$td_status = [ 'label' => __( 'Connected', 'thrivedesk' ), 'dot' => 'bg-green-500', 'text' => 'text-green-600' ];
}
?>
<div class="space-y-6">

    <?php // Portal leads at two thirds; the workspace facts sit beside it as a reference column. ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="td-card lg:col-span-2">

        <div style="position:relative;padding-top:56.25%;"><iframe src="https://iframe.mediadelivery.net/embed/10114/9f38fded-ddd9-44ba-bdfe-7d362235d40c?autoplay=false&loop=true&muted=true&preload=true&responsive=true" loading="lazy" style="border:0;position:absolute;top:0;height:100%;width:100%;" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;" allowfullscreen="true"></iframe></div>
        <h3 class="text-lg font-medium my-4"><?php esc_html_e('What is Portal?', 'thrivedesk'); ?></h3>
        <p><?php esc_html_e('Embed Help Center into your site that won’t make any database calls, no extra plugins dependency.', 'thrivedesk'); ?></p>
        <a href="https://www.thrivedesk.com/wordpress/" target="_blank" class="mt-2 inline-block"><?php esc_html_e('Learn more', 'thrivedesk'); ?></a>
        </div>

        <div class="td-card">
            <div class="text-base font-bold mb-3"><?php esc_html_e( 'Workspace', 'thrivedesk' ); ?></div>
        <?php
        // Uppercasing is a CSS class, never baked into the string: plenty of
        // languages have no case at all, and the ones that do do not all
        // uppercase the way English does.
        $td_label = 'text-[11px] font-semibold uppercase text-slate-400 whitespace-nowrap';
        ?>
        <?php
        /*
         * A two-column grid rather than a fixed label width. `auto` sizes the
         * first column to the widest label there actually is, so every value
         * lines up without a magic number that a longer translation would
         * overflow. The spans are direct children on purpose - wrapping a row
         * in a div would take it out of the grid.
         */
        ?>
        <div class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-2 items-baseline">
            <span class="<?php echo esc_attr( $td_label ); ?>"><?php esc_html_e( 'Workspace:', 'thrivedesk' ); ?></span>
            <span class="text-sm text-slate-800">
                <?php echo esc_html( $td_workspace['name'] ? $td_workspace['name'] : __( 'Not connected', 'thrivedesk' ) ); ?>
            </span>

            <?php if ( $td_plan ) : ?>
                <span class="<?php echo esc_attr( $td_label ); ?>"><?php esc_html_e( 'Plan:', 'thrivedesk' ); ?></span>
                <span class="flex items-center flex-wrap gap-2 text-sm text-slate-800">
                    <?php echo esc_html( $td_plan['label'] ); ?>
                    <?php if ( $td_plan['billing_type'] ) : ?>
                        <span class="py-0.5 px-2 bg-slate-100 text-slate-600 text-[11px] rounded-full whitespace-nowrap">
                            <?php echo esc_html( $td_plan['billing_type'] ); ?>
                        </span>
                    <?php endif; ?>
                </span>

                <?php // Answers up front what the Portal tab would otherwise only reveal by being empty. ?>
                <span class="<?php echo esc_attr( $td_label ); ?>"><?php esc_html_e( 'Portal:', 'thrivedesk' ); ?></span>
                <span class="text-sm <?php echo $td_plan['portal'] ? 'text-green-600' : 'text-gray-500'; ?>">
                    <?php echo $td_plan['portal']
                        ? esc_html__( 'Included', 'thrivedesk' )
                        : esc_html__( 'Not included', 'thrivedesk' ); ?>
                </span>

                <?php if ( $td_plan['expired'] ) : ?>
                    <span class="col-span-2 text-[12px] text-rose-600"><?php esc_html_e( 'Subscription expired', 'thrivedesk' ); ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php if ( $td_summary['api'] ) : ?>
            <div class="pt-3 border-t border-slate-200">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400"><?php esc_html_e( 'API access', 'thrivedesk' ); ?></div>
                <ul class="mt-2! mb-0! p-0! list-none grid grid-cols-2 gap-x-4 gap-y-1">
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
    </div>

    <?php // Connection details, with the status of that connection stated on the same card. ?>
    <div class="td-card space-y-4">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="text-base font-bold"><?php esc_html_e( 'Connection details', 'thrivedesk' ); ?></div>
                <p class="mt-1! mb-0! text-gray-500"><?php esc_html_e( 'The API key that links this site to ThriveDesk.', 'thrivedesk' ); ?></p>
            </div>
            <span class="inline-flex items-center gap-2 text-sm font-medium <?php echo esc_attr( $td_status['text'] ); ?>">
                <span class="w-2 h-2 rounded-full <?php echo esc_attr( $td_status['dot'] ); ?>" aria-hidden="true"></span>
                <?php echo esc_html( $td_status['label'] ); ?>
            </span>
        </div>

        <div class="pt-4 border-t border-slate-200">
            <div class="space-y-2">
                <label for="td_helpdesk_api_key" class="block mb-2 text-sm font-medium text-gray-900"><?php esc_html_e('API Key', 'thrivedesk'); ?></label>
                <span>
                    <?php esc_html_e('Login to ThriveDesk app and get your API key from ', 'thrivedesk'); ?>
                    <a class="text-blue-500" href="<?php echo esc_url(THRIVEDESK_APP_URL . '/settings/company/api-key'); ?>" target="_blank">
                        <?php esc_html_e('here', 'thrivedesk'); ?>
                    </a>
                </span>
                <?php
                // type="password" hides the key on screen and nowhere else: it
                // is still in view-source, in the DOM, in password managers, on
                // a screen share, and one selector away from any script on the
                // page. Only a preview is rendered, and the editable field is
                // left blank - submitting it empty means "unchanged".
                ?>
                <div class="flex items-center api-key-preview">
                    <input class="truncate w-2/3 bg-gray-50" type="text" disabled value="<?php echo esc_attr($td_api_key_preview); ?>" />
                    <span class="text-green-500 underline hover:text-green-600 px-2 cursor-pointer trigger"><?php esc_html_e('Update', 'thrivedesk'); ?></span>
                </div>
                <div class="api-key-editable hidden">
                    <input type="password" id="td_helpdesk_api_key" name="td_helpdesk_api_key" value="<?php echo esc_attr($td_connect_token); ?>" placeholder="<?php echo esc_attr($td_api_key_preview); ?>" autocomplete="off" class="block p-2.5 w-full text-sm" />

                    <button type="button" class="btn btn-primary py-1.5 mt-3 bg-green-500 hover:bg-green-600" id="td-api-verification-btn">
                        <?php esc_html_e('Verify', 'thrivedesk'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="td-card bg-orange-50 border border-orange-400 space-y-2">
        <img class="w-36 ml-auto" src="<?php echo esc_url(THRIVEDESK_PLUGIN_ASSETS . "/images/cloudflare-logo.svg"); ?>">
        <h3 class="text-lg font-medium"><?php esc_html_e('Using Cloudflare?', 'thrivedesk'); ?></h3>
        <p><?php esc_html_e('To ensure seamless integration, add the ThriveDesk IP address to your Cloudflare whitelist; without this, WooCommerce and other plugins may not integrate correctly.', 'thrivedesk');?></p>
        <a href="https://help.thrivedesk.com/en/troubleshooting-with-cloudflare" target="_blank" class="inline-block"><?php esc_html_e('Learn more', 'thrivedesk'); ?></a>
    </div>
    <div class="td-card">
        <img src="<?php echo esc_url(THRIVEDESK_PLUGIN_ASSETS . '/images/livechat-hero.jpg'); ?>" alt="<?php esc_attr_e('Assistant', 'thrivedesk'); ?>">
        <h3 class="text-lg font-medium my-4"><?php esc_html_e('What is Assistant?', 'thrivedesk'); ?></h3>
        <p><?php esc_html_e('Enable Live Chat, Knowledge base and Contact form in a simple widget called Assistant.', 'thrivedesk'); ?></p>
        <a href="https://www.thrivedesk.com/live-chat/" target="_blank" class="mt-2 inline-block"><?php esc_html_e('Learn more', 'thrivedesk'); ?></a>
    </div>
    </div>
</div>
