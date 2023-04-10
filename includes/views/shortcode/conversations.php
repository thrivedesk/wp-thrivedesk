<?php
use ThriveDesk\Conversations\Conversation;
use ThriveDesk\Services\PortalService;

$conversations       = Conversation::get_conversations();
$conversation_data   = isset($conversations['data']) ? Conversation::td_conversation_sort_by_status($conversations['data']) : [];
$links               = $conversations['meta']['links'] ?? [];
$is_portal_available = (new PortalService())->has_portal_access();

?>

<div id="thrivedesk" class="w-full prose prose-slate max-w-none">
    <div class="td-container">

        <?php if (!$is_portal_available): ?>
        <div class="p-10 text-center">
            <span><?php _e('Portal feature is not available at this moment.', 'thrivedesk'); ?></span>
        </div>

        <?php else: ?>
            <div class="td-portal-header">
                <input type="search" class="td-ticket-search" id="td-ticket-search" placeholder="<?php _e('Search...')?>">
                <button type="submit" id="openConversationModal" class="td-btn-primary" data-modal-toggle="tdConversationModal">
                    <span><?php _e('Create a new ticket', 'thrivedesk'); ?></span>
                </button>
            </div>

            <table class="td-portal-tickets !m-0" id="conversation-table">
                <thead>
                <tr>
                    <th scope="col" class="w-28">
				        <?php _e('Status', 'thrivedesk'); ?>
                    </th>
                    <th scope="col" class="w-auto">
				        <?php _e('Ticket', 'thrivedesk'); ?>
                    </th>
                    <th scope="col" class="w-36 text-center">
				        <?php _e('Last update', 'thrivedesk'); ?>
                    </th>
                    <th scope="col" class="w-36"></th>
                </tr>
                </thead>
                <tbody>
		        <?php if (empty($conversation_data)): ?>
                    <tr id="no-results">
                        <td colspan="5" class="text-center">
                            <span><?php _e('No tickets found. Open new ticket and start the conversation.', 'thrivedesk'); ?></span>
                        </td>
                    </tr>
		        <?php endif; ?>
		        <?php foreach($conversation_data as $key => $conversation): ?>
                    <tr>
                        <td scope="row" class="text-center align-middle">
                        <span class="status status-<?php echo strtolower($conversation['status']); ?>">
                            <?php echo $conversation['status']; ?>
                        </span>
                        </td>
                        <td>
					        <?php
					        $conv_page_url = add_query_arg( array(
						        'td_conversation_id' => $conversation['id']
					        ), get_permalink() );
					        ?>
                            <a href="<?php echo get_permalink() .'?td_conversation_id='.$conversation['id']; ?>">
                                <div class="text-base font-medium">
                                    <span>(#<?php echo $conversation['ticket_id']; ?>)</span>
                                    <span class="text-black"><?php echo $conversation['subject'];?></span>
                                </div>
                                <div class="font-normal text-sm"><?php echo $conversation['excerpt']; ?>.</div>
                            </a>
                        </td>
                        <td class="text-center align-middle">
					        <?php echo diff_for_humans($conversation['updated_at']) ?>
                        </td>
                        <td class="text-center align-middle">
                            <a href="<?php echo $conv_page_url; ?>" class="td-btn-default">
						        <?php _e('View', 'thrivedesk'); ?>
                            </a>
                        </td>
                    </tr>
		        <?php endforeach; ?>
                <tr id="no-results" style="display: none;">
                    <td colspan="5" class="text-center">
                        <span><?php _e('No tickets found. Open new ticket and start the conversation.', 'thrivedesk'); ?></span>
                    </td>
                </tr>
                </tbody>
            </table>

            <div class="td-portal-footer-container">
                <a href="https://www.thrivedesk.com/introducing-wpportal" target="_blank">
                    <span>Powered by</span>
                    <img src="<?php echo THRIVEDESK_PLUGIN_ASSETS . '/images/thrivedesk.png'; ?>" alt="" style="height: 20px;">
                </a>
                <?php
                if ( $links ): ?>
                    <div class="py-3">
                        <ul class="td-paginator not-prose divide-x">
                            <?php
                            foreach ( $links as $key => $link ): ?>
                                <?php
                                $params = parse_url( $link['url'] ?? '', PHP_URL_QUERY );
                                parse_str( $params, $query );
                                $page = $query['page'] ?? 1;
                                ?>
                                <li class="<?php
                                echo $link['active'] ? 'active' : ''; ?>">
                                    <a href="<?php
                                    echo $link['url'] ? get_permalink() . '?cv_page=' . $page : 'javascript:void(0)' ?>">
                                        <?php
                                        echo $link['label'] ?>
                                    </a>
                                </li>
                            <?php
                            endforeach; ?>
                        </ul>
                    </div>
                <?php
                endif; ?>
            </div>
        <?php
        endif; ?>
    </div>
</div>

<?php
thrivedesk_view('shortcode/modal');
?>
