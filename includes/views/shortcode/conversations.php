<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use ThriveDesk\Conversations\Conversation;
use ThriveDesk\Services\PortalService;

$conversations       = Conversation::get_conversations();
$conversation_data   = isset($conversations['data']) ? Conversation::td_conversation_sort_by_status($conversations['data']) : [];
$links               = $conversations['meta']['links'] ?? [];
$is_portal_available = (new PortalService())->has_portal_access();
$settings            = get_td_helpdesk_settings();
$ticket_page_id      = absint($settings['td_helpdesk_page_id'] ?? 0);
$ticket_page_url     = $ticket_page_id ? get_page_link($ticket_page_id) : '';
$has_knowledgebase   = ! empty($settings['td_knowledgebase_slug']);
$should_open_modal   = $has_knowledgebase && $ticket_page_id;

// new-ticket button state when no ticket page is configured — show it
// as disabled with an explanation instead of a broken # link
$new_ticket_disabled = empty($ticket_page_url);
$new_ticket_disabled_title = $new_ticket_disabled
    ? __('No ticket page is selected. Set one in ThriveDesk settings to enable creating new tickets.', 'thrivedesk')
    : __('Open the new ticket form', 'thrivedesk');
$new_ticket_label = $new_ticket_disabled
    ? __('New ticket (not configured)', 'thrivedesk')
    : __('New ticket', 'thrivedesk');

// tab counts (single pass over the sorted list)
$counts = ['all' => 0, 'active' => 0, 'open' => 0, 'pending' => 0, 'closed' => 0, 'resolved' => 0, 'on-hold' => 0];
foreach ($conversation_data as $_c) {
    $counts['all']++;
    $s = strtolower($_c['status'] ?? '');
    if (isset($counts[$s])) { $counts[$s]++; }
}
// "open" tab bundles active + open
$open_count = $counts['active'] + $counts['open'];

?>

