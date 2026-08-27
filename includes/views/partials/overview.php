<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$td_connected = thrivedesk_is_connected();
// See partials/workspace-card: summary() is five HTTP requests when cold, and
// there is nothing worth asking about until this site has a key that works.
$td_summary   = $td_connected
	? \ThriveDesk\Services\WorkspaceService::summary()
	: [ 'connected' => false, 'workspace' => [ 'name' => '' ], 'plan' => null, 'api' => [] ];

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

	<?php if ( ! $td_connected ) : ?>
		<?php
		/*
		 * The first thing on the page until there is a key, because nothing
		 * below it does anything without one. Centred rather than stretched:
		 * this is the same card as the setup screen, and it widens on its own
		 * when the reference rail is opened.
		 */
		?>
		<div class="flex justify-center">
			<?php thrivedesk_view( 'partials/connect-card' ); ?>
		</div>
	<?php endif; ?>

    <?php // Portal leads at two thirds; the workspace facts sit beside it as a reference column. ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="td-card lg:col-span-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                <div>
                    <h3 class="text-lg font-semibold m-0!"><?php esc_html_e( 'Overview of WPPortal', 'thrivedesk' ); ?></h3>
                    <p class="mt-2! mb-0! text-gray-500">
                        <?php esc_html_e( 'Portal puts a help centre on your own site: customers sign in with the account they already have, read your knowledge base, open a ticket and follow it to a reply - without leaving your domain.', 'thrivedesk' ); ?>
                    </p>
                    <a class="btn-ghost mt-4" href="https://www.thrivedesk.com/wordpress/" target="_blank">
                        <span><?php esc_html_e( 'Learn more', 'thrivedesk' ); ?></span>
                        <?php thrivedesk_view( 'icons/external' ); ?>
                    </a>
                </div>

                <?php
                /*
                 * A button, not a thumbnail with a click handler: this opens
                 * something, so it should be reachable by keyboard and announce
                 * itself. The embed URL rides on a data attribute and is only
                 * given to an iframe when the dialog opens, so the page does not
                 * pull a video player it may never need.
                 */
                ?>
                <button
                    type="button"
                    class="td-video"
                    data-td-video="<?php echo esc_url( 'https://iframe.mediadelivery.net/embed/10114/9f38fded-ddd9-44ba-bdfe-7d362235d40c?autoplay=true&responsive=true' ); ?>"
                    data-td-video-title="<?php esc_attr_e( 'Overview of WPPortal', 'thrivedesk' ); ?>"
                >
                    <?php
                    /*
                     * The mockup is drawn, not photographed. There is no picture
                     * of the portal in the bundled assets and the embed host's
                     * thumbnail URL is not something to guess at, so this is a
                     * suggestion of the product rather than a claim to be a
                     * screenshot of it.
                     */
                    ?>
                    <span class="td-video__mockup" aria-hidden="true">
                        <svg viewBox="0 0 320 200" width="100%" height="100%" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="8" y="8" width="304" height="184" rx="8" fill="#fff" fill-opacity=".97"/>
                            <rect x="8" y="8" width="304" height="22" rx="8" fill="#eef2ff"/>
                            <rect x="8" y="24" width="304" height="6" fill="#eef2ff"/>
                            <circle cx="20" cy="19" r="3" fill="#cbd5e1"/><circle cx="30" cy="19" r="3" fill="#cbd5e1"/><circle cx="40" cy="19" r="3" fill="#cbd5e1"/>
                            <rect x="92" y="46" width="136" height="14" rx="7" fill="#1e293b" fill-opacity=".12"/>
                            <rect x="60" y="72" width="200" height="20" rx="10" fill="#3858e9" fill-opacity=".10" stroke="#3858e9" stroke-opacity=".25"/>
                            <circle cx="74" cy="82" r="4" stroke="#3858e9" stroke-opacity=".55" stroke-width="1.5"/>
                            <path d="m77 85 3 3" stroke="#3858e9" stroke-opacity=".55" stroke-width="1.5" stroke-linecap="round"/>
                            <rect x="60" y="106" width="92" height="34" rx="5" fill="#f1f5f9"/>
                            <rect x="168" y="106" width="92" height="34" rx="5" fill="#f1f5f9"/>
                            <rect x="68" y="114" width="52" height="5" rx="2.5" fill="#94a3b8"/>
                            <rect x="68" y="125" width="72" height="4" rx="2" fill="#cbd5e1"/>
                            <rect x="176" y="114" width="52" height="5" rx="2.5" fill="#94a3b8"/>
                            <rect x="176" y="125" width="72" height="4" rx="2" fill="#cbd5e1"/>
                            <rect x="60" y="152" width="200" height="28" rx="5" fill="#f8fafc"/>
                            <rect x="68" y="162" width="110" height="5" rx="2.5" fill="#cbd5e1"/>
                            <rect x="222" y="159" width="30" height="12" rx="6" fill="#3858e9" fill-opacity=".2"/>
                        </svg>
                    </span>
                    <span class="td-video__play" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22"><path d="M8 5.5v13l11-6.5-11-6.5Z" fill="currentColor"/></svg>
                    </span>
                    <span class="td-video__label"><?php esc_html_e( 'Watch the 40 second tour', 'thrivedesk' ); ?></span>
                </button>
            </div>
        </div>

        <?php
        /*
         * Carries an id because it is the one card that is replaced in place:
         * connecting from the card above re-renders this body over AJAX rather
         * than reloading the page out from under the celebration.
         */
        ?>
        <div class="td-card" id="td-workspace-card">
            <div id="td-workspace-card-body">
                <?php thrivedesk_view( 'partials/workspace-card' ); ?>
            </div>
        </div>
    </div>

	<?php if ( $td_connected ) : ?>
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
	<?php endif; ?>

    <?php // Image left, content right - the same shape as the Portal card above. ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
        <div class="td-card bg-orange-50 border border-orange-400">
            <div class="flex items-start gap-4">
                <img class="w-24 shrink-0 mt-1" src="<?php echo esc_url( THRIVEDESK_PLUGIN_ASSETS . '/images/cloudflare-logo.svg' ); ?>" alt="Cloudflare">
                <div>
                    <h3 class="text-base font-semibold m-0!"><?php esc_html_e( 'Using Cloudflare?', 'thrivedesk' ); ?></h3>
                    <p class="mt-2! mb-0! text-gray-600"><?php esc_html_e( 'Add the ThriveDesk IP addresses to your Cloudflare allowlist. Without them, WooCommerce and the other integrations may not be reachable.', 'thrivedesk' ); ?></p>
                    <a class="btn-ghost mt-4" href="https://help.thrivedesk.com/en/troubleshooting-with-cloudflare" target="_blank">
                        <span><?php esc_html_e( 'Learn more', 'thrivedesk' ); ?></span>
                        <?php thrivedesk_view( 'icons/external' ); ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="td-card">
            <div class="flex items-start gap-4">
                <img class="w-24 h-20 shrink-0 rounded object-cover mt-1" src="<?php echo esc_url( THRIVEDESK_PLUGIN_ASSETS . '/images/livechat-hero.jpg' ); ?>" alt="">
                <div>
                    <h3 class="text-base font-semibold m-0!"><?php esc_html_e( 'What is Assistant?', 'thrivedesk' ); ?></h3>
                    <p class="mt-2! mb-0! text-gray-500"><?php esc_html_e( 'Live chat, knowledge base and a contact form in one widget, shown on the pages you choose.', 'thrivedesk' ); ?></p>
                    <a class="btn-ghost mt-4" href="https://www.thrivedesk.com/live-chat/" target="_blank">
                        <span><?php esc_html_e( 'Learn more', 'thrivedesk' ); ?></span>
                        <?php thrivedesk_view( 'icons/external' ); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
