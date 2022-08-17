<div class="w-full bg-gray-200 py-6 px-8">
	<h1 class="pb-3 text-left text-lg font-extrabold">Tickets</h1>
	<div class="rounded-lg shadow-md sm:rounded-lg bg-white">
		<div class="flex justify-between items-center px-6 py-5">
			<form class="flex items-center">
				<div class="relative w-full">
					<div class="flex absolute inset-y-0 left-0 items-center pl-3 pointer-events-none">
						<svg aria-hidden="true" class="w-5 h-5 text-gray-500 " fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path></svg>
					</div>
					<input type="text" id="simple-search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5  dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Search" required />
				</div>
			</form>
			<button type="submit" id="openConversationModal" class="p-2.5 ml-2 text-sm font-medium text-white bg-blue-700 rounded-lg border 
			border-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 
			dark:hover:bg-blue-700 dark:focus:ring-blue-800" data-modal-toggle="tdConversationModal">
				<span class="p-3">Open New Ticket</span>
			</button>
		</div>
		<table class="w-full text-sm text-left text-gray-500 ">
			<thead class="text-xs text-gray-700 bg-gray-100">
			<tr>
				<th scope="col" class="py-4 px-6 w-28">
					Status
				</th>
				<th scope="col" class="py-4 px-6 w-auto">
					Ticket
				</th>
				<th scope="col" class="py-4 px-6 w-40 text-center">
					Last replied by
				</th>
				<th scope="col" class="py-4 px-6 w-36 text-center">
					Last Update
				</th>
				<th scope="col" class="py-4 px-6 w-36 text-right">
					Actions
				</th>
			</tr>
			</thead>
			<tbody>
			<tr class="bg-white border-b hover:bg-gray-50 cursor-pointer">
				<th scope="row" class="py-4 px-6 font-medium text-center whitespace-nowrap">
					<span class="px-2 py-1 bg-gray-300 rounded-full">Closed</span>
				</th>
				<td class="py-4 px-6">
					<div class="flex flex-col justify-start">
						<span class="text-blue-800 font-semibold">#8746836</span>
						<span class="font-bold text-gray-600 text-base">Privacy Error</span>
						<p>Lorem ipsum dolor sit amet consectetur adipisicing elit.</p>
					</div>
				</td>
				<td class="py-4 px-6 text-center">
					Parvez Akhter
				</td>
				<td class="py-4 px-6 text-center">
					5 mins ago
				</td>
				<td class="py-4 px-6 text-right">
