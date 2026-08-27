<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * What the live chat widget is, for someone who has not seen one.
 *
 * A partial because it has two homes: beside the connect card while this site
 * has no account - where "what am I signing up for" is the question actually
 * being asked - and in the bottom row of tips once it is connected.
 */
?>
<div class="td-card">
	<div class="flex items-start gap-4">
		<img class="w-24 h-20 shrink-0 rounded object-cover mt-1" src="<?php echo esc_url( THRIVEDESK_PLUGIN_ASSETS . '/images/livechat-hero.jpg' ); ?>" alt="">
		<div>
			<h3 class="text-base font-semibold m-0!"><?php esc_html_e( 'What is Assistant?', 'thrivedesk' ); ?></h3>
			<p class="mt-2! mb-0! text-gray-500"><?php esc_html_e( 'Live chat, knowledge base and a contact form in one widget, shown on the pages you choose.', 'thrivedesk' ); ?></p>
			<a class="btn-ghost mt-4" href="https://www.thrivedesk.com/live-chat/" target="_blank">
				<span><?php esc_html_e( 'Learn more', 'thrivedesk' ); ?></span>
				<?php thrivedesk_view( 'icons/external' ); ?>
			</a>
		</div>
	</div>
</div>
