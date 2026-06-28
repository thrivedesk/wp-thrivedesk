<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<!-- Backdrop + dialog. Starts hidden via CSS, NOT the HTML hidden
     attribute, tailwind's preflight sets [hidden]{display:none!important}
     which would beat the .is-open{display:flex} rule and keep JS from
     ever showing it. -->
<div class="td-modal-container" id="tdConversationModal" aria-modal="true" role="dialog" aria-labelledby="td-modal-title">
	<div class="td-modal" role="document">

		<!-- Header -->
		<header class="td-modal-header">
			<div class="td-modal-header-text">
				<h2 class="td-modal-title" id="td-modal-title"><?php esc_html_e('Search documentation', 'thrivedesk'); ?></h2>
				<p class="td-modal-subtitle"><?php esc_html_e('Find answers in our knowledge base or open a ticket.', 'thrivedesk'); ?></p>
			</div>
			<button type="button" id="close-modal" class="td-btn td-btn-outline td-btn-sm" aria-label="<?php esc_attr_e('Close (Esc)', 'thrivedesk'); ?>" data-modal-toggle="tdConversationModal">
				<span><?php esc_html_e('Esc', 'thrivedesk'); ?></span>
			</button>
		</header>

		<!-- Search row. Same shape as the portal header's search so it
		     feels like a focused view of the same input. -->
		<div class="td-modal-search">
			<div class="td-search-wrap">
				<svg class="td-search-icon" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="11" cy="11" r="8"/>
					<path d="m21 21-4.3-4.3"/>
				</svg>
				<input id="td-search-input" class="td-input" type="search" spellcheck="false"
				       placeholder="<?php esc_attr_e('Search articles, guides, and docs…', 'thrivedesk'); ?>"
				       maxlength="64" autocomplete="off">
				<button type="button" class="td-search-clear" id="td-modal-search-clear" aria-label="<?php esc_attr_e('Clear search', 'thrivedesk'); ?>" hidden>
					<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M18 6 6 18"/><path d="m6 6 12 12"/>
					</svg>
				</button>
			</div>
		</div>

		<!-- Body. Fixed min-height so the dialog doesn't resize as
		     results come and go. -->
		<div class="td-modal-body">

			<!-- Centered loading state. Replaces the body contents
			     while a request is in flight, hidden when results,
			     empty, or initial state is shown. -->
			<div class="td-modal-loading" id="td-search-spinner" hidden aria-hidden="true">
				<div class="td-modal-loading-spinner" aria-hidden="true">
					<?php thrivedesk_view('/icons/spinner'); ?>
				</div>
				<div class="td-modal-loading-label"><?php esc_html_e('Searching…', 'thrivedesk'); ?></div>
			</div>

			<!-- One container that holds the hint, the empty state,
			     and any JS-injected sections. The container itself
			     never changes height, JS only toggles which child
			     is visible. -->
			<div class="td-search-items" id="td-search-results">

				<!-- Initial hint, shown when there's no query -->
				<div class="td-search-initial" id="td-search-initial">
					<div class="td-empty-state">
						<div class="td-empty-state-icon" aria-hidden="true">
							<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<circle cx="11" cy="11" r="8"/>
								<path d="m21 21-4.3-4.3"/>
							</svg>
						</div>
						<div class="td-empty-state-title"><?php esc_html_e('Search before opening a ticket', 'thrivedesk'); ?></div>
						<div class="td-empty-state-text"><?php esc_html_e('Type at least one character to search our knowledge base. Most questions are already answered there.', 'thrivedesk'); ?></div>
					</div>
				</div>

				<!-- Empty state, shown when a query returns nothing -->
				<div class="td-search-empty" id="td-search-empty" hidden>
					<div class="td-empty-state">
						<div class="td-empty-state-icon" aria-hidden="true">
							<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
							</svg>
						</div>
						<div class="td-empty-state-title"><?php esc_html_e('No matches', 'thrivedesk'); ?></div>
						<div class="td-empty-state-text"><?php esc_html_e("We couldn't find anything for that query. Try a different term or open a new ticket.", 'thrivedesk'); ?></div>
					</div>
				</div>

				<!-- Result sections get appended here -->
			</div>
		</div>

		<!-- Footer -->
		<footer class="td-modal-footer">
			<?php
			$settings          = get_td_helpdesk_settings();
			$page_id           = absint($settings['td_helpdesk_page_id'] ?? 0);
			// empty string when no ticket page is set so empty()
			// detects the disabled state. '#' would always be truthy.
			$new_ticket_url      = $page_id ? get_page_link($page_id) : '';
			$new_ticket_disabled = empty($new_ticket_url);
			$new_ticket_label    = __('Create a new ticket', 'thrivedesk');
			$new_ticket_title  = $new_ticket_disabled
				? __('No ticket page is selected. Set one in ThriveDesk settings to enable creating new tickets.', 'thrivedesk')
				: __('Open the new ticket form', 'thrivedesk');
			?>
			<a href="<?php echo $new_ticket_disabled ? '#' : esc_url($new_ticket_url); ?>"
			   id="td-new-ticket-url"
			   class="td-btn td-btn-primary td-btn-sm<?php echo $new_ticket_disabled ? ' td-btn-locked' : ''; ?>"
			   <?php echo $new_ticket_disabled ? 'aria-disabled="true" data-locked="true"' : ''; ?>
			   title="<?php echo esc_attr($new_ticket_title); ?>">
				<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<?php if ($new_ticket_disabled): ?>
						<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
						<path d="M7 11V7a5 5 0 0 1 10 0v4"/>
					<?php else: ?>
						<path d="M5 12h14"/><path d="M12 5v14"/>
					<?php endif; ?>
				</svg>
				<span><?php echo esc_html($new_ticket_label); ?></span>
			</a>
		</footer>

	</div>
</div>