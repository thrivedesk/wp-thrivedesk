<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<div class="thrivedesk">
  <?php // One centred stack - the logo sits directly over the card, so the card
        // stays the only thing on the screen to look at. ?>
  <div class="flex flex-col td-fullscreen items-center justify-center relative p-10">

    <a href="https://www.thrivedesk.com/" target="_blank" class="mb-10">
      <img src="<?php echo esc_url(THRIVEDESK_PLUGIN_ASSETS . '/images/thrivedesk.png'); ?>" alt="ThriveDesk logo" class="w-48">
    </a>

    <?php thrivedesk_view( 'partials/connect-card' ); ?>
  </div>
</div>
