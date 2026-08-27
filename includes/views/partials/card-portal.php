<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * What Portal is, and the tour.
 *
 * A partial because it sits in two shapes. Two thirds of a row once this site
 * is connected, text beside the video; and stacked in the narrow right column
 * before then, where splitting 460px between a paragraph and a thumbnail would
 * leave neither readable.
 *
 * @var bool $td_portal_narrow Whether it is in the stacked column.
 */

$td_portal_narrow = isset( $td_portal_narrow ) ? $td_portal_narrow : false;
?>
<div class="td-card">
        <div class="grid grid-cols-1 <?php echo $td_portal_narrow ? '' : 'md:grid-cols-2'; ?> gap-6 items-center">
            <div>
                <h3 class="text-lg font-semibold m-0!"><?php esc_html_e( 'Overview of WPPortal', 'thrivedesk' ); ?></h3>
                <p class="mt-2! mb-0! text-gray-500">
                    <?php esc_html_e( 'Portal puts a help centre on your own site: customers sign in with the account they already have, read your knowledge base, open a ticket and follow it to a reply - without leaving your domain.', 'thrivedesk' ); ?>
                </p>
                <a class="btn-ghost mt-4" href="https://www.thrivedesk.com/wordpress/" target="_blank">
                    <span><?php esc_html_e( 'Learn more', 'thrivedesk' ); ?></span>
                    <?php thrivedesk_view( 'icons/external' ); ?>
                </a>
            </div>

            <?php
            /*
             * A button, not a thumbnail with a click handler: this opens
             * something, so it should be reachable by keyboard and announce
             * itself. The embed URL rides on a data attribute and is only
             * given to an iframe when the dialog opens, so the page does not
             * pull a video player it may never need.
             */
            ?>
            <button
                type="button"
                class="td-video"
                data-td-video="<?php echo esc_url( 'https://iframe.mediadelivery.net/embed/10114/9f38fded-ddd9-44ba-bdfe-7d362235d40c?autoplay=true&responsive=true' ); ?>"
                data-td-video-title="<?php esc_attr_e( 'Overview of WPPortal', 'thrivedesk' ); ?>"
            >
                <?php
                /*
                 * The mockup is drawn, not photographed. There is no picture
                 * of the portal in the bundled assets and the embed host's
                 * thumbnail URL is not something to guess at, so this is a
                 * suggestion of the product rather than a claim to be a
                 * screenshot of it.
                 */
                ?>
                <span class="td-video__mockup" aria-hidden="true">
                    <svg viewBox="0 0 320 200" width="100%" height="100%" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="8" y="8" width="304" height="184" rx="8" fill="#fff" fill-opacity=".97"/>
                        <rect x="8" y="8" width="304" height="22" rx="8" fill="#eef2ff"/>
                        <rect x="8" y="24" width="304" height="6" fill="#eef2ff"/>
                        <circle cx="20" cy="19" r="3" fill="#cbd5e1"/><circle cx="30" cy="19" r="3" fill="#cbd5e1"/><circle cx="40" cy="19" r="3" fill="#cbd5e1"/>
                        <rect x="92" y="46" width="136" height="14" rx="7" fill="#1e293b" fill-opacity=".12"/>
                        <rect x="60" y="72" width="200" height="20" rx="10" fill="#3858e9" fill-opacity=".10" stroke="#3858e9" stroke-opacity=".25"/>
                        <circle cx="74" cy="82" r="4" stroke="#3858e9" stroke-opacity=".55" stroke-width="1.5"/>
                        <path d="m77 85 3 3" stroke="#3858e9" stroke-opacity=".55" stroke-width="1.5" stroke-linecap="round"/>
                        <rect x="60" y="106" width="92" height="34" rx="5" fill="#f1f5f9"/>
                        <rect x="168" y="106" width="92" height="34" rx="5" fill="#f1f5f9"/>
                        <rect x="68" y="114" width="52" height="5" rx="2.5" fill="#94a3b8"/>
                        <rect x="68" y="125" width="72" height="4" rx="2" fill="#cbd5e1"/>
                        <rect x="176" y="114" width="52" height="5" rx="2.5" fill="#94a3b8"/>
                        <rect x="176" y="125" width="72" height="4" rx="2" fill="#cbd5e1"/>
                        <rect x="60" y="152" width="200" height="28" rx="5" fill="#f8fafc"/>
                        <rect x="68" y="162" width="110" height="5" rx="2.5" fill="#cbd5e1"/>
                        <rect x="222" y="159" width="30" height="12" rx="6" fill="#3858e9" fill-opacity=".2"/>
                    </svg>
                </span>
                <span class="td-video__play" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22"><path d="M8 5.5v13l11-6.5-11-6.5Z" fill="currentColor"/></svg>
                </span>
                <span class="td-video__label"><?php esc_html_e( 'Watch the 40 second tour', 'thrivedesk' ); ?></span>
            </button>
        </div>
</div>
