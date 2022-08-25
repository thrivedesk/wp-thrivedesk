<div class="bg-gray-900 bg-opacity-50 fixed inset-0 z-40 hidden"></div>
<!-- Main modal -->
<div id="tdConversationModal" style="display: none;" tabindex="-1"
     class="td-modal overflow-y-auto overflow-x-hidden hidden
fixed top-0
right-0
left-0 z-50
w-full md:inset-0 h-modal md:h-full justify-center items-center flex y-4" aria-modal="true" role="dialog">
	<div class="relative p-4 w-full max-w-2xl h-full md:h-auto">
		<!-- Modal content -->
		<div class="relative bg-white rounded-lg shadow">
			<!-- Modal header -->
			<div class="flex justify-between items-start p-4 rounded-t border-b">
				<h3 class="text-xl font-semibold text-gray-900">
					Create new ticket
				</h3>
				<button type="button" id="close_modal" class="text-gray-400 bg-transparent hover:bg-gray-200
                hover:text-gray-900
                rounded-lg text-sm p-1.5 ml-auto inline-flex items-center" data-modal-toggle="tdConversationModal">
					<?php thrivedesk_view('icons/close'); ?>
					<span class="sr-only">Close modal</span>
				</button>
			</div>
			<!-- Modal body -->
			<div class="tdSearch-Modal" style="--tdSearch-vh:5.8px;">
				<header class="tdSearch-SearchBar">
					<form class="tdSearch-Form">
						<label class="tdSearch-MagnifierLabel" for="tdSearch-input" id="tdSearch-label"
						><?php thrivedesk_view('/icons/search'); ?></label>

						<input class="tdSearch-Input" aria-autocomplete="both" aria-labelledby="tdSearch-label"
						       id="td_search_input" autocomplete="off" autocorrect="off" autocapitalize="off"
						       enterkeyhint="go" spellcheck="false" placeholder="Search documentation" maxlength="64" type="search" value="" />
					</form>
				</header>
				<div class="tdSearch-Dropdown">
					<div class="tdSearch-Dropdown-Container">
						<section class="tdSearch-Hits">
							<ul role="listbox" aria-labelledby="tdSearch-label" id="td_search_list">
								<li class="text-center pt-3">
									<p class="text-sm">Write to search from documentation</p>
								</li>
							</ul>
						</section>
						<section class="tdSearch-HitsFooter"></section>
					</div>
				</div>
				<footer class="tdSearch-Footer">
					<div class="flex-s p-1 space-x-2">
						<a href="<?php echo get_page_link( get_post(get_td_helpdesk_options('td_helpdesk_settings')['td_form_page_id']))?>" id="td-new-ticket-url" target="_blank" class="text-blue-600 text-sm">
							Create new
						</a>
					</div>
				</footer>
			</div>
		</div>
	</div>
</div>
