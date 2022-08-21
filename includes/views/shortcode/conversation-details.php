<div class="w-full bg-gray-200 py-6 px-8" style="margin: 20px 0 20px 0;">
    <div class="py-4 px-2 flex flex-col justify-start">
        <span class="font-semibold">#8746837 - API setup</span>
        <p class="text-sm"><span>Last update: 7 days ago</span></p>
    </div>
    <div class="py-4 px-2 rounded-lg shadow-md sm:rounded-lg bg-white border">
        <div class="px-6 py-4">
            <h1 class="pb-3 text-left text-lg font-extrabold border-b">Conversation</h1>
            <div class="w-full text-sm text-lef border-b py-5 mb-4">
                <div class="bg-blue-100 rounded border-2">
                    <div class="px-6 py-4 pt-3">
                        <div class="flex border-b">
                            <div class="flex-none w-14 h-14">
                                <img class="w-10 h-10 rounded-full" src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Rounded avatar" />
                            </div>
                            <div class="flex-initial w-64">
                                <h3 class="font-bold">Parvez Akhter</h3>
                                <p class="text-sm">15 days ago - 23 July 2022 (02:19:03 PM)</p>
                            </div>
                        </div>

                        <div class="py-4">
                            <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Quas sint ratione ipsam non repudiandae, quidem quia ea, sapiente tempora in a eligendi voluptatem ducimus quod praesentium eum laborum necessitatibus expedita.</p>
                        </div>

                    </div>
                </div>

                <div class="bg-gray-100 rounded mt-5">
                    <div class="px-6 py-4 pt-3">
                        <div class="flex border-b">
                            <div class="flex-none w-14 h-14">
                                <img class="w-10 h-10 rounded-full" src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Rounded avatar" />
                            </div>
                            <div class="flex-initial w-64">
                                <h3 class="font-bold">Atiqur Rahman</h3>
                                <p class="text-sm">15 days ago - 23 July 2022 (02:19:03 PM)</p>
                            </div>
                        </div>

                        <div class="py-4">
                            <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Quas sint ratione ipsam non repudiandae, quidem quia ea, sapiente tempora in a eligendi voluptatem ducimus quod praesentium eum laborum necessitatibus expedita.</p>
                        </div>

                    </div>
                </div>
            </div>
            <div class="py-6">
                <form action="" id="td_conversation_reply" method="POST">
                    <div class="mb-6">
			            <?php wp_editor('', 'td_conversation_editor', ['editor_height' => '120'] ); ?>
                    </div>

                    <div class="mb-6">
                        <button type="submit" id="td_conversation_reply_submit"
                                class="text-white bg-blue-700
                                    hover:bg-blue-800
                                    focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-full sm:w-auto px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            Reply
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

