<?php

use ThriveDesk\Assistants\Assistant;

$assistant_settings = Assistant::get_assistant_settings();


$nonce = wp_create_nonce( 'thrivedesk-plugin-action' );
?>

<div class="thrivedesk">
    <!-- header  -->
    <div class="flex items-center py-5 px-9">
        <img class="w-32" src="<?php echo THRIVEDESK_PLUGIN_ASSETS . "/images/thrivedesk.png"; ?>"
                alt="ThriveDesk Logo">
        <div class="flex space-x-4 ml-2">
            <span class="py-0.5 px-2 bg-slate-200 text-slate-700 text-[11px] rounded-full">
                <?php _e( 'Version', 'thrivedesk' ) ?> <?php echo THRIVEDESK_VERSION;?>
            </span>
            <button id="thrivedesk_clear_cache_btn" class="hover:text-blue-600">
                <?php _e( 'Clear Cache', 'thrivedesk' ) ?>
            </button>
        </div>
        <div class="ml-auto flex space-x-2 text-sm top-nav">
            <a class="rounded bg-gradient-to-b from-white to-neutral-100 shadow hover:from-blue-500 hover:to-blue-600 hover:text-white"
                href="https://www.thrivedesk.com/" target="_blank">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor"
                        viewBox="0 0 16 16">
                    <path fill-rule="evenodd"
                            d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z"/>
                    <path fill-rule="evenodd"
                            d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z"/>
                </svg>
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
    <div class="p-10 grid grid-cols-4 gap-12">        
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
