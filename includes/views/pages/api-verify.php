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
        <input type="password" id="td_helpdesk_api_key" class="w-full p-2 border border-gray-300 shadow-sm rounded" placeholder="Enter your API Key" value="<?php echo isset($_GET['token']) ? $_GET['token'] : ''; ?>" />
        <button id="submit-btn" class="btn btn-primary w-full justify-center">
          <span><?php esc_html_e( 'Complete Setup', 'wp-thrivedesk' ); ?></span>
          <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" color="#fff" fill="none"><path d="m14.527 18-1.408-1.414L16.689 13H3.5v-2h13.189l-3.57-3.587L14.527 6l5.973 6-5.973 6Z" fill="currentColor"/></svg></span>
        </button>
        
        <div class="text-gray-400 text-[12px]">By continuing, you agree to the <a href="https://www.thrivedesk.com/our/terms/" target="_blank" class="underline">Terms of Service</a> and <a href="https://www.thrivedesk.com/our/privacy/" target="_blank" class="underline">Privacy Policy</a>.</div>
      </div>
    </div>
  </div>
</div>
