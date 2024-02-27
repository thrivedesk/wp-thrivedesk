<div class="flex items-center justify-center flex-col relative p-10">

  <div class="flex items-center w-full">
    <a href="https://www.thrivedesk.com/" target="_blank">
      <img src="<?php echo THRIVEDESK_PLUGIN_ASSETS . '/images/thrivedesk.png' ?>" alt="ThriveDesk logo" class="w-32">
    </a>
    <a href="#" class="ml-auto text-brand-light inline-block mr-5">Need help?</a>
  </div>

  
  <div class="m-10 p-10 w-6/12 bg-white shadow hover:shadow-md transition-shadow rounded ">
    <div class="text-xl font-bold"><?php esc_html_e( "Welcome to ThriveDesk, You'r a few clicks away from setup!", 'wp-thrivedesk' ); ?></div>
    <p class="mt-1 text-sm muted">
        <?php esc_html_e( 'We are excited to have you on board. ThriveDesk is a powerful customer support platform that helps you manage your customer support requests and provide a better customer experience.', 'wp-thrivedesk' ); ?>
    </p>
    <div class="grid grid-flow-col justify-stretch space-x-2">
        <a href="<?php echo THRIVEDESK_APP_URL . '/register?store=' . get_bloginfo('url') . '/wp-admin/admin.php?page=thrivedesk'?>" target="_blank" class="btn-primary mt-5"><?php esc_html_e( 'Create New Account', 'wp-thrivedesk' ); ?></a>
        <a href="<?php echo THRIVEDESK_APP_URL . '/handshake?store=' . get_bloginfo('url') . '/wp-admin/admin.php?page=thrivedesk' ?>" class="btn-primary mt-5"><?php esc_html_e( 'Connect Existing Account', 'wp-thrivedesk' ); ?></a>
    </div>
    
    <p class="muted inline-block mt-3">By continuing, you agree to the Terms of Service and Privacy Policy.</p>
  </div>

</div>
