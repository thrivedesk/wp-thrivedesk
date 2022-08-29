<?php
$td_reply_nonce = wp_create_nonce('td-reply-conversation-action');

if (isset($_GET['conversation_id'])) {
	$conversation =  \ThriveDesk\Conversations\Conversation::get_conversation($_GET['conversation_id']);
}
?>
<?php if ($conversation): ?>
<div class="td-portal-conversations prose max-w-none">
    <!-- header  -->
    <div class="flex items-center">
       <div class="flex-auto">
        <span>#<?php echo $conversation['ticket_id'];?></span>
            <h2 class="text-2xl font-bold mt-0 mb-1 text-black"><?php echo $conversation['subject']?></h2>
            <span class="text-sm">
                <?php _e('Last update', 'thrivedesk'); ?>:
                <?php echo diff_for_humans($conversation['updated_at']) ?>
            </span>
       </div>
       <span class="status status-<?php echo strtolower($conversation['status']); ?>"><?php echo $conversation['status']; ?></span>
    </div>
    <!-- conversations  -->
    <div class="mt-5 space-y-6">
        <?php foreach ($conversation['events'] as $event): ?>
            <div class="td-conversation <?php echo $event['actor_type'] == 'ThriveDesk\\Models\\User' ? 'actor-contact' : 'actor-agent';?>">
                <div class="td-conversation-header">
                    <div class="flex items-center space-x-2 flex-auto">
                        <img class="w-8 h-8 rounded-full m-0" src="<?php echo $event['actor']['avatar'] ?? '' ?>"
                                alt="<?php echo $event['actor']['name'] ?? '' ?> avatar" />
                        <span class="font-bold"><?php echo $event['actor']['name']; ?></span>
                        <span><?php echo $event['action'];?></span>
                    </div>
                    <span class="text-sm"><?php echo diff_for_humans($event['created_at']); ?></span>
                </div>
                <div class="td-conversation-body">
                    <?php if ($event['event'] && $event['event']['html_body']): ?>
                        <?php echo \ThriveDesk\Conversations\Conversation::validate_conversation_body($event['event']['html_body']); ?>
                    <?php elseif($event['event'] && $event['event']['text_body']): ?>
                        <?php echo $event['event']['text_body']; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Reply editor -->
        <div class="mt-10 pt-10 border-t">
            <form action="" id="td_conversation_reply" method="POST">
                <input type="hidden" id="td_reply_none" value="<?php echo $td_reply_nonce; ?>">
                <input type="hidden" id="td_conversation_id" value="<?php echo $_GET['conversation_id']; ?>">
                <?php wp_editor('', 'td_conversation_editor', ['editor_height' => '120'] ); ?>

                <button type="submit" id="td_conversation_reply_submit" data-nonce="<?php echo esc_attr($td_reply_nonce); ?>"
                        class="td-btn-primary mt-6 px-10">
                    <?php _e('Reply', 'thrivedesk'); ?>
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
