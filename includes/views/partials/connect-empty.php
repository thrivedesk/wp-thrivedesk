<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * What a tab shows before this site is connected.
 *
 * Every control on the Live Chat and Portal tabs is populated from ThriveDesk -
 * the assistants, the inboxes, the knowledge base. Rendered without a key they
 * are a screen of empty selects that reads like the account is empty rather
 * than absent, so the tab says what is actually missing and offers the same two
 * ways out as the setup screen.
 *
 * @var string $td_empty_title What the tab would do once connected.
 * @var string $td_empty_text  One sentence on why it is empty.
 */

$td_empty_title = isset( $td_empty_title ) ? $td_empty_title : __( 'Connect ThriveDesk first', 'thrivedesk' );
$td_empty_text  = isset( $td_empty_text ) ? $td_empty_text : '';
?>
<div class="td-card td-empty">

	<span class="td-empty__icon" aria-hidden="true">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" fill="none">
			<path d="M4.513 19.487c2.512 2.392 5.503 1.435 6.7.466.618-.501.897-.825 1.136-1.065.837-.777.784-1.555.24-2.177-.219-.249-1.616-1.591-2.956-2.967-.694-.694-1.172-1.184-1.582-1.58-.547-.546-1.026-1.172-1.744-1.154-.658 0-1.136.58-1.735 1.179-.688.688-1.196 1.555-1.375 2.333-.539 2.273.299 3.888 1.316 4.965Zm0 0L2 21.999M19.487 4.515c-2.513-2.394-5.494-1.42-6.69-.45-.62.502-.898.826-1.138 1.066-.837.778-.784 1.556-.239 2.178.078.09.31.32.635.644m7.432-3.438c1.017 1.077 1.866 2.71 1.327 4.985-.18.778-.688 1.645-1.376 2.334-.598.598-1.077 1.179-1.735 1.179-.718.018-1.09-.502-1.639-1.048m3.423-7.45L22 2m-5.936 9.964c-.41-.395-.994-.993-1.688-1.687-.858-.882-1.74-1.75-2.321-2.325m4.009 4.012-1.562 1.524m-3.99-3.983 1.543-1.553" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
		</svg>
	</span>

	<h3 class="text-lg font-semibold m-0!"><?php echo esc_html( $td_empty_title ); ?></h3>

	<?php if ( $td_empty_text ) : ?>
		<p class="mt-2! mb-0! text-gray-500 max-w-md mx-auto"><?php echo esc_html( $td_empty_text ); ?></p>
	<?php endif; ?>

	<div class="td-empty__actions">
		<?php thrivedesk_view( 'partials/connect-accounts' ); ?>

		<?php
		/*
		 * Sends the reader to the card that actually takes the key rather than
		 * repeating the field here - two API key inputs on one page is two ids,
		 * and the handler that verifies it is bound to one of them.
		 */
		?>
		<button type="button" class="td-empty__link" data-td-goto-tab="overview">
			<?php esc_html_e( 'Already have a key? Add it on the Overview tab', 'thrivedesk' ); ?>
		</button>
	</div>
</div>
