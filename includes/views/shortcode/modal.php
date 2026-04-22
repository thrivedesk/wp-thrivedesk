<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<!-- Main modal -->
<div class="td-modal-container max-w-full h-screen w-screen" aria-modal="true" role="dialog">
	<div class="td-modal">
		<!-- Modal header  -->
		<div class="td-modal-header">
			<form class="">
				<label for="td-search-input" id="tdSearch-label"><?php thrivedesk_view('/icons/search'); ?></label>
				        <input id="td-search-input" class=""  spellcheck="false" placeholder="<?php esc_attr_e('Search documentation', 'thrivedesk'); ?>" maxlength="64" type="search" value="" />
			</form>
			<button id="close-modal" class="" data-modal-toggle="tdConversationModal">
				            <span><?php esc_html_e('Esc', 'thrivedesk'); ?></span>
			</button>
		</div> <!-- /Modal header  -->
		
		<!-- Modal body  -->
		<div class="td-modal-body" style="max-height: 40rem">
			<div class="py-6 td-search-items">
                <div class="text-center my-5" style="display: none;" id="td-search-spinner">
                    <div role="status">
                        <?php thrivedesk_view('/icons/spinner'); ?>
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
				<div class="overflow-y-scroll">
					<ul id="td-search-results">
						<li class="flex items-center justify-center text-slate-500">
							        <span> <?php esc_html_e('Please search before creating a new ticket', 'thrivedesk'); ?></span>
						</li>
					</ul>
				</div>
			</div>
		</div> <!-- /Modal body  -->
		
		<!-- Modal footer  -->
		<div class="td-modal-footer">
			<?php 
			$settings = get_td_helpdesk_settings();
			$page_id = absint($settings['td_helpdesk_page_id'] ?? 0);
			$new_ticket_url = $page_id ? get_page_link($page_id) : '#';

			?>
			<a href="<?php echo esc_url($new_ticket_url); ?>" id="td-new-ticket-url" class="td-btn-primary">
			            <?php esc_html_e('Create a new ticket', 'thrivedesk'); ?>
			</a>
		</div>
	</div>
</div>
