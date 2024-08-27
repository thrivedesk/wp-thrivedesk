<?php

use ThriveDesk\Assistants\Assistant;

$assistant_settings = Assistant::get_assistant_settings();

?>

<div class="thrivedesk">
    <canvas id="confetti-canvas" style="position: absolute; z-index: 999; display: none" class="w-full "></canvas>
    <!-- header  -->
    <div class="flex items-center py-5 px-9">
        <img class="w-32" src="<?php echo THRIVEDESK_PLUGIN_ASSETS . "/images/thrivedesk.png"; ?>"
                alt="ThriveDesk Logo">
        <div class="flex items-center space-x-4 ml-2">
            <span class="py-0.5 px-2 bg-slate-200 text-slate-700 text-[12px] rounded-full">
                <?php _e( 'Version', 'thrivedesk' ) ?> <?php echo THRIVEDESK_VERSION;?>
            </span>
        </div>
        <div class="ml-auto flex items-center space-x-2 text-sm top-nav">
            <a class="rounded flex items-center space-x-1 px-3 py-1.5 border border-gray-300" href="https://www.thrivedesk.com/wordpress/" target="_blank">
                <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" color="#666" fill="none"><path d="M11.099 3c-3.65.007-5.56.096-6.781 1.318C3 5.636 3 7.757 3 12c0 4.242 0 6.364 1.318 7.682C5.636 21 7.757 21 11.998 21c4.243 0 6.364 0 7.682-1.318 1.22-1.221 1.31-3.133 1.317-6.782M20.556 3.496 11.05 13.06m9.507-9.563c-.494-.494-3.822-.448-4.525-.438m4.525.438c.494.495.448 3.827.438 4.531" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <span><?php _e( 'Visit ThriveDesk', 'thrivedesk' ) ?></span>
            </a>
            <a href="https://help.thrivedesk.com/en" target="_blank">
                <?php _e( 'Help Center', 'thrivedesk' ) ?>
            </a>
            <a href="https://status.thrivedesk.com/" target="_blank">
                <?php _e( 'System Status', 'thrivedesk' ) ?>
            </a>
            <a href="#" onclick="Assistant('contact', {
                subject: 'Issue/Feedback from WP Plugin',
                body: 'Write your issue/feedback details here...',
            })"><?php _e( 'Support', 'thrivedesk' ) ?></a>
        </div>
    </div>

    <!-- body  -->
    <div class="p-10 grid grid-cols-1 md:grid-cols-4 gap-12">        
        <div class="col-span-3 space-y-6">
            <?php thrivedesk_view( 'partials/integrations' ); ?>

            <!-- include the settings page -->
            <?php thrivedesk_view( 'partials/settings' ); ?>
        </div>
        <div class="col-span-1">
            <!-- include the sidebar -->
            <?php thrivedesk_view( 'partials/sidebar' ); ?>
        </div>
    </div>
</div>
