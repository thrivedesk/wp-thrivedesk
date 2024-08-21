<!-- Main modal -->
<div class="td-modal-container max-w-full h-screen w-screen" aria-modal="true" role="dialog">
	<div class="td-modal">
		<!-- Modal header  -->
		<div class="td-modal-header">
			<form class="">
				<label for="td-search-input" id="tdSearch-label"><?php thrivedesk_view('/icons/search'); ?></label>
				<input id="td-search-input" class=""  spellcheck="false" placeholder="<?php _e('Search documentation')?>" maxlength="64" type="search" value="" />
			</form>
			<button id="close-modal" class="" data-modal-toggle="tdConversationModal">
				<span><?php _e('Esc', 'thrivedesk'); ?></span>
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
							<span> <?php _e('Please search before creating a new ticket', 'thrivedesk'); ?></span>
						</li>
					</ul>
				</div>
			</div>
		</div> <!-- /Modal body  -->
		
		<!-- Modal footer  -->
		<div class="td-modal-footer">
			<a href="<?php echo get_page_link( get_post(get_td_helpdesk_options('td_helpdesk_settings')['td_helpdesk_page_id']))?>" id="td-new-ticket-url" target="_blank" class="td-btn-primary">
				<?php _e('Create a new ticket', 'thrivedesk'); ?>
			</a>
		</div>
	</div>
</div>
