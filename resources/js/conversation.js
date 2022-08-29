import Swal from "sweetalert2";

jQuery(document).ready(($) => {
    $('#openConversationModal').click(function (e) {
        $('.td-modal-container').removeClass('hidden').fadeIn(500);
    });

    $('#close-modal').click(function (e) {
        $('.td-modal-container').addClass('hidden').fadeOut(200);
    });

    $(document).keydown(function(event){
        if (event.key === 'Escape') {
            $('.td-modal-container').addClass('hidden').fadeOut(200);
        }
    });

    function debounce( callback, delay ) {
        let timeout;
        return function() {
            clearTimeout( timeout );
            timeout = setTimeout( callback, delay );
        }
    }

    function search_query(){
        let search_query = $('#td-search-input').val();
        if (!search_query) return;
        $.ajax({
            type: "POST",
            url: td_objects.wp_json_url + "/td-search-query/docs",
            data: {
                query_string: search_query,
            },
            success: function(data){
                const list = $('#td-search-results');
                let search_results = '';
                if (data.data.length > 0) {
                    data.data.forEach(function(item, i){
                        search_results += `<li class="td-search-item" id="td-search-item-${i}">
                            <a class="p-4 mx-6 no-underline relative bg-slate-100 hover:bg-blue-500 rounded-lg block group" target="_blank" href="${item.link}">
                                <div class="flex flex-auto flex-col min-w-0">
                                    <span class="font-medium text-black group-hover:text-white">${item.title}</span>
                                    <span class="truncate text-slate-500 group-hover:text-white">${item.excerpt}</span>
                                </div>
                            </a>
                        </li>`;
                    });
                } else {
                    let new_ticket_url = $('#td-new-ticket-url').attr('href');
                    search_results += `<li class="h-36 flex items-center justify-center text-slate-500">
                            <div>No documentation found. <a href="${new_ticket_url}" target="_blank" class="text-blue-600">Click here </a>to open a new ticket</div>
                        </li>`
                }
                list.html(search_results);
            }
        });
    }

    $('#td-search-input').keyup(debounce(search_query, 1000));

    $('#td_conversation_reply').submit(function(e){
        e.preventDefault();

        let td_reply_none = $("#td_reply_none").val();
        let td_conversation_id = $("#td_conversation_id").val();
        let reply_text = $("#td_conversation_editor").val();
        if (reply_text === '') {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Reply text can not be empty!',
            })
        } else {
            jQuery.post(
                td_objects.ajax_url,
                {
                    action: 'td_reply_conversation',
                    data: {
                        nonce: td_reply_none,
                        conversation_id: td_conversation_id,
                        reply_text: reply_text,
                    },
                },
                (response) => {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Reply sent',
                            text: response.message,
                        }).then(() => {
                            location.reload();
                        })
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: response.message,
                        })
                    }
                }
            );
        }
    });
});
