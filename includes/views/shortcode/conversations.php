<?php
use ThriveDesk\Conversations\Conversation;
use ThriveDesk\Services\PortalService;

$conversations       = Conversation::get_conversations();
$conversation_data   = isset($conversations['data']) ? Conversation::td_conversation_sort_by_status($conversations['data']) : [];
$links               = $conversations['meta']['links'] ?? [];
$is_portal_available = (new PortalService())->has_portal_access();

?>

<div id="thrivedesk" class="w-full">
    <div class="td-container">
        <!-- if portal feature not available  -->
        <?php if (!$is_portal_available): ?>
        <div class="p-10 text-center my-10 bg-rose-50 border-2 border-dashed border-rose-200 text-rose-500 rounded font-medium space-y-4">
            <span><?php _e('Your subscription plan does not support WPPortal feature. Please contact ThriveDesk for more information.', 'thrivedesk'); ?></span>
            <img src="https://media.thrivedesk.com/wp-content/uploads/2023/05/portal-mini.avif">
        </div>

        <?php else: ?>
            <div class="td-portal-header">
                <input type="search" class="px-3 py-2 w-64 bg-white border rounded-md shadow-sm" id="td-ticket-search" placeholder="<?php _e('Search...')?>">
                <button type="submit" id="openConversationModal" class="td-btn-primary ml-auto" data-modal-toggle="tdConversationModal">
                    <span><?php _e('Create a new ticket', 'thrivedesk'); ?></span>
                </button>
            </div>
            <div class="relative overflow-x-auto shadow-md sm:rounded-lg bg-white">
                <table class="td-portal-tickets" id="conversation-table">
                    <thead>
                        <tr>
                            <th scope="col">
                                <?php _e('Ticket', 'thrivedesk'); ?>
                            </th>
                            <th scope="col" class="w-28 text-center">
                                <?php _e('Status', 'thrivedesk'); ?>
                            </th>
                            <th scope="col" class="w-32 text-center">
                                <?php _e('Last update', 'thrivedesk'); ?>
                            </th>
                            <th scope="col"></th>
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
                            <td>
                                <?php
                                global $wp;
                                $url =  home_url( $wp->request );
                                $conv_page_url = add_query_arg( array(
                                    'td_conversation_id' => $conversation['id']
                                ), $url );
                                ?>
                                <a href="<?php echo $conv_page_url; ?>">
                                    <div class="font-semibold text-base text-slate-800">
                                        <span>(#<?php echo $conversation['ticket_id']; ?>)</span>
                                        <span><?php echo $conversation['subject'];?></span>
                                    </div>
                                    <span class="text-sm text-slate-500"><?php echo $conversation['excerpt']; ?>.</span>
                                </a>
                            </td>
                            <td scope="row" class="text-center align-middle">
                                <span class="status status-<?php echo strtolower($conversation['status']); ?>">
                                    <?php echo $conversation['status']; ?>
                                </span>
                            </td>
                            <td class="text-center align-middle text-sm">
                                <?php echo diff_for_humans($conversation['updated_at']) ?>
                            </td>
                            <td class="text-center align-middle w-32">
                                <a class="td-btn"  href="<?php echo $conv_page_url; ?>"><?php _e('View Ticket', 'thrivedesk'); ?></a>
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
            </div>
            <div class="td-portal-footer">
                <a class="flex items-center space-x-2 text-xs cursor-pointer text-slate-600 uppercase opacity-75 hover:opacity-100" href="https://www.thrivedesk.com/wordpress?utm_source=wpportal&utm_medium=<?php echo get_site_url(); ?>&utm_campaign=powered-by" target="_blank">
                    <span>Powered by</span>
                    <img src="<?php echo THRIVEDESK_PLUGIN_ASSETS . '/images/thrivedesk.png'; ?>" alt="ThriveDesk Logo"
                         style="height: 15px; width: 84px; margin:0;">
                </a>

                <?php
                if ( $links ): ?>
                    <nav class="ml-auto" aria-label="Page navigation">
                        <ul class="td-paginator">
                            <?php
                            foreach ( $links as $key => $link ): ?>
                                <?php
                                $params = parse_url( $link['url'] ?? '', PHP_URL_QUERY );
                                if ( $params ) {
                                    parse_str( $params, $query );
                                }
                                $page = $query['page'] ?? 1;
                                ?>
                                <li class="<?php echo $link['active'] ? 'pg-active' : ''; ?>">
                                    <?php if($link['url']): ?>
                                        <a href="<?php echo get_permalink() . '?cv_page=' . $page ?>">
                                    <?php endif; ?>
                                            <span><?php echo $link['label'] ?></span>
                                    <?php if($link['url']): ?>
                                        </a>
                                    <?php endif; ?>
                                </li>
                            <?php
                            endforeach; ?>
                        </ul>
                    </nav>
                <?php
                endif; ?>
            </div>
        <?php
        endif; ?>
    </div>

    <!-- Include modal  -->
    <?php thrivedesk_view('shortcode/modal');?>
</div>