<div class="flex items-center justify-center flex-col relative p-10">

  <div class="flex items-center w-full">
    <a href="https://www.thrivedesk.com/" target="_blank">
      <img src="<?php echo THRIVEDESK_PLUGIN_ASSETS . '/images/thrivedesk.png' ?>" alt="ThriveDesk logo" class="w-32">
    </a>
    <a href="#" class="ml-auto text-brand-light inline-block mr-5">Need help?</a>
  </div>

  
  <div class="m-10 p-10 w-6/12 bg-white shadow hover:shadow-md transition-shadow rounded ">
    <div class="text-xl font-bold"><?php esc_html_e( "Just one last step!", 'wp-thrivedesk' ); ?></div>
    <p class="mt-1 text-sm muted">
        <?php esc_html_e( 'We are excited to have you on board.', 'wp-thrivedesk' ); ?>
    </p>

    <div class="flex flex-col space-y-2 my-4">
    <textarea id="td_helpdesk_api_key" class="w-full p-2 border border-gray-300 rounded" rows="5" placeholder="Enter your API Key"><?php echo isset($_GET['token']) ? $_GET['token'] : ''; ?></textarea>
        <button id="submit-btn" class="btn-primary w-full"><?php esc_html_e( 'Complete Setup', 'wp-thrivedesk' ); ?></button>
    </div>
    
    <p class="muted inline-block mt-3">By continuing, you agree to the Terms of Service and Privacy Policy.</p>
  </div>
</div>
