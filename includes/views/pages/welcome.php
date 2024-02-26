<div class="tab-welcome flex flex-col space-y-4">
    <div class="flex space-x-4 items-center">
        <div class="mr-28 w-1/2 thrivedesk">
        <?php if (empty(get_td_helpdesk_options()['td_helpdesk_api_key'])): ?>
            <div class="space-y-3 flex flex-col tab-link">
                <div class="td-steps">
                    <span class="active"></span><span></span><span></span>
                </div>
                <h1 class="font-bold text-3xl">Welcome To ThriveDesk</h1>
                <p class="text-base">Customer support on WordPress has never been easier, faster, or more flexible.</p>

                <a href="<?php echo THRIVEDESK_APP_URL . '/handshake?store=' . get_bloginfo('url') . '/wp-admin/admin.php?page=thrivedesk' ?>" class="btn-primary text-center">Connect To ThriveDesk</a>
                
                <div class="flex justify-evenly space-x-2 w-full mt-4">
                    <span class="bg-gray-300 h-px flex-grow t-2 relative top-2"></span>
                    <span class="flex-none uppercase text-xs text-gray-400 font-semibold">or</span>
                    <span class="bg-gray-300 h-px flex-grow t-2 relative top-2"></span>
                </div>
                <a class="py-2.5 text-center border-2 border-gray-200 rounded text-black font-medium bg-white hover:bg-gray-100" data-target="tab-settings" href="#settings">Enter API Key</a>
            </div>
        <?php else: ?>
            <div class="space-y-3 flex flex-col tab-link">
                <canvas id="confetti-canvas" style="position: absolute;"></canvas>
                <div class="td-steps">
                    <span></span><span class="active"></span><span></span>
                </div>
                <h1 class="font-bold text-3xl">You are all set!</h1>
                <p class="text-xl mb-4">Woohoo! You did it! Your connection with ThriveDesk is officially up and running!</p>

                <a class="btn-primary text-center" data-target="tab-welcome" href="<?php echo THRIVEDESK_APP_URL ?>" target="_blank">Open Thrivedesk</a>
                <p class="text-base">🎡 Connect your plugins with your ThriveDesk</p>
                <a class="py-2.5 text-center border-2 border-blue-200 rounded text-blue-500 font-medium hover:bg-blue-100" data-target="tab-integrations" href="#integrations">Integrate now</a>
                
            </div>
        <?php endif; ?>
        </div>
        <div>
            <img src="<?php echo THRIVEDESK_PLUGIN_ASSETS . '/images/welcome.png'; ?>" alt="Welcome">
        </div>
    </div>
    
    <hr>

    <div>
        <h3 class="text-base mb-3 uppercase">Latest news</h3>
        <?php
            // RSS feed
            $feed_url = 'https://www.thrivedesk.com/feed/';
            // Get the feed items using SimplePie
            include_once(ABSPATH . WPINC . '/feed.php');
            $rss = fetch_feed($feed_url);
            $maxitems = 0;
            // Checks that the object is created correctly
            if ( ! is_wp_error( $rss ) ){ 
                // Limit it to 3. 
                $maxitems = $rss->get_item_quantity( 3 ); 
                // Build an array of all the items, starting with element 0 (first element).
                $items = $rss->get_items( 0, $maxitems );
            
            };
        ?>
        <div class="flex space-x-3">
            <?php foreach($items as $item):?>
                <div class="w-1/3 border py-2 px-3 rounded bg-white">
                    <a href="<?php echo esc_url( $item->get_permalink() ); ?>" target="_blank">
                        <?php echo esc_html( $item->get_title() ); ?>
                    </a>
                </div>
            <?php endforeach;?>
        </div>
    </div>
</div>
<script src="node_modules/confetti-js/dist/index.min.js"></script>