<div id="thrivedesk">
    <div class="td-container">
        <?php if (!$is_portal_available): ?>
            <div class="td-page">
                <div class="td-portal-notice">
                    <div><?php esc_html_e('Your subscription plan does not support the WP Portal feature. Please contact ThriveDesk for more information.', 'thrivedesk'); ?></div>
                    <img src="https://media.thrivedesk.com/wp-content/uploads/2023/05/portal-mini.avif" alt="">
                </div>
            </div>
        <?php else: ?>

            <div class="td-page">

                <!-- header: search + actions -->
                <div class="td-portal-header">
                    <div class="td-search-wrap">
                        <svg class="td-search-icon" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.3-4.3"/>
                        </svg>
                        <input type="search" class="td-input" id="td-ticket-search" placeholder="<?php esc_attr_e('Search tickets…', 'thrivedesk'); ?>" autocomplete="off">
                        <button type="button" class="td-search-clear" id="td-search-clear" aria-label="<?php esc_attr_e('Clear search', 'thrivedesk'); ?>" hidden>
                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="td-portal-actions">
                        <button type="button" id="reloadTickets" class="td-btn td-btn-outline td-btn-sm" title="<?php esc_attr_e('Reload tickets', 'thrivedesk'); ?>">
                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
                                <path d="M21 3v5h-5"/>
                                <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/>
                                <path d="M3 21v-5h5"/>
                            </svg>
                            <span><?php esc_html_e('Reload', 'thrivedesk'); ?></span>
                        </button>
                        <a
                            href="<?php echo $new_ticket_disabled ? '#' : esc_url($ticket_page_url); ?>"
                            id="openConversationModal"
                            class="td-btn td-btn-primary td-btn-sm<?php echo $new_ticket_disabled ? ' td-btn-locked' : ''; ?>"
                            data-open-modal="<?php echo $should_open_modal && !$new_ticket_disabled ? 'true' : 'false'; ?>"
                            <?php echo $new_ticket_disabled ? 'aria-disabled="true" data-locked="true"' : ''; ?>
                            title="<?php echo esc_attr($new_ticket_disabled_title); ?>"
                        >
                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <?php if ($new_ticket_disabled): ?>
                                    <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                <?php else: ?>
                                    <path d="M5 12h14"/><path d="M12 5v14"/>
                                <?php endif; ?>
                            </svg>
                            <span><?php echo esc_html($new_ticket_label); ?></span>
                        </a>
                    </div>
                </div>

                <!-- filter tabs -->
                <div class="td-tabs" role="tablist" aria-label="<?php esc_attr_e('Filter by status', 'thrivedesk'); ?>">
                    <a href="<?php echo esc_url(remove_query_arg('cv_status', get_permalink())); ?>" class="is-active" role="tab" aria-selected="true" data-filter="all">
                        <?php esc_html_e('All', 'thrivedesk'); ?>
                        <span class="td-tab-count"><?php echo (int) $counts['all']; ?></span>
                    </a>
                    <a href="?cv_status=active" role="tab" data-filter="active">
                        <?php esc_html_e('Active', 'thrivedesk'); ?>
                        <span class="td-tab-count"><?php echo (int) $open_count; ?></span>
                    </a>
                    <a href="?cv_status=pending" role="tab" data-filter="pending">
                        <?php esc_html_e('Pending', 'thrivedesk'); ?>
                        <span class="td-tab-count"><?php echo (int) $counts['pending']; ?></span>
                    </a>
                    <a href="?cv_status=closed" role="tab" data-filter="closed">
                        <?php esc_html_e('Closed', 'thrivedesk'); ?>
                        <span class="td-tab-count"><?php echo (int) $counts['closed']; ?></span>
                    </a>
                </div>

                <!-- tickets list -->
                <div class="td-list-card td-card">
                    <div class="td-card-content">
                        <?php if (empty($conversation_data)): ?>
                            <div class="td-empty-state">
                                <div class="td-empty-state-icon">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                </div>
                                <div class="td-empty-state-title"><?php esc_html_e('No tickets yet', 'thrivedesk'); ?></div>
                                <div class="td-empty-state-text"><?php esc_html_e("Open a new ticket to start a conversation with the team. We typically reply within a few hours during business days.", 'thrivedesk'); ?></div>
                                <?php if ($new_ticket_disabled): ?>
                                    <button type="button" disabled class="td-btn td-btn-primary td-btn-locked"
                                            title="<?php echo esc_attr($new_ticket_disabled_title); ?>"
                                            aria-disabled="true">
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                        </svg>
                                        <span><?php esc_html_e('New ticket', 'thrivedesk'); ?></span>
                                    </button>
                                    <p class="td-empty-state-hint"><?php echo esc_html($new_ticket_disabled_title); ?></p>
                                <?php else: ?>
                                    <a href="<?php echo esc_url($ticket_page_url); ?>" class="td-btn td-btn-primary">
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M5 12h14"/><path d="M12 5v14"/>
                                        </svg>
                                        <span><?php esc_html_e('New ticket', 'thrivedesk'); ?></span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <table class="td-portal-tickets" id="conversation-table">
                                <colgroup>
                                    <col class="td-col-status">
                                    <col class="td-col-subject">
                                    <col class="td-col-time">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th class="td-ticket-cell-status"><?php esc_html_e('Status', 'thrivedesk'); ?></th>
                                        <th><?php esc_html_e('Subject', 'thrivedesk'); ?></th>
                                        <th class="td-ticket-cell-time"><?php esc_html_e('Last update', 'thrivedesk'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($conversation_data as $conversation):
                                    $conv_status   = strtolower($conversation['status'] ?? 'unknown');
                                    $conv_page_url = add_query_arg('td_conversation_id', $conversation['id']);
                                    $ticket_label  = $conversation['ticket_id'] ?? substr((string) ($conversation['id'] ?? ''), -6);
                                    $ticket_label  = $ticket_label !== '' ? $ticket_label : '—';
                                ?>
                                    <tr data-ticket-id="<?php echo esc_attr($conversation['id']); ?>"
                                        data-status="<?php echo esc_attr($conv_status); ?>"
                                        data-subject="<?php echo esc_attr(wp_strip_all_tags($conversation['subject'] ?? '')); ?>"
                                        data-excerpt="<?php echo esc_attr(wp_strip_all_tags($conversation['excerpt'] ?? '')); ?>"
                                        data-href="<?php echo esc_url($conv_page_url); ?>"
                                        tabindex="0"
                                        role="link"
                                        aria-label="<?php
                                            /* translators: 1: ticket subject, 2: ticket ID */
                                            echo esc_attr(sprintf(__('Open ticket %2$s: %1$s', 'thrivedesk'), wp_strip_all_tags($conversation['subject'] ?? ''), '#' . $ticket_label));
                                        ?>"
                                    >
                                        <td class="td-ticket-cell-status" data-label="<?php esc_attr_e('Status', 'thrivedesk'); ?>">
                                            <span class="td-badge td-badge-status-<?php echo esc_attr($conv_status); ?>">
                                                <?php echo esc_html($conversation['status']); ?>
                                            </span>
                                        </td>
                                        <td class="td-ticket-cell-subject-cell" data-label="<?php esc_attr_e('Subject', 'thrivedesk'); ?>">
                                            <div class="td-ticket-cell-subject">
                                                <a href="<?php echo esc_url($conv_page_url); ?>" class="td-ticket-link">
                                                    <span class="td-ticket-id-inline">[#<?php echo esc_html($ticket_label); ?>]</span>
                                                    <span class="td-ticket-subject-text"><?php echo esc_html($conversation['subject']); ?></span>
                                                </a>
                                                <?php if (!empty($conversation['excerpt'])): ?>
                                                    <span class="td-ticket-excerpt"><?php echo esc_html($conversation['excerpt']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="td-ticket-cell-time" data-label="<?php esc_attr_e('Last update', 'thrivedesk'); ?>">
                                            <?php echo esc_html(diff_for_humans($conversation['updated_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                    <!-- empty row JS toggles when search/filter hides everything -->
                                    <tr id="no-results" style="display: none;">
                                        <td colspan="3">
                                            <div class="td-empty-state">
                                                <div class="td-empty-state-icon">
                                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <circle cx="11" cy="11" r="8"/>
                                                        <path d="m21 21-4.3-4.3"/>
                                                    </svg>
                                                </div>
                                                <div class="td-empty-state-title"><?php esc_html_e('No matches', 'thrivedesk'); ?></div>
                                                <div class="td-empty-state-text"><?php esc_html_e('No tickets match your search. Try a different term or clear the search to see all tickets.', 'thrivedesk'); ?></div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- footer: powered-by + pagination -->
                <div class="td-portal-footer">
                    <a class="td-powered" href="<?php echo esc_url('https://www.thrivedesk.com/wordpress?utm_source=wpportal&utm_medium=' . get_site_url() . '&utm_campaign=powered-by'); ?>" target="_blank" rel="noopener">
                        <span><?php esc_html_e('Powered by', 'thrivedesk'); ?></span>
                        <img src="<?php echo esc_url(THRIVEDESK_PLUGIN_ASSETS . '/images/thrivedesk.png'); ?>" alt="ThriveDesk">
                    </a>

                    <?php if ($links): ?>
                        <nav aria-label="Page navigation">
                            <ul class="td-paginator">
                                <?php foreach ($links as $key => $link):
                                    $params = parse_url($link['url'] ?? '', PHP_URL_QUERY);
                                    $page = 1;
                                    if ($params) { parse_str($params, $query); $page = $query['page'] ?? 1; }
                                ?>
                                    <li class="<?php echo !empty($link['active']) ? 'pg-active' : ''; ?>">
                                        <?php if (!empty($link['url'])): ?>
                                            <a href="<?php echo esc_url(get_permalink() . '?cv_page=' . $page); ?>" aria-label="<?php echo esc_attr($link['label']); ?>">
                                        <?php else: ?>
                                            <span>
                                        <?php endif; ?>
                                            <?php echo esc_html($link['label']); ?>
                                        <?php if (!empty($link['url'])): ?>
                                            </a>
                                        <?php else: ?>
                                            </span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>

            </div>
        <?php endif; ?>
    </div>

    <?php thrivedesk_view('shortcode/modal');?>
</div>