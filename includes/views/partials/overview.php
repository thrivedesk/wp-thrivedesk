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
		 * One row, and it is the whole tab: the one thing to do on the left, and
		 * everything that answers "why would I" stacked beside it. The tour and
		 * what the widget is were three rows down before, under the fold, behind
		 * the card asking for a key.
		 *
		 * is-inline lifts the width cap the setup screen needs - there the card is
		 * alone on the page and centred, here it has a column to fill.
		 */
		?>
		<div class="grid grid-cols-1 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] gap-6 items-start">
			<?php thrivedesk_view( 'partials/connect-card', [ 'td_connect_class' => 'is-inline' ] ); ?>

			<div class="space-y-6">
				<?php thrivedesk_view( 'partials/card-assistant' ); ?>
				<?php thrivedesk_view( 'partials/card-portal', [ 'td_portal_narrow' => true ] ); ?>
				<?php thrivedesk_view( 'partials/card-workspace' ); ?>
			</div>
		</div>

	<?php else : ?>

		<?php // Portal leads at two thirds; the workspace facts sit beside it as a reference column. ?>
		<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
			<div class="lg:col-span-2">
				<?php thrivedesk_view( 'partials/card-portal' ); ?>
			</div>

			<?php thrivedesk_view( 'partials/card-workspace' ); ?>
		</div>

	    <?php // Connection details, with the status of that connection stated on the same card. ?>
	    <div class="td-card space-y-4">
	        <div class="flex items-center justify-between gap-3 flex-wrap">
	            <div>
	                <div class="text-base font-bold"><?php esc_html_e( 'Connection details', 'thrivedesk' ); ?></div>
	                <p class="mt-1! mb-0! text-gray-500"><?php esc_html_e( 'The API key that links this site to ThriveDesk.', 'thrivedesk' ); ?></p>
	            </div>
	            <div class="flex items-center gap-3">
	                <span class="inline-flex items-center gap-2 text-sm font-medium <?php echo esc_attr( $td_status['text'] ); ?>">
	                    <span class="w-2 h-2 rounded-full <?php echo esc_attr( $td_status['dot'] ); ?>" aria-hidden="true"></span>
	                    <?php echo esc_html( $td_status['label'] ); ?>
	                </span>

	                <?php
	                /*
	                 * Beside the status it undoes. Labelled, not icon-only: a
	                 * bare x next to a status pill reads as "dismiss this
	                 * message" at least as easily as "disconnect", and this is
	                 * the one control on the screen that must not be guessed at.
	                 *
	                 * What it costs is spelled out in the confirmation, which is
	                 * where anyone is going to read it - see the
	                 * #td-disconnect-account handler in admin.js.
	                 */
	                ?>
	                <button
	                    type="button"
	                    class="btn-danger"
	                    id="td-disconnect-account"
	                    title="<?php esc_attr_e( 'Disconnect this site from ThriveDesk', 'thrivedesk' ); ?>"
	                >
	                    <span class="btn-danger__icon" aria-hidden="true">
	                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none">
	                            <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
	                        </svg>
	                    </span>
	                    <span><?php esc_html_e( 'Disconnect', 'thrivedesk' ); ?></span>
	                </button>
	            </div>
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

		<?php
		/*
		 * Allowlisting is troubleshooting for a connection that exists. Before
		 * there is one it is noise - and the IP addresses it is really about are
		 * already on the connect card, behind its Additional Info rail.
		 */
		?>
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

			<?php thrivedesk_view( 'partials/card-assistant' ); ?>
		</div>

	<?php endif; ?>
</div>
