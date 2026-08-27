<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * The wrapper, separate from the body it holds.
 *
 * The id is on the wrapper because that is what glows on the first load after
 * connecting; the body is what the rows reveal inside. See the
 * TD_CONNECTED_FLAG block in admin.js.
 */
?>
<div class="td-card h-full" id="td-workspace-card">
	<div id="td-workspace-card-body">
		<?php thrivedesk_view( 'partials/workspace-card' ); ?>
	</div>
</div>
