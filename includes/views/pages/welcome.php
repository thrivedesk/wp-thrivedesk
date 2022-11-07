<div class="hidden tab-welcome flex space-x-4">
    <div class="flex-1">
        <div class="bg-white shadow-lg rounded-md p-16 space-y-3 flex flex-col">
            <h1 class="font-medium text-2xl">Welcome To ThriveDesk</h1>
            <p class="mr-28 text-base">Customer support on WordPress has never been easier, faster, or more flexible.</p>
            <a class="btn-primary mr-28 text-center" href="https://app.thrivedesk.com/register/?email=<?php echo wp_get_current_user()->user_email?>&workspace=<?php echo get_bloginfo('name');?>" target="_blank">Set Up My Account</a>
            <small>If you already have account on ThriveDesk, obtain API key and put it here.</small>
        </div>
        <div class="flex space-x-4 mt-4">
            <div class="w-1/2 p-6 rounded-md text-white text-base bg-gradient-to-br from-zinc-600 to-zinc-900">
                <div class="rounded-xl p-4 bg-gradient-to-b from-white/20 to-white/5 w-16 h-16 flex items-center justify-center mb-4">
                    <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="m9.25 1.75-6.5 6.5h4v6l6.5-6.5h-4v-6Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                </div>
                <div class="text-xl font-bold">Sync WordPress Posts</div>
                <div class="mt-2 tracking-wide">Everything you’ve asked for, in one place. Search and browse Extensions for your tools.</div>
                <a href="#" target="_blnk" class="rounded-md bg-white/20 hover:bg-white/30 py-2 px-4 no-underline text-white my-4 inline-block">Learn more</a>
            </div>
            <div class="w-1/2 p-6 rounded-md text-white text-base space-y-2" style="background:linear-gradient(107.56deg,#dcf9ff,#621dba 48.44%,#04001c 95.31%)">
                <a href="#" target="_blank" class="block">
                    <div class="text-xl font-bold">Portal</div>
                    <div class="tracking-wide">Create help center inside WordPress without writing any code.</div>
                    <!-- <img class="" src="https://www.raycast.com/_next/image?url=%2F_next%2Fstatic%2Fmedia%2Fextension-code-example.b2ccf8d7.png&w=828&q=75" alt="Live chat"> -->
                </a>
            </div>
        </div>
    </div>
    <div class="w-1/3 space-y-4">
        <a href="#" target="_blank" class="block relative">
            <div class="p-6 rounded-md text-white text-base bg-gradient-to-br from-purple-400 via-purple-600 to-violet-600">
                <div class="text-xl font-bold">Live Chat</div>
                <div class="tracking-wide mt-2">Talk with your website visitor and help them realtime with ThriveDesk Assistant</div>
                <img class="max-w-xs 2xl:max-w-md mt-3 -mb-10" src="https://www.thrivedesk.com/wp-content/uploads/2021/08/assistant-customizable.png" alt="Live chat">
            </div>
        </a>
    </div>
</div>
