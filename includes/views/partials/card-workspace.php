<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * The wrapper, separate from the body it holds.
 *
 * The id is on the wrapper because that is what the celebration glows, and the
 * body is what gets replaced when a key is accepted - see tdCelebrateConnection
 * in admin.js and Admin::ajax_workspace_card().
 */
?>
<div class="td-card" id="td-workspace-card">
	<div id="td-workspace-card-body">
		<?php thrivedesk_view( 'partials/workspace-card' ); ?>
	</div>
</div>