<!--					<a href="#" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Edit</a>-->
                    <br>
					<a href="<?php echo home_url(). '/conversation_details_page/action=details_page'; ?>" class="font-medium
					text-blue-600
					dark:text-blue-500
					hover:underline">View
                        Ticket</a>
				</td>
			</tr>
            <tr class="bg-white border-b hover:bg-gray-50 cursor-pointer">
                <th scope="row" class="py-4 px-6 font-medium text-center whitespace-nowrap">
                    <span class="px-2 py-1 bg-gray-300 rounded-full">Active</span>
                </th>
                <td class="py-4 px-6">
                    <div class="flex flex-col justify-start">
                        <span class="text-blue-800 font-semibold">#8746837</span>
                        <span class="font-bold text-gray-600 text-base">API setup</span>
                        <p>Lorem ipsum dolor sit amet consectetur adipisicing elit.</p>
                    </div>
                </td>
                <td class="py-4 px-6 text-center">
                    Abu Huraira
                </td>
                <td class="py-4 px-6 text-center">
                    15 mins ago
                </td>
                <td class="py-4 px-6 text-right">
                    <!--					<a href="#" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Edit</a>-->
                    <br>
                    <a href="<?php echo home_url(). '/conversation_details_page/action=details_page'; ?>" class="font-medium
					text-blue-600
					dark:text-blue-500
					hover:underline">View
                        Ticket</a>
                </td>
            </tr>
            <tr class="bg-white border-b hover:bg-gray-50 cursor-pointer">
                <th scope="row" class="py-4 px-6 font-medium text-center whitespace-nowrap">
                    <span class="px-2 py-1 bg-gray-300 rounded-full">Pending</span>
                </th>
                <td class="py-4 px-6">
                    <div class="flex flex-col justify-start">
                        <span class="text-blue-800 font-semibold">#8746838</span>
                        <span class="font-bold text-gray-600 text-base">Custom Form does not load</span>
                        <p>Lorem ipsum dolor sit amet consectetur adipisicing elit.</p>
                    </div>
                </td>
                <td class="py-4 px-6 text-center">
                    Sabir Mahmud
                </td>
                <td class="py-4 px-6 text-center">
                    1 day ago
                </td>
                <td class="py-4 px-6 text-right">
                    <!--					<a href="#" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Edit</a>-->
                    <br>
                    <a href="<?php echo home_url(). '/conversation_details_page/action=details_page'; ?>" class="font-medium
					text-blue-600
					dark:text-blue-500
					hover:underline">View
                        Ticket</a>
                </td>
            </tr>

			</tbody>

		</table>
		<div class="py-4 text-right pr-4">
			<ul class="inline-flex -space-x-px">
				<li>
					<a href="#" class="py-2 px-3 ml-0 leading-tight text-gray-500 bg-white rounded-l-lg border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">Previous</a>
				</li>
				<li>
					<a href="#" class="py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">1</a>
				</li>
				<li>
					<a href="#" class="py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">2</a>
				</li>
				<li>
					<a href="#" aria-current="page" class="py-2 px-3 text-blue-600 bg-blue-50 border border-gray-300 hover:bg-blue-100 hover:text-blue-700 dark:border-gray-700 dark:bg-gray-700 dark:text-white">3</a>
				</li>
				<li>
					<a href="#" class="py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">4</a>
				</li>
				<li>
					<a href="#" class="py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">5</a>
				</li>
				<li>
					<a href="#" class="py-2 px-3 leading-tight text-gray-500 bg-white rounded-r-lg border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">Next</a>
				</li>
			</ul>
		</div>
	</div>
</div>

<!-- Main modal -->
<div id="tdConversationModal" style="display: none" tabindex="-1" class="td-modal overflow-y-auto overflow-x-hidden hidden
fixed top-0
right-0
left-0 z-50
w-full md:inset-0 h-modal md:h-full justify-center items-center flex" aria-modal="true" role="dialog">
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
            <div class="p-6 space-y-6">
                <form class="flex items-center">
                    <label for="td-search" class="sr-only">Search</label>
                    <div class="relative w-full">
                        <div class="flex absolute inset-y-0 left-0 items-center pl-3 pointer-events-none">
                            <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path></svg>
                        </div>
                        <input type="text" id="td-search" class="bg-gray-50 border border-gray-300 text-gray-900
                        text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5  dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Search" required="">
                    </div>

                    <button type="submit" class="p-2.5 ml-2 text-sm font-medium text-white bg-blue-700 rounded-lg border border-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <span class="sr-only">Search</span>
                    </button>
                </form>
                <div class="dropdownClass"
                >
                    <ul class="py-1">
                        <li class="px-3 py-2 cursor-pointer hover:bg-gray-200"
                        >
                            hhhkk
                        </li>
                        <li class="px-3 py-2 text-center">
                            No Matching Results
                        </li>
                    </ul>
                </div>

            </div>
            <!-- Modal footer -->
            <div class="flex items-center p-6 space-x-2 rounded-b border-t border-gray-200 dark:border-gray-600">
                <a href="<?php echo get_page_link( get_post(getSelectedHelpdeskOptions('td_helpdesk_options')['td_form_page_id']))?>" target="_blank" class="text-center text-gray-600 text-sm">
                    Create new
                </a>
            </div>
        </div>
    </div>
</div>

