<div class="td-modal">
<!-- Modal header  -->
<div class="td-modal-header">
	<form class="">
		<label for="td-search-input" id="tdSearch-label"><?php thrivedesk_view('/icons/search'); ?></label>
		<input id="td-search-input" class=""  spellcheck="false" placeholder="<?php _e('Search')?>" maxlength="64" type="search" value="" />
	</form>
</div> <!-- /Modal header  -->

<!-- Modal body  -->
<div class="td-modal-body">
	<div class="py-6 td-search-items">
		<div class="text-center my-5" style="display: none;" id="td-search-spinner">
			<div role="status">
				<?php thrivedesk_view('/icons/spinner'); ?>
				<span class="sr-only">Loading...</span>
			</div>
		</div>
		<div>
			<ul id="td-search-results" class="space-y-2" style="list-style: none; padding: 0; margin: 0;">
				<li class=" flex items-center justify-center text-slate-500">
					<span> <?php _e('Search your documentation', 'thrivedesk'); ?></span>
				</li>
			</ul>
		</div>
	</div>
</div> <!-- /Modal body  -->

</div>