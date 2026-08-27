<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * The body of the Workspace card.
 *
 * Only ever reached from the connected branch of partials/overview - a card
 * whose whole content is "this will say something once you connect" is a card's
 * worth of screen saying later, beside one saying now.
 */

$td_summary    = \ThriveDesk\Services\WorkspaceService::summary();
$td_workspace  = $td_summary['workspace'];
$td_plan       = $td_summary['plan'];
$td_capability = [
	'account'       => __( 'Account', 'thrivedesk' ),
	'billing'       => __( 'Billing', 'thrivedesk' ),
	'assistants'    => __( 'Assistants', 'thrivedesk' ),
	'inboxes'       => __( 'Inboxes', 'thrivedesk' ),
	'knowledgebase' => __( 'Knowledge base', 'thrivedesk' ),
];

// Uppercasing is a CSS class, never baked into the string: plenty of languages
// have no case at all, and the ones that do do not all uppercase the way
// English does.
$td_label = 'text-[11px] font-semibold uppercase text-slate-400 whitespace-nowrap';
?>
<div class="text-base font-bold mb-3"><?php esc_html_e( 'Workspace', 'thrivedesk' ); ?></div>


<?php
/*
 * A two-column grid rather than a fixed label width. `auto` sizes the first
 * column to the widest label there actually is, so every value lines up
 * without a magic number that a longer translation would overflow. The
 * spans are direct children on purpose - wrapping a row in a div would take
 * it out of the grid.
 */
?>
<div class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-2 items-baseline">
	<span class="<?php echo esc_attr( $td_label ); ?>" data-td-reveal><?php esc_html_e( 'Workspace:', 'thrivedesk' ); ?></span>
	<span class="text-sm text-slate-800" data-td-reveal>
		<?php echo esc_html( $td_workspace['name'] ? $td_workspace['name'] : __( 'Not connected', 'thrivedesk' ) ); ?>
	</span>

	<?php if ( $td_plan ) : ?>
		<span class="<?php echo esc_attr( $td_label ); ?>" data-td-reveal><?php esc_html_e( 'Plan:', 'thrivedesk' ); ?></span>
		<span class="flex items-center flex-wrap gap-2 text-sm text-slate-800" data-td-reveal>
			<?php echo esc_html( $td_plan['label'] ); ?>
			<?php if ( $td_plan['billing_type'] ) : ?>
				<span class="py-0.5 px-2 bg-slate-100 text-slate-600 text-[11px] rounded-full whitespace-nowrap">
					<?php echo esc_html( $td_plan['billing_type'] ); ?>
				</span>
			<?php endif; ?>
		</span>

		<?php // Answers up front what the Portal tab would otherwise only reveal by being empty. ?>
		<span class="<?php echo esc_attr( $td_label ); ?>" data-td-reveal><?php esc_html_e( 'Portal:', 'thrivedesk' ); ?></span>
		<span class="text-sm <?php echo $td_plan['portal'] ? 'text-green-600' : 'text-gray-500'; ?>" data-td-reveal>
			<?php echo $td_plan['portal']
				? esc_html__( 'Included', 'thrivedesk' )
				: esc_html__( 'Not included', 'thrivedesk' ); ?>
		</span>

		<?php if ( $td_plan['expired'] ) : ?>
			<span class="col-span-2 text-[12px] text-rose-600" data-td-reveal><?php esc_html_e( 'Subscription expired', 'thrivedesk' ); ?></span>
		<?php endif; ?>
	<?php endif; ?>
</div>

<?php if ( $td_summary['api'] ) : ?>
	<div class="mt-4 pt-3 border-t border-slate-200">
		<div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400" data-td-reveal><?php esc_html_e( 'API access', 'thrivedesk' ); ?></div>
		<ul class="mt-2! mb-0! p-0! list-none grid grid-cols-2 gap-x-4 gap-y-1">
			<?php foreach ( $td_capability as $td_key => $td_name ) : ?>
				<?php
				if ( ! isset( $td_summary['api'][ $td_key ] ) ) {
					continue;
				}

				$td_state = $td_summary['api'][ $td_key ];
				?>
				<li class="flex items-center gap-2 text-[13px]" data-td-reveal>
					<span class="<?php echo $td_state['ok'] ? 'text-green-600' : 'text-rose-500'; ?>" aria-hidden="true">
						<?php echo $td_state['ok'] ? '&#10003;' : '&#10005;'; ?>
					</span>
					<span class="text-slate-700"><?php echo esc_html( $td_name ); ?></span>
					<?php if ( ! $td_state['ok'] ) : ?>
						<span class="ml-auto text-[11px] text-gray-400">
							<?php
							// A status beats a word here: 403 is a permission the key lacks,
							// 0 is never having reached ThriveDesk at all.
							echo esc_html( $td_state['status'] ? (string) $td_state['status'] : __( 'unreachable', 'thrivedesk' ) );
							?>
						</span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

