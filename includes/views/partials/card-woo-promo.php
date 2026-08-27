<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * What connecting WooCommerce buys you.
 *
 * Hidden once it is connected: nobody needs selling something they already
 * have, and a promo that never goes away stops being read at all. Shown
 * whatever else is true - a store that has not installed WooCommerce yet is
 * still the audience for this.
 */

$td_woo_promo = \ThriveDesk\Plugins\WooCommerce::instance();

if ( $td_woo_promo && $td_woo_promo->get_plugin_data( 'connected' ) ) {
	return;
}

$td_woo_features = [
	__( 'A chatbot that answers from your own catalogue', 'thrivedesk' ),
	__( 'Refunds, order edits and shipping without leaving the chat', 'thrivedesk' ),
	__( 'Agentic AI with real control over the store, not just replies', 'thrivedesk' ),
	__( 'Subscriptions and order history beside every conversation', 'thrivedesk' ),
];
?>
<aside class="td-promo td-promo--woo">

	<?php
	/*
	 * The wordmark from WooCommerce's own brand guidelines, in their purple.
	 *
	 * Decoration, so it is aria-hidden and carries no alt text - the heading
	 * below already says which product this is about. It bleeds off the right
	 * edge on purpose; the container clips it.
	 */
	?>
	<img class="td-promo__art" src="<?php echo esc_url( THRIVEDESK_PLUGIN_ASSETS . '/images/woo-logo.svg' ); ?>" alt="" aria-hidden="true" width="475" height="130">

	<div class="td-promo__body">
		<span class="td-promo__eyebrow"><?php esc_html_e( 'WooCommerce integration', 'thrivedesk' ); ?></span>

		<h3 class="td-promo__title"><?php esc_html_e( 'Run your store from the conversation', 'thrivedesk' ); ?></h3>

		<p class="td-promo__lede">
			<?php esc_html_e( 'Connect your store and every conversation arrives with the customer already known - what they bought, what they are paying for, and what is on its way.', 'thrivedesk' ); ?>
		</p>

		<ul class="td-promo__features">
			<?php foreach ( $td_woo_features as $td_woo_feature ) : ?>
				<li>
					<span class="td-promo__tick" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="13" height="13" fill="none"><path d="m5 12.5 4.5 4.5L19 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</span>
					<?php echo esc_html( $td_woo_feature ); ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php // The tab that can actually connect it - see the [data-td-goto-tab] handler in admin.js. ?>
		<button type="button" class="btn-solid td-promo__cta" data-td-goto-tab="integrations">
			<?php esc_html_e( 'Connect WooCommerce', 'thrivedesk' ); ?>
		</button>
	</div>
</aside>
