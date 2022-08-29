<?php

$conversations =  \ThriveDesk\Conversations\Conversation::get_conversations();
$conversation_data = $conversations['data'] ?? [];
$links = $conversations['meta']['links'] ?? [];

?>

<div id="thrivedesk" class="w-full prose max-w-none">
    <div class="td-container">
        <div class="td-portal-header">
            <input type="text" class="td-ticket-search" placeholder="<?php _e('Search...')?>">
            <button type="submit" id="openConversationModal" class="td-btn-primary" data-modal-toggle="tdConversationModal">
                <span><?php _e('Create a new ticket', 'thrivedesk'); ?></span>
            </button>
        </div>

        <table class="td-portal-tickets !m-0">
            <thead>
                <tr>
                    <th scope="col" class="w-28">
                        <?php _e('Status', 'thrivedesk'); ?>
                    </th>
                    <th scope="col" class="w-auto">
                        <?php _e('Ticket', 'thrivedesk'); ?>
                    </th>
                    <th scope="col" class="w-40 text-center">
                        <?php _e('Last replied by', 'thrivedesk'); ?>
                    </th>
                    <th scope="col" class="w-36 text-center">
                        <?php _e('Last update', 'thrivedesk'); ?>
                    </th>
                    <th scope="col" class="w-36"></th>
                </tr>
            </thead>
            <tbody>
			<?php foreach($conversation_data as $key => $conversation): ?>
                <tr>
                    <td scope="row" class="text-center align-middle">
                        <span class="status status-<?php echo strtolower($conversation['status']); ?>">
                            <?php echo $conversation['status']; ?>
                        </span>
                    </td>
                    <td>
                    <a href="<?php echo get_permalink() .'?conversation_id='.$conversation['id']; ?>">
                            <div class="text-base font-medium">
                                <span class="text-slate-500">(#<?php echo $conversation['ticket_id']; ?>)</span>
                                <span class="text-black"><?php echo $conversation['subject'];?></span>
                            </div>
                            <div><?php echo $conversation['excerpt']; ?>.</div>
                        </a>
                    </td>
                    <td class="text-center align-middle">
						<?php echo $conversation['last_actor']['name'] ?? '-' ?>
                    </td>
                    <td class="text-center align-middle">
						<?php echo diff_for_humans($conversation['updated_at']) ?>
                    </td>
                    <td class="text-center align-middle">
                        <a href="<?php echo get_permalink() .'?conversation_id='.$conversation['id']; ?>" class="td-btn-default">
                           <?php _e('View', 'thrivedesk'); ?>
                        </a>
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
        <div class="td-portal-footer">
            <ul class="td-paginator not-prose divide-x">
				<?php foreach ($links as $key => $link): ?>
					<?php
					$params =  parse_url($link['url'] ?? '', PHP_URL_QUERY);
					parse_str($params, $query);
					$page = $query['page'] ?? 1;
					?>
                    <li class="<?php echo $link['active'] ? 'text-white bg-blue-600' : ''; ?>">
                        <a href="<?php echo $link['url'] ? get_permalink() .'?cv_page='.$page : 'javascript:void(0)' ?>">
                            <?php echo $link['label']?>
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
