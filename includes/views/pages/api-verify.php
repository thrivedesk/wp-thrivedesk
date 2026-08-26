<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<div class="thrivedesk">
  <div class="flex flex-col min-h-screen relative p-10">

    <div class="flex items-center w-full">
      <a href="https://www.thrivedesk.com/" target="_blank">
        <img src="<?php echo esc_url(THRIVEDESK_PLUGIN_ASSETS . '/images/thrivedesk.png'); ?>" alt="ThriveDesk logo" class="w-32">
      </a>
      <a href="https://help.thrivedesk.com/en/wpportal" target="_blank" class="ml-auto text-brand-light inline-block mr-5"><?php esc_html_e( 'Need help?', 'thrivedesk' ); ?></a>
    </div>

    <div class="flex flex-1 items-center justify-center py-10">
      <?php // p-0 on the card so the right column's tint reaches its rounded edge. ?>
      <div class="w-full max-w-4xl td-card p-0! overflow-hidden">
        <div class="grid grid-cols-1 md:grid-cols-2">

          <div class="p-8 space-y-5">

            <div>
              <h1 class="text-2xl font-bold m-0! p-0!"><?php esc_html_e( "Just one last step!", 'thrivedesk' ); ?></h1>
              <p class="mt-2 text-gray-500"><?php esc_html_e( 'We are excited to have you on board. Put your API key here and complete the setup.', 'thrivedesk' ); ?></p>
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

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <a href="<?php echo esc_url( \ThriveDesk\Admin::connect_url( '/auth/register' ) ); ?>" class="btn btn-primary justify-center space-x-2 px-3! text-[13px] text-white!">
                <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" color="#fff" fill="none" aria-hidden="true"><path d="M16.308 4.384c-.59 0-.886 0-1.155-.1a1.61 1.61 0 0 1-.111-.046c-.261-.12-.47-.328-.888-.746-.962-.962-1.443-1.443-2.034-1.488a1.6 1.6 0 0 0-.24 0c-.591.045-1.072.526-2.034 1.488-.418.418-.627.627-.888.746a1.602 1.602 0 0 1-.11.046c-.27.1-.565.1-1.156.1h-.11c-1.507 0-2.261 0-2.73.468-.468.469-.468 1.223-.468 2.73v.11c0 .59 0 .886-.1 1.155-.014.038-.03.075-.046.111-.12.261-.328.47-.746.888-.962.962-1.443 1.443-1.488 2.034a1.6 1.6 0 0 0 0 .24c.045.591.526 1.072 1.488 2.034.418.418.627.627.746.888.017.036.032.073.046.11.1.27.1.565.1 1.156v.11c0 1.507 0 2.261.468 2.73.469.468 1.223.468 2.73.468h.11c.59 0 .886 0 1.155.1.038.014.075.03.111.046.261.12.47.328.888.746.962.962 1.443 1.443 2.034 1.488.08.006.16.006.24 0 .591-.045 1.072-.526 2.034-1.488.418-.418.627-.627.888-.746.036-.017.073-.032.11-.046.27-.1.565-.1 1.156-.1h.11c1.507 0 2.261 0 2.73-.468.468-.469.468-1.223.468-2.73v-.11c0-.59 0-.886.1-1.155.014-.038.03-.075.046-.111.12-.261.328-.47.746-.888.962-.962 1.443-1.443 1.488-2.034.006-.08.006-.16 0-.24-.045-.591-.526-1.072-1.488-2.034-.418-.418-.627-.627-.746-.888a1.628 1.628 0 0 1-.046-.11c-.1-.27-.1-.565-.1-1.156v-.11c0-1.507 0-2.261-.468-2.73-.469-.468-1.223-.468-2.73-.468h-.11Z" stroke="currentColor" stroke-width="1.5"/><path d="M8.5 16.5a4.039 4.039 0 0 1 3.5-2.02c1.496 0 2.801.812 3.5 2.02M14 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                <span><?php esc_html_e( 'Create New Account', 'thrivedesk' ); ?></span>
              </a>
              <a href="<?php echo esc_url( \ThriveDesk\Admin::connect_url( '/auth/authorize' ) ); ?>" class="btn btn-secondary justify-center space-x-2 px-3! text-[13px]">
                <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" color="#000" fill="none" aria-hidden="true"><path d="M4.513 19.487c2.512 2.392 5.503 1.435 6.7.466.618-.501.897-.825 1.136-1.065.837-.777.784-1.555.24-2.177-.219-.249-1.616-1.591-2.956-2.967-.694-.694-1.172-1.184-1.582-1.58-.547-.546-1.026-1.172-1.744-1.154-.658 0-1.136.58-1.735 1.179-.688.688-1.196 1.555-1.375 2.333-.539 2.273.299 3.888 1.316 4.965Zm0 0L2 21.999M19.487 4.515c-2.513-2.394-5.494-1.42-6.69-.45-.62.502-.898.826-1.138 1.066-.837.778-.784 1.556-.239 2.178.078.09.31.32.635.644m7.432-3.438c1.017 1.077 1.866 2.71 1.327 4.985-.18.778-.688 1.645-1.376 2.334-.598.598-1.077 1.179-1.735 1.179-.718.018-1.09-.502-1.639-1.048m3.423-7.45L22 2m-5.936 9.964c-.41-.395-.994-.993-1.688-1.687-.858-.882-1.74-1.75-2.321-2.325m4.009 4.012-1.562 1.524m-3.99-3.983 1.543-1.553" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                <span><?php esc_html_e( 'Connect Existing Account', 'thrivedesk' ); ?></span>
              </a>
            </div>

          </div>

          <div class="flex flex-col gap-4 p-8 bg-slate-50 border-t border-slate-200 md:border-t-0 md:border-l">

            <div>
              <div class="flex items-center gap-2">
                <span class="text-blue-600 shrink-0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9.25" stroke="currentColor" stroke-width="1.5"/><path d="M12 16.5v-5M12 8h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                <div class="text-sm font-semibold text-slate-700"><?php esc_html_e( 'Allowlist our IP addresses', 'thrivedesk' ); ?></div>
              </div>
              <p class="mt-2 m-0! text-gray-500"><?php esc_html_e( "For a seamless integration, add these IP addresses to your server's firewall or security plugin.", 'thrivedesk' ); ?></p>
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
            <div class="mt-auto pt-4 border-t border-slate-200 text-gray-400 text-[12px]"><?php
              /* translators: %1$s, %3$s: opening link tags; %2$s, %4$s: closing link tags */
              printf( esc_html__( 'By continuing, you agree to the %1$sTerms of Service%2$s and %3$sPrivacy Policy%4$s.', 'thrivedesk' ), '<a href="https://www.thrivedesk.com/our/terms/" target="_blank" class="underline">', '</a>', '<a href="https://www.thrivedesk.com/our/privacy/" target="_blank" class="underline">', '</a>' ); ?></div>

          </div>

        </div>
      </div>
    </div>
  </div>
</div>
