<!-- Main modal -->
<div class="td-modal-container hidden fixed top-0 left-0 z-100 max-w-full flex flex-col h-screen w-screen p-6 md:p-[10vh] 2xl:p-[12vh] bg-slate-900 bg-opacity-50 backdrop-blur-sm" aria-modal="true" role="dialog">
	<div class="td-modal w-full mx-auto max-w-3xl min-h-0 flex flex-col rounded-lg drop-shadow-lg bg-white">
		<!-- Modal header  -->
		<div class="td-modal-header px-4 flex flex-none items-center border-b border-slate-100">
			<form class="flex flex-1 items-center">
				<label for="td-search-input" id="tdSearch-label"><?php thrivedesk_view('/icons/search'); ?></label>
				<input id="td-search-input" class="bg-white border-0 h-14 flex-auto outline-none"  spellcheck="false" placeholder="<?php _e('Search documentation')?>" maxlength="64" type="search" value="" />
			</form>
			<button id="close-modal" class="px-1.5 py-1 uppercase font-semibold border border-slate-100 rounded flex items-center text-xs hover:bg-slate-100 text-black" data-modal-toggle="tdConversationModal">
				<span><?php _e('Esc', 'thrivedesk'); ?></span>
			</button>
		</div> <!-- /Modal header  -->
		
		<!-- Modal body  -->
		<div class="td-modal-body flex-auto overflow-auto">
			<div class="py-6">
				<div>
					<ul id="td-search-results" class="space-y-2">
						<li class="h-36 flex items-center justify-center text-slate-500">
							<span> <?php _e('Search before creating a new ticket', 'thrivedesk'); ?></span>
						</li>
					</ul>
				</div>
			</div>
		</div> <!-- /Modal body  -->
		
		<!-- Modal footer  -->
		<div class="td-modal-footer p-6 border-t text-center">
			<a href="<?php echo get_page_link( get_post(get_td_helpdesk_options('td_helpdesk_settings')['td_form_page_id']))?>" id="td-new-ticket-url" target="_blank" class="td-btn-primary no-underline">
				<?php _e('Create a new ticket', 'thrivedesk'); ?>
			</a>
		</div>
	</div>
</div>
