<?php

use ThriveDesk\Conversations\Conversation;
use ThriveDesk\Services\PortalService;

$td_reply_nonce = wp_create_nonce('td-reply-conversation-action');
const ACTOR_TYPE = 'ThriveDesk\\Models\\User';

$url_parts = add_query_arg( NULL, NULL );
$parts = (parse_url($url_parts, PHP_URL_QUERY));
parse_str($parts, $query_params);

if (isset($query_params['td_conversation_id'])) {
	$conversation =  Conversation::get_conversation($query_params['td_conversation_id']);
	$is_portal_available = (new PortalService())->has_portal_access();
}
?>
<?php if ($is_portal_available && $conversation): ?>
<div id="thrivedesk" class="td-portal-conversations space-y-4">
    
    <a href="<?php echo get_permalink(); ?>" class="border rounded-full px-2.5 py-1 bg-white hover:bg-slate-50">
        <span>←</span>
        <span><?php _e('Back to tickets', 'thrivedesk'); ?></span>
    </a>
    <!-- header  -->
    <div class="flex items-center">
        <div class="flex-auto">
            <div class="flex space-x-1 text-slate-500">
                <span>[#<?php echo $conversation['ticket_id'];?>]</span>
                <span><?php echo diff_for_humans($conversation['updated_at']) ?></span>
            </div>
            <h1 class="text-2xl font-bold mt-0 mb-1 text-black"><?php echo $conversation['subject']?></h1>
        </div>
        <span class="status status-<?php echo strtolower($conversation['status']); ?>"><?php echo $conversation['status']; ?></span>
    </div>
    <!-- conversations  -->
    <div class="space-y-4">
        <?php foreach ($conversation['events'] as $event): ?>
	        <?php if ($event['event'] && $event['action'] !== 'note'): ?>
                <?php $actor_name = $event['actor']['name'] ?? ''; ?>
                <div class="td-conversation <?php echo $event['actor_type'] == ACTOR_TYPE ? 'actor-contact' : 'actor-agent';?>">
                    <div class="td-conversation-header">
                        <div class="flex items-center space-x-2 flex-auto">
                            <img class="w-8 h-8 rounded-full m-0"
                                 src="<?php echo $event['actor']['avatar'] ??
                                                 get_gravatar_url(wp_get_current_user()->user_email) ?>"
                                 alt="<?php echo $actor_name ?> avatar" />
                            <span class="font-bold"><?php echo $actor_name; ?></span>
                            <span><?php echo $event['action'];?></span>
                        </div>
                        <span class="text-sm ml-auto text-slate-800/50"><?php echo diff_for_humans($event['created_at']); ?></span>
                    </div>
                    <div class="td-conversation-body py-4" dir="auto">
				        <?php if ($event['event']['html_body']): ?>
					        <?php echo Conversation::validate_conversation_body($event['event']['html_body']); ?>
				        <?php elseif($event['event']['text_body']): ?>
					        <?php echo $event['event']['text_body']; ?>
				        <?php endif; ?>
                    </div>
                </div>
	        <?php endif; ?>
        <?php endforeach; ?>

        <!-- Reply editor -->
        <div>
            <form action="" id="td_conversation_reply" method="POST">
                <input type="hidden" id="td_reply_none" value="<?php echo $td_reply_nonce; ?>">
                
                <?php
                echo '<input type="hidden" id="td_conversation_id" value="'. $query_params['td_conversation_id'] .'">'
                ?>
                
                <?php wp_editor('', 'td_conversation_editor', ['editor_height' => '120'] ); ?>

                <button type="submit" id="td_conversation_reply_submit" data-nonce="<?php echo esc_attr($td_reply_nonce); ?>"
                        class="td-btn-primary px-8 mt-6">
		            <?php _e('Reply', 'thrivedesk'); ?>
                    <span id="td-reply-spinner" style="display: none;">
                        <?php thrivedesk_view('/icons/spinner'); ?>
                    </span>
                </button>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
    <div class="p-10 text-center my-10 bg-rose-50 border-2 border-dashed border-rose-200 text-rose-500 rounded font-medium">
        <span><?php _e('Your subscription plan does not support WPPortal feature. Please contact ThriveDesk for more information.', 'thrivedesk'); ?></span>
    </div>
<?php endif; ?>
