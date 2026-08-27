<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * The card that connects this site to ThriveDesk.
 *
 * One partial, two homes: the standalone setup screen and the first row of the
 * Overview tab before a key exists. They were the same card typed out twice,
 * which is how the setup screen and the settings screen ended up offering
 * different ways to do the same thing.
 *
 * `is-open` on the wrapper is what both widens the card and opens the right
 * rail - see the .td-split rules and the .td-aside-toggle handler.
 */
?>
<div id="td-setup-split" class="td-card td-split p-0! overflow-hidden">

      <div class="p-8 space-y-5">

        <div>
          <h1 class="text-2xl font-bold m-0! p-0!"><?php esc_html_e( "Just one last step!", 'thrivedesk' ); ?></h1>
          <p class="mt-2 text-gray-500"><?php esc_html_e( 'Add your API key to connect this site to ThriveDesk.', 'thrivedesk' ); ?></p>
        </div>

        <div class="space-y-1.5">
          <label for="td_helpdesk_api_key" class="block text-sm font-semibold text-slate-700"><?php esc_html_e( 'API Key', 'thrivedesk' ); ?></label>
          <?php // Only a token from an authorization this site started pre-fills the field; see Admin::connect_return_token(). ?>
          <input type="password" id="td_helpdesk_api_key" autocomplete="off" spellcheck="false" class="w-full p-2! border border-slate-300! shadow-sm rounded" placeholder="<?php esc_attr_e( 'Enter your API Key', 'thrivedesk' ); ?>" value="<?php echo esc_attr( \ThriveDesk\Admin::connect_return_token() ); ?>"/>
        </div>

        <?php // Black, so the one action that finishes setup does not read as a peer of the two account links below. ?>
        <button id="submit-btn" class="btn btn-dark w-full justify-center">
          <span><?php esc_html_e( 'Complete Setup', 'thrivedesk' ); ?></span>
          <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" color="#fff" fill="none" aria-hidden="true"><path d="m14.527 18-1.408-1.414L16.689 13H3.5v-2h13.189l-3.57-3.587L14.527 6l5.973 6-5.973 6Z" fill="currentColor"/></svg></span>
        </button>

        <?php // aria-hidden goes on the rules, not the wrapper - the label is real content. ?>
        <div class="flex items-center gap-3">
          <span class="h-px flex-1 bg-slate-200" aria-hidden="true"></span>
          <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400"><?php esc_html_e( "Don't have a key yet?", 'thrivedesk' ); ?></span>
          <span class="h-px flex-1 bg-slate-200" aria-hidden="true"></span>
        </div>

        <?php thrivedesk_view( 'partials/connect-accounts' ); ?>

      </div>

      <aside class="td-aside">

        <button type="button" class="td-aside-toggle" aria-expanded="false" aria-controls="td-setup-aside-panel">
          <span><?php esc_html_e( 'Additional Info - click to expand', 'thrivedesk' ); ?></span>
        </button>

        <div id="td-setup-aside-panel" class="td-aside-panel">

        <div>
          <div class="flex items-center gap-2">
            <span class="text-blue-600 shrink-0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9.25" stroke="currentColor" stroke-width="1.5"/><path d="M12 16.5v-5M12 8h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
            <div class="text-sm font-semibold text-slate-700"><?php esc_html_e( 'Allowlist our IP addresses', 'thrivedesk' ); ?></div>
            <?php // The rail is gone once the panel is open, so the way back lives here. ?>
            <button type="button" class="td-aside-close ml-auto" aria-label="<?php esc_attr_e( 'Collapse additional info', 'thrivedesk' ); ?>" title="<?php esc_attr_e( 'Collapse additional info', 'thrivedesk' ); ?>">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </button>
          </div>
          <p class="mt-2! mb-0! text-gray-500"><?php esc_html_e( "For a seamless integration, add these IP addresses to your server's firewall or security plugin.", 'thrivedesk' ); ?></p>
        </div>

        <ul class="m-0! p-0! list-none space-y-2">
          <?php foreach ( thrivedesk_service_ips() as $td_service_ip ) : ?>
            <li class="td-ip-row">
              <code class="font-mono text-[13px] select-all"><?php echo esc_html( $td_service_ip ); ?></code>
              <button
                type="button"
                class="td-copy"
                data-td-copy="<?php echo esc_attr( $td_service_ip ); ?>"
                title="<?php echo esc_attr(
                  /* translators: %s: an IP address */
                  sprintf( __( 'Copy %s', 'thrivedesk' ), $td_service_ip )
                ); ?>"
                aria-label="<?php echo esc_attr(
                  /* translators: %s: an IP address */
                  sprintf( __( 'Copy %s', 'thrivedesk' ), $td_service_ip )
                ); ?>"
              >
                <span class="td-copy-idle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" fill="none" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2.5" stroke="currentColor" stroke-width="1.5"/><path d="M15 6V5.5A2.5 2.5 0 0 0 12.5 3h-6A2.5 2.5 0 0 0 4 5.5v6A2.5 2.5 0 0 0 6.5 14H7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                <span class="td-copy-done"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" fill="none" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
              </button>
            </li>
          <?php endforeach; ?>
        </ul>

        <?php // Announces the copy result to screen readers; the icon swap alone is silent. ?>
        <span id="td-copy-status" class="sr-only" role="status" aria-live="polite"></span>

        <p class="m-0! text-gray-500"><?php
          /* translators: %1$s: opening link tag, %2$s: closing link tag */
          printf( esc_html__( 'Still stuck? Email us at %1$shelp@thrivedesk.com%2$s with your site URL and IP address.', 'thrivedesk' ), '<a href="mailto:help@thrivedesk.com" class="underline">', '</a>' ); ?>
        </p>

        <?php // mt-auto pins this to the foot of the column however tall the left side runs. ?>
        <?php // A <p>, so WordPress sizes it exactly like the paragraphs above it
              // rather than us guessing at the value it applies. Margins need
              // forcing for the same reason. ?>
        <p class="mt-auto! mb-0! pt-4 border-t border-slate-200 text-gray-500"><?php
          /* translators: %1$s, %3$s: opening link tags; %2$s, %4$s: closing link tags */
          printf( esc_html__( 'By continuing, you agree to the %1$sTerms of Service%2$s and %3$sPrivacy Policy%4$s.', 'thrivedesk' ), '<a href="https://www.thrivedesk.com/our/terms/" target="_blank" class="td-inline-link">', '</a>', '<a href="https://www.thrivedesk.com/our/privacy/" target="_blank" class="td-inline-link">', '</a>' ); ?></p>

        </div>

      </aside>

</div>
