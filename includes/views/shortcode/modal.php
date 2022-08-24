<?php
$conversation = ThriveDesk\Conversations\Conversation::instance();
?>

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
		<div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
			<!-- Modal header -->
			<div class="flex justify-between items-start p-4 rounded-t border-b dark:border-gray-600">
				<h3 class="text-xl font-semibold text-gray-900 dark:text-white">
					Create new ticket
				</h3>
				<button type="button" id="close_modal" class="text-gray-400 bg-transparent hover:bg-gray-200
                hover:text-gray-900
                rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-gray-600 dark:hover:text-white" data-modal-toggle="tdConversationModal">
					<svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
					<span class="sr-only">Close modal</span>
				</button>
			</div>
			<!-- Modal body -->
			<div class="tdSearch-Modal" style="--tdSearch-vh:5.8px;">
				<header class="tdSearch-SearchBar">
					<form class="tdSearch-Form">
						<label class="tdSearch-MagnifierLabel" for="tdSearch-input" id="tdSearch-label"
						><svg width="20" height="20" class="tdSearch-Search-Icon" viewBox="0 0 20 20"><path d="M14.386 14.386l4.0877 4.0877-4.0877-4.0877c-2.9418 2.9419-7.7115 2.9419-10.6533 0-2.9419-2.9418-2.9419-7.7115 0-10.6533 2.9418-2.9419 7.7115-2.9419 10.6533 0 2.9419 2.9418 2.9419 7.7115 0 10.6533z" stroke="currentColor" fill="none" fill-rule="evenodd" stroke-linecap="round" stroke-linejoin="round"></path></svg
							></label>
						<div class="tdSearch-LoadingIndicator">
							<svg viewBox="0 0 38 38" stroke="currentColor" stroke-opacity=".5">
								<g fill="none" fill-rule="evenodd">
									<g transform="translate(1 1)" stroke-width="2">
										<circle stroke-opacity=".3" cx="18" cy="18" r="18"></circle>
										<path d="M36 18c0-9.94-8.06-18-18-18"><animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="1s" repeatCount="indefinite"></animateTransform></path>
									</g>
								</g>
							</svg>
						</div>
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
						<a href="<?php echo get_page_link( get_post($conversation->getSelectedHelpdeskOptions('td_helpdesk_settings')['td_form_page_id']))?>" id="td-new-ticket-url" target="_blank" class="text-blue-600 text-sm">
							Create new
						</a>
					</div>
				</footer>
			</div>
		</div>
	</div>
</div>
