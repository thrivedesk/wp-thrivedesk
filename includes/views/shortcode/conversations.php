<?php

$conversations =  \ThriveDesk\Conversations\Conversation::get_conversations();
$conversation_data = $conversations['data'] ?? [];
$links = $conversations['meta']['links'] ?? [];

?>

<div class="w-full bg-gray-50 p-10 td-portal" style="margin-top: 20px;">
    <div class="rounded-lg shadow-md sm:rounded-lg bg-white">
        <div class="flex justify-between items-center px-6 py-5">
            <h3 class="font-extrabold !m-0"><?php _e('Tickets'); ?></h3>
            <button type="submit" id="openConversationModal" class="p-2.5 ml-2 text-sm font-medium text-white bg-blue-700 rounded-lg border
			border-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300" data-modal-toggle="tdConversationModal">
                <span class="p-3"><?php _e('Open New Ticket', 'thrivedesk'); ?></span>
            </button>
        </div>

        <table class="w-full text-sm text-left text-gray-500 !m-0">
            <thead class="text-xs text-gray-700 bg-gray-100">
            <tr>
                <th scope="col" class="py-4 px-6 w-28">
					<?php _e('Status', 'thrivedesk'); ?>
                </th>
                <th scope="col" class="py-4 px-6 w-auto">
					<?php _e('Ticket', 'thrivedesk'); ?>
                </th>
                <th scope="col" class="py-4 px-6 w-40 text-center">
					<?php _e('Last replied by', 'thrivedesk'); ?>
                </th>
                <th scope="col" class="py-4 px-6 w-36 text-center">
					<?php _e('Last update', 'thrivedesk'); ?>
                </th>
                <th scope="col" class="py-4 px-6 w-36 text-right">
					<?php _e('Actions', 'thrivedesk'); ?>
                </th>
            </tr>
            </thead>
            <tbody>
			<?php foreach($conversation_data as $key => $conversation): ?>
                <tr class="bg-white border-b hover:bg-gray-50 cursor-pointer">
                    <th scope="row" class="py-4 px-6 font-medium text-center whitespace-nowrap">
                        <span class="px-2 py-1 bg-gray-300 rounded-full"><?php echo $conversation['status']; ?></span>
                    </th>
                    <td class="py-4 px-6">
                        <div class="flex flex-col justify-start">
                            <span class="text-blue-800 font-semibold">#<?php echo $conversation['ticket_id']; ?></span>
                            <span class="font-bold text-gray-600 text-base"><?php echo $conversation['subject'];
								?></span>
                            <p><?php echo $conversation['excerpt']; ?>.</p>
                        </div>
                    </td>
                    <td class="py-4 px-6 text-center">
						<?= $conversation['last_actor']['name'] ?? '-' ?>
                    </td>
                    <td class="py-4 px-6 text-center">
						<?= diff_for_humans($conversation['updated_at']) ?>
                    </td>
                    <td class="py-4 px-6 text-right">
                        <br>
                        <a href="<?php echo get_permalink() .'?conversation_id='.$conversation['id']; ?>"
                           class="font-medium text-blue-600 hover:underline"><?php _e('View Ticket', 'thrivedesk'); ?></a>
                    </td>
                </tr>
			<?php endforeach; ?>
            </tbody>
        </table>
		<?php if (!count($conversations)): ?>
            <div class="p-10 text-center">
                <span><?php _e('No tickets found. Open new ticket and start the conversation', 'thrivedesk'); ?></span>
            </div>
		<?php endif ?>

        <?php if($links): ?>
        <div class="py-4 text-right pr-4">
            <ul class="inline-flex -space-x-px">
				<?php foreach ($links as $key => $link): ?>
					<?php
					$params =  parse_url($link['url'] ?? '', PHP_URL_QUERY);
					parse_str($params, $query);
					$page = $query['page'] ?? 1;
					?>
                    <li>
                        <a href="<?= $link['url'] ? get_permalink() .'?cv_page='.$page : 'javascript:void(0)' ?>"
                           class="<?= $link['active'] ? 'text-white bg-blue-600' : ''; ?> py-2 px-3
                        ml-0 leading-tight text-gray-500 bg-white  border border-gray-300
                        hover:bg-gray-100 hover:text-gray-700<?= $key == count($links)-1 ?
							   'rounded-r-lg' : '' ?> <?= $key == 0 ? 'rounded-l-lg' : '' ?>"><?=
							$link['label']
							?>
                        </a>
                    </li>
				<?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
thrivedesk_view('shortcode/modal');
?>
