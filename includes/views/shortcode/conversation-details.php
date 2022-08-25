<?php
$td_reply_nonce = wp_create_nonce('td-reply-conversation-action');

if (isset($_GET['conversation_id'])) {
	$conversation =  \ThriveDesk\Conversations\Conversation::get_conversation($_GET['conversation_id']);
}
?>

<?php if ($conversation): ?>
    <div class="w-full bg-gray-200 py-6 px-8 td-shortcode-body" style="margin: 20px 0 20px 0;">
        <div class="py-4 px-2 flex flex-col justify-start">
            <span class="font-semibold">#<?= $conversation['ticket_id'] . ' - ' .
                                             $conversation['subject'] ; ?></span>
            <p class="text-sm mt-2"><span>Last update: <?= diff_for_humans
					($conversation['updated_at']) ?></span></p>

        </div>

        <div class="py-4 px-2 rounded-lg shadow-md sm:rounded-lg bg-white border">
            <div class="px-6 py-4">
                <div class="flex justify-between border-b">
                    <h1 class="pb-3 text-left text-lg font-extrabold">Conversation </h1>
                    <div class="font-medium text-center whitespace-nowrap">
                        <span class="px-2 py-1 bg-gray-300 rounded-full"><?php echo $conversation['status']; ?></span>
                    </div>

                </div>
                <div class="w-full text-sm text-lef border-b py-5 mb-4">
					<?php foreach ($conversation['events'] as $event): ?>
                        <div class=" <?php echo $event['actor_type'] == 'ThriveDesk\\Models\\User' ? 'bg-blue-100' :
							'bg-gray-100' ?>
                        rounded
                        border-2 mb-5">
                            <div class="px-6 py-4 pt-3">
                                <div class="flex border-b">
                                    <div class="flex-none w-14 h-14">
                                        <img class="w-10 h-10 rounded-full" src="<?= $event['actor']['avatar'] ?? '' ?>"
                                             alt="<?= $event['actor']['name'] ?? '' ?>
                                        avatar" />
                                    </div>
                                    <div class="flex-initial w-64">
                                        <h3 class="font-bold"><?= $event['actor']['name']; ?></h3>
                                        <p class="text-sm"><?= diff_for_humans($event['created_at']); ?></p>
                                    </div>
                                </div>
                                <div class="prose py-4">
									<?php if ($event['event'] && $event['event']['html_body']): ?>
										<?= \ThriveDesk\Conversations\Conversation::validate_conversation_body($event['event']['html_body']); ?>
									<?php elseif($event['event'] && $event['event']['text_body']): ?>
										<?= $event['event']['text_body']; ?>
									<?php endif; ?>
                                </div>
                            </div>
                        </div>
					<?php endforeach; ?>
                </div>
                <div class="py-6">
                    <form action="" id="td_conversation_reply" method="POST">
                        <input type="hidden" id="td_reply_none" value="<?php echo $td_reply_nonce; ?>">
                        <input type="hidden" id="td_conversation_id" value="<?php echo $_GET['conversation_id']; ?>">
                        <div class="mb-6">
							<?php wp_editor('', 'td_conversation_editor', ['editor_height' => '120'] ); ?>
                        </div>

                        <div class="mb-6">
                            <button type="submit" id="td_conversation_reply_submit"
                                    data-nonce="<?php echo esc_attr($td_reply_nonce); ?>"
                                    class="text-white bg-blue-700
                                    hover:bg-blue-800
                                    focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-full sm:w-auto px-5 py-2.5 text-center">
                                Reply
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
