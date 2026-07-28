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

$conversation_id = Conversation::sanitize_conversation_id($query_params['td_conversation_id'] ?? '');

$is_portal_available = (new PortalService())->has_portal_access();

if ($is_portal_available && $conversation_id !== '') {
    $conversation = Conversation::get_conversation($conversation_id);
    $conversation_exists = !empty($conversation) && !isset($conversation['wp_error']);
}
?>
<?php if ($conversation_exists): ?>
<?php
// precompute display data for the header + timeline
$status         = strtolower($conversation['status'] ?? 'unknown');
$status_label   = td_conversation_status_label($conversation['status'] ?? '');
$ticket_label   = $conversation['ticket_id'] ?? substr((string) $conversation_id, -6);
$ticket_label   = $ticket_label !== '' ? $ticket_label : '—';
$event_count    = !empty($conversation['events']) && is_array($conversation['events']) ? count($conversation['events']) : 0;
$updated_human  = diff_for_humans($conversation['updated_at'] ?? '');
?>
<div id="thrivedesk">
    <div class="td-portal-conversations">

        <div class="td-conv-toolbar">
            <a href="<?php echo esc_url(td_portal_back_to_list_url()); ?>" class="td-back-link">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m15 18-6-6 6-6"/>
                </svg>
                <span><?php esc_html_e('Back to tickets', 'thrivedesk'); ?></span>
            </a>
        </div>

        <div class="td-conv-layout">

            <!-- main column: header + timeline + compose -->
            <div class="td-conv-main">

                <header class="td-conv-header">
                    <div class="td-conv-meta">
                        <span class="td-badge td-badge-status-<?php echo esc_attr($status); ?>">
                            <?php echo esc_html($status_label); ?>
                        </span>
                        <span class="td-meta-sep" aria-hidden="true">·</span>
                        <span class="td-meta-id">#<?php echo esc_html($ticket_label); ?></span>
                        <span class="td-meta-sep" aria-hidden="true">·</span>
                        <span><?php
                            /* translators: %s: relative time */
                            echo esc_html(sprintf(__('Updated %s', 'thrivedesk'), $updated_human));
                        ?></span>
                        <?php if ($event_count): ?>
                            <span class="td-meta-sep" aria-hidden="true">·</span>
                            <span><?php
                                /* translators: %d: number of messages in the conversation thread */
                                echo esc_html(sprintf(
                                    _n('%d message', '%d messages', $event_count, 'thrivedesk'),
                                    $event_count
                                ));
                            ?></span>
                        <?php endif; ?>
                    </div>
                    <h1 class="td-conv-subject"><?php echo esc_html($conversation['subject'] ?? ''); ?></h1>
                </header>

                <div class="td-separator" role="separator"></div>

                <!-- timeline -->
                <div class="td-timeline" role="list">
                    <div class="td-timeline-rail" aria-hidden="true"></div>

                    <?php if (!empty($conversation['events']) && is_array($conversation['events'])): ?>
                        <?php foreach ($conversation['events'] as $idx => $event): ?>
                            <?php if (!empty($event['event']) && $event['action'] !== 'note'): ?>
                                <?php
                                    $is_contact  = $event['actor_type'] != ACTOR_TYPE;
                                    $actor_name  = $event['actor']['name'] ?? '';
                                    $actor_email = $event['actor']['email'] ?? wp_get_current_user()->user_email;
                                    $avatar_url  = $event['actor']['avatar'] ?? get_gravatar_url($actor_email);
                                    $initials    = strtoupper(mb_substr(trim((string) $actor_name), 0, 1) ?: 'U');
                                    $is_first    = $idx === 0;
                                    $event_time  = !empty($event['created_at']) ? strtotime($event['created_at']) : null;
                                    $time_label  = $event_time
                                        ? date_i18n(get_option('date_format') . ' · ' . get_option('time_format'), $event_time)
                                        : '';
                                ?>
                                <article class="td-timeline-event <?php echo $is_contact ? 'td-event-contact' : 'td-event-agent'; ?> <?php echo $is_first ? 'td-event-first' : ''; ?>"
                                         role="listitem">
                                    <span class="td-timeline-dot" aria-hidden="true">
                                        <?php if ($is_contact): ?>
                                            <span class="td-dot-inner"></span>
                                        <?php endif; ?>
                                    </span>
                                    <time class="td-timeline-time" datetime="<?php echo esc_attr($event['created_at'] ?? ''); ?>">
                                        <?php echo esc_html(diff_for_humans($event['created_at'])); ?>
                                        <?php if ($time_label): ?>
                                            <span class="td-timeline-time-abs"><?php echo esc_html($time_label); ?></span>
                                        <?php endif; ?>
                                    </time>
                                    <div class="td-card td-timeline-card">
                                        <header class="td-timeline-card-header">
                                            <div class="td-avatar td-avatar-sm" aria-hidden="true">
                                                <img src="<?php echo esc_url($avatar_url); ?>" alt="" loading="lazy" onerror="this.style.display='none';this.parentNode.innerHTML='<span class=\'td-avatar-fallback\'><?php echo esc_js($initials); ?></span>'">
                                            </div>
                                            <div class="td-timeline-card-identity">
                                                <span class="td-actor-name"><?php echo esc_html($actor_name); ?></span>
                                                <span class="td-actor-role <?php echo $is_contact ? 'td-actor-role-contact' : 'td-actor-role-agent'; ?>">
                                                    <?php echo $is_contact
                                                        ? esc_html__('You', 'thrivedesk')
                                                        : esc_html__('Agent', 'thrivedesk'); ?>
                                                </span>
                                            </div>
                                        </header>
                                        <div class="td-conversation-body" dir="auto">
                                            <?php if (!empty($event['event']['html_body'])): ?>
                                                <?php echo wp_kses_post(Conversation::validate_conversation_body($event['event']['html_body'])); ?>
                                            <?php elseif (!empty($event['event']['text_body'])): ?>
                                                <?php echo wp_kses_post($event['event']['text_body']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="td-timeline-event td-event-empty">
                            <span class="td-timeline-dot" aria-hidden="true"></span>
                            <div class="td-card td-timeline-card">
                                <div class="td-empty-state">
                                    <div class="td-empty-state-icon">
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                        </svg>
                                    </div>
                                    <div class="td-empty-state-title"><?php esc_html_e('No messages yet', 'thrivedesk'); ?></div>
                                    <div class="td-empty-state-text"><?php esc_html_e('Send the first reply below to start the conversation.', 'thrivedesk'); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Compose event -->
                    <article class="td-timeline-event td-event-compose" role="listitem">
                        <span class="td-timeline-dot td-timeline-dot-compose" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 5v14"/><path d="M5 12h14"/>
                            </svg>
                        </span>
                        <div class="td-card td-timeline-card td-reply-card">
                            <div class="td-timeline-card-header">
                                <div class="td-avatar td-avatar-sm td-avatar-compose" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                </div>
                                <div class="td-timeline-card-identity">
                                    <span class="td-actor-name"><?php esc_html_e('Your reply', 'thrivedesk'); ?></span>
                                    <span class="td-reply-hint"><?php esc_html_e('Goes to the agent assigned to this ticket.', 'thrivedesk'); ?></span>
                                </div>
                            </div>

                            <form action="" id="td_conversation_reply" method="POST">
                                <input type="hidden" id="td_reply_nonce" value="<?php echo esc_attr($td_reply_nonce); ?>">
                                <input type="hidden" id="td_conversation_id" value="<?php echo esc_attr($conversation_id); ?>">

                                <label for="td_conversation_editor" class="td-reply-label">
                                    <?php esc_html_e('Compose message', 'thrivedesk'); ?>
                                </label>
                                <?php wp_editor('', 'td_conversation_editor', ['editor_height' => '140']); ?>

                                <div class="td-reply-footer">
                                    <span class="td-reply-shortcut"><?php esc_html_e('Reply is sent when you submit the form.', 'thrivedesk'); ?></span>
                                    <button type="submit" id="td_conversation_reply_submit" data-nonce="<?php echo esc_attr($td_reply_nonce); ?>"
                                            class="td-btn td-btn-primary">
                                        <span><?php esc_html_e('Send reply', 'thrivedesk'); ?></span>
                                        <span id="td-reply-spinner" style="display: none;" class="td-spinner">
                                            <?php thrivedesk_view('/icons/spinner'); ?>
                                        </span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </article>
                </div>

            </div>

        </div>

    </div>
</div>

<?php else: ?>
<div id="thrivedesk">
    <div class="td-page">
        <div class="td-portal-notice">
            <?php if (!$is_portal_available): ?>
                <div><?php esc_html_e('Your subscription plan does not support the WP Portal feature. Please contact ThriveDesk for more information.', 'thrivedesk'); ?></div>
            <?php elseif (!$conversation_exists): ?>
                <div class="td-empty-state">
                    <div class="td-empty-state-icon">
                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>
                        </svg>
                    </div>
                    <div class="td-empty-state-title"><?php esc_html_e('Conversation not found', 'thrivedesk'); ?></div>
                    <div class="td-empty-state-text"><?php esc_html_e('The requested conversation does not exist or you do not have permission to view it.', 'thrivedesk'); ?></div>
                    <a href="<?php echo esc_url(td_portal_back_to_list_url()); ?>" class="td-btn td-btn-outline">
                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m15 18-6-6 6-6"/>
                        </svg>
                        <span><?php esc_html_e('Back to tickets', 'thrivedesk'); ?></span>
                    </a>
                </div>
            <?php else: ?>
                <div class="td-empty-state-text"><?php esc_html_e('Unable to load conversation. Please try again later.', 'thrivedesk'); ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>