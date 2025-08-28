<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use ThriveDesk\Conversations\Conversation;
use ThriveDesk\Services\PortalService;

$td_reply_nonce = wp_create_nonce('td-reply-conversation-action');
const ACTOR_TYPE = 'ThriveDesk\\Models\\User';

$url_parts = add_query_arg( NULL, NULL );
$parts = (parse_url($url_parts, PHP_URL_QUERY));
parse_str($parts, $query_params);

$is_portal_available = false;
$conversation_exists = false;
$conversation_id = isset( $query_params['td_conversation_id'] ) ? $query_params['td_conversation_id'] : 0;

if ($conversation_id && $conversation_id !== '0') {
	$conversation =  Conversation::get_conversation($conversation_id);
	
	$is_portal_available = (new PortalService())->has_portal_access();
	
	// Check if conversation exists and is valid
	$conversation_exists = !empty($conversation) && !isset($conversation['wp_error']);
}
?>
<?php if ($is_portal_available && $conversation_exists): ?>
<div id="thrivedesk" class="td-portal-conversations space-y-4">
    
                    <a href="<?php echo esc_url(get_permalink()); ?>" class="border rounded-full px-2.5 py-1 bg-white hover:bg-slate-50">
        <span>←</span>
        <span><?php esc_html_e('Back to tickets', 'thrivedesk'); ?></span>
    </a>
    <!-- header  -->
    <div class="flex items-center">
        <div class="flex-auto">
            <div class="flex space-x-1 text-slate-500">
                <span>[#<?php echo esc_html($conversation['ticket_id'] ?? ''); ?>]</span>
                <span><?php echo esc_html(diff_for_humans($conversation['updated_at'] ?? '')); ?></span>
            </div>
            <h1 class="text-2xl font-bold mt-0 mb-1 text-black"><?php echo esc_html($conversation['subject'] ?? ''); ?></h1>
        </div>
        <span class="status status-<?php echo esc_attr(strtolower($conversation['status'] ?? 'unknown')); ?>"><?php echo esc_html($conversation['status'] ?? 'Unknown'); ?></span>
    </div>
    <!-- conversations  -->
    <div class="space-y-4">
        <?php if (!empty($conversation['events']) && is_array($conversation['events'])): ?>
            <?php foreach ($conversation['events'] as $event): ?>
	        <?php if ($event['event'] && $event['action'] !== 'note'): ?>
                <?php $actor_name = $event['actor']['name'] ?? ''; ?>
                <div class="td-conversation <?php echo $event['actor_type'] == ACTOR_TYPE ? 'actor-contact' : 'actor-agent';?>">
                    <div class="td-conversation-header">
                        <div class="flex items-center space-x-2 flex-auto">
                            <img class="w-8 h-8 rounded-full m-0"
                                 src="<?php echo esc_url($event['actor']['avatar'] ??
                                                 get_gravatar_url(wp_get_current_user()->user_email)); ?>"
                                 alt="<?php echo esc_attr($actor_name); ?> avatar" />
                            <span class="font-bold"><?php echo esc_html($actor_name); ?></span>
                            <span><?php echo esc_html($event['action']);?></span>
                        </div>
                        <span class="text-sm ml-auto text-slate-800/50"><?php echo esc_html(diff_for_humans($event['created_at'])); ?></span>
                    </div>
                    <div class="td-conversation-body py-4" dir="auto">
				        <?php if ($event['event']['html_body']): ?>
					        <?php echo wp_kses_post(Conversation::validate_conversation_body($event['event']['html_body'])); ?>
				        <?php elseif($event['event']['text_body']): ?>
					                                <?php echo wp_kses_post($event['event']['text_body']); ?>
				        <?php endif; ?>
                    </div>
                </div>
	        <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="p-4 text-center text-gray-500">
                <?php esc_html_e('No conversation events found.', 'thrivedesk'); ?>
            </div>
        <?php endif; ?>

        <!-- Reply editor -->
        <div>
            <form action="" id="td_conversation_reply" method="POST">
                <input type="hidden" id="td_reply_nonce" value="<?php echo esc_attr($td_reply_nonce); ?>">
                
                <?php
                echo '<input type="hidden" id="td_conversation_id" value="'. esc_attr($conversation_id) .'">'
                ?>
                
                <?php wp_editor('', 'td_conversation_editor', ['editor_height' => '120'] ); ?>

                <button type="submit" id="td_conversation_reply_submit" data-nonce="<?php echo esc_attr($td_reply_nonce); ?>"
                        class="td-btn-primary px-8 mt-6">
		                                <?php esc_html_e('Reply', 'thrivedesk'); ?>
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
        <?php if (!$is_portal_available): ?>
            <span><?php esc_html_e('Your subscription plan does not support WPPortal feature. Please contact ThriveDesk for more information.', 'thrivedesk'); ?></span>
        <?php elseif (!$conversation_exists): ?>
            <div class="space-y-2">
                <div class="text-lg font-semibold"><?php esc_html_e('Conversation Not Found', 'thrivedesk'); ?></div>
                <div><?php esc_html_e('The requested conversation does not exist or you do not have permission to view it.', 'thrivedesk'); ?></div>
                <div class="mt-4">
                    <a href="<?php echo esc_url(get_permalink()); ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        <span>←</span>
                        <span class="ml-2"><?php esc_html_e('Back to Conversations', 'thrivedesk'); ?></span>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <span><?php esc_html_e('Unable to load conversation. Please try again later.', 'thrivedesk'); ?></span>
        <?php endif; ?>
    </div>
<?php endif; ?>
