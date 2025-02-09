<div class="flex flex-col h-screen relative p-10">

  <div class="flex items-center w-full">
    <a href="https://www.thrivedesk.com/" target="_blank">
      <img src="<?php echo THRIVEDESK_PLUGIN_ASSETS . '/images/thrivedesk.png' ?>" alt="ThriveDesk logo" class="w-32">
    </a>
    <a href="#" class="ml-auto text-brand-light inline-block mr-5">Need help?</a>
  </div>

  
  <div class="flex items-center justify-center mt-48">
    <div class="w-2/4 2xl:w-1/3 space-y-4">
      <div class="td-card-heading">
        <div class="text-2xl font-bold"><?php esc_html_e( "Just one last step!", 'wp-thrivedesk' ); ?></div>
        <p class="mt-2 muted"><?php esc_html_e( 'We are excited to have you on board. Put your API key here and complete the setup', 'wp-thrivedesk' ); ?></p>
      </div>
      <div class="td-card space-y-4">
        <input type="password" id="td_helpdesk_api_key" class="w-full p-2 border border-gray-300 shadow-sm rounded" placeholder="Enter your API Key" value="<?php echo isset($_GET['token']) ? esc_html(sanitize_text_field($_GET['token'])) : ''; ?>"/>
        <button id="submit-btn" class="btn btn-primary w-full justify-center">
          <span><?php esc_html_e( 'Complete Setup', 'wp-thrivedesk' ); ?></span>
          <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" color="#fff" fill="none"><path d="m14.527 18-1.408-1.414L16.689 13H3.5v-2h13.189l-3.57-3.587L14.527 6l5.973 6-5.973 6Z" fill="currentColor"/></svg></span>
        </button>
        
        <div class="text-gray-400 text-[12px]">By continuing, you agree to the <a href="https://www.thrivedesk.com/our/terms/" target="_blank" class="underline">Terms of Service</a> and <a href="https://www.thrivedesk.com/our/privacy/" target="_blank" class="underline">Privacy Policy</a>.</div>
      </div>

        <div class="flex items-center space-x-2">
            <a href="<?php echo THRIVEDESK_APP_URL . '/auth/register?auth_return_url=' . get_bloginfo('url') . '/wp-admin/admin.php?page=thrivedesk&auth_platform=WordPress'?>" class="btn btn-primary">
                <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" color="#fff" fill="none"><path d="M16.308 4.384c-.59 0-.886 0-1.155-.1a1.61 1.61 0 0 1-.111-.046c-.261-.12-.47-.328-.888-.746-.962-.962-1.443-1.443-2.034-1.488a1.6 1.6 0 0 0-.24 0c-.591.045-1.072.526-2.034 1.488-.418.418-.627.627-.888.746a1.602 1.602 0 0 1-.11.046c-.27.1-.565.1-1.156.1h-.11c-1.507 0-2.261 0-2.73.468-.468.469-.468 1.223-.468 2.73v.11c0 .59 0 .886-.1 1.155-.014.038-.03.075-.046.111-.12.261-.328.47-.746.888-.962.962-1.443 1.443-1.488 2.034a1.6 1.6 0 0 0 0 .24c.045.591.526 1.072 1.488 2.034.418.418.627.627.746.888.017.036.032.073.046.11.1.27.1.565.1 1.156v.11c0 1.507 0 2.261.468 2.73.469.468 1.223.468 2.73.468h.11c.59 0 .886 0 1.155.1.038.014.075.03.111.046.261.12.47.328.888.746.962.962 1.443 1.443 2.034 1.488.08.006.16.006.24 0 .591-.045 1.072-.526 2.034-1.488.418-.418.627-.627.888-.746.036-.017.073-.032.11-.046.27-.1.565-.1 1.156-.1h.11c1.507 0 2.261 0 2.73-.468.468-.469.468-1.223.468-2.73v-.11c0-.59 0-.886.1-1.155.014-.038.03-.075.046-.111.12-.261.328-.47.746-.888.962-.962 1.443-1.443 1.488-2.034.006-.08.006-.16 0-.24-.045-.591-.526-1.072-1.488-2.034-.418-.418-.627-.627-.746-.888a1.628 1.628 0 0 1-.046-.11c-.1-.27-.1-.565-.1-1.156v-.11c0-1.507 0-2.261-.468-2.73-.469-.468-1.223-.468-2.73-.468h-.11Z" stroke="currentColor" stroke-width="1.5"/><path d="M8.5 16.5a4.039 4.039 0 0 1 3.5-2.02c1.496 0 2.801.812 3.5 2.02M14 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                <span><?php esc_html_e( 'Create New Account', 'wp-thrivedesk' ); ?></span>
            </a>
            <a href="<?php echo THRIVEDESK_APP_URL . '/auth/authorize?auth_return_url=' . get_bloginfo('url') . '/wp-admin/admin.php?page=thrivedesk&auth_platform=WordPress' ?>" class="btn btn-secondary">
                <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" color="#000" fill="none"><path d="M4.513 19.487c2.512 2.392 5.503 1.435 6.7.466.618-.501.897-.825 1.136-1.065.837-.777.784-1.555.24-2.177-.219-.249-1.616-1.591-2.956-2.967-.694-.694-1.172-1.184-1.582-1.58-.547-.546-1.026-1.172-1.744-1.154-.658 0-1.136.58-1.735 1.179-.688.688-1.196 1.555-1.375 2.333-.539 2.273.299 3.888 1.316 4.965Zm0 0L2 21.999M19.487 4.515c-2.513-2.394-5.494-1.42-6.69-.45-.62.502-.898.826-1.138 1.066-.837.778-.784 1.556-.239 2.178.078.09.31.32.635.644m7.432-3.438c1.017 1.077 1.866 2.71 1.327 4.985-.18.778-.688 1.645-1.376 2.334-.598.598-1.077 1.179-1.735 1.179-.718.018-1.09-.502-1.639-1.048m3.423-7.45L22 2m-5.936 9.964c-.41-.395-.994-.993-1.688-1.687-.858-.882-1.74-1.75-2.321-2.325m4.009 4.012-1.562 1.524m-3.99-3.983 1.543-1.553" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                <span><?php esc_html_e( 'Connect Existing Account', 'wp-thrivedesk' ); ?></span>
            </a>
        </div>
    </div>
  </div>
</div>
