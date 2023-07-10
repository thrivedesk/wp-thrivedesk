<?php
use ThriveDesk\Conversations\Conversation;
use ThriveDesk\Services\PortalService;

$conversations       = Conversation::get_conversations();
$conversation_data   = isset($conversations['data']) ? Conversation::td_conversation_sort_by_status($conversations['data']) : [];
$links               = $conversations['meta']['links'] ?? [];
$is_portal_available = (new PortalService())->has_portal_access();
$td_helpdesk_selected_option = get_td_helpdesk_options();
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
                <?php if (!isset($td_helpdesk_selected_option['knowledge_base_search_modal']) || $td_helpdesk_selected_option['knowledge_base_search_modal']): ?>

                    <button type="submit" id="openConversationModal" class="td-btn-primary" data-modal-toggle="tdConversationModal">
                        <span><?php _e('Create a new ticket', 'thrivedesk'); ?></span>
                    </button>
                <?php else: ?>
                    <a href="<?php echo get_page_link( get_post(get_td_helpdesk_options('td_helpdesk_settings')['td_helpdesk_page_id']))?>" id="td-new-ticket-url" target="_blank" class="td-btn-primary">
		                <?php _e('Create a new ticket', 'thrivedesk'); ?>
                    </a>
                <?php endif; ?>

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
                        <th scope="col" class="w-auto text-center">
                            <?php _e('Last update', 'thrivedesk'); ?>
                        </th>
                        <th scope="col" class="w-auto"></th>
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
					        global $wp;
					        $url =  home_url( $wp->request );
					        $conv_page_url = add_query_arg( array(
						        'td_conversation_id' => $conversation['id']
					        ), $url );
					        ?>
                            <a class="text-sm" href="<?php echo $conv_page_url; ?>">
                                <div class="font-bold">
                                    <span>(#<?php echo $conversation['ticket_id']; ?>)</span>
                                    <span class="text-black"><?php echo $conversation['subject'];?></span>
                                </div>
                                <div class="font-normal"><?php echo $conversation['excerpt']; ?>.</div>
                            </a>
                        </td>
                        <td class="text-center align-middle">
					        <?php echo diff_for_humans($conversation['updated_at']) ?>
                        </td>
                        <td class="text-center align-middle  max-w-[100px]">
                            <div class="td-action-btn">
                                <div class="truncate">
                                    <a  href="<?php echo $conv_page_url; ?>"><?php _e('View', 'thrivedesk'); ?></a>                                
                                </div>
                            </div>

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
                <a class="powered-by" href="https://www.thrivedesk.com/introducing-wpportal?utm_source=wpportal&utm_medium=<?php echo get_site_url(); ?>&utm_campaign=powered-by" target="_blank">
                    <span>Powered by</span>
                    <img src="<?php echo THRIVEDESK_PLUGIN_ASSETS . '/images/thrivedesk.png'; ?>" alt="ThriveDesk Logo"
                         style="height: 15px; width: 84px;">
                </a>
                <?php
                if ( $links ): ?>
                    <ul class="td-paginator not-prose divide-x">
                        <?php
                        foreach ( $links as $key => $link ): ?>
                            <?php
                            $params = parse_url( $link['url'] ?? '', PHP_URL_QUERY );
                            if ( $params ) {
                                parse_str( $params, $query );
                            }
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
