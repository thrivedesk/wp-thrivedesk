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

    // get search input id
    const tdTicketSearchId = $('#td-ticket-search');

    // handle clear button
    tdTicketSearchId.on('keyup', function (e) {
        // trigger search immediately
        $(this).trigger('search');
    });

    // Conversation search
    tdTicketSearchId.on('search', function (e) {
        const tableContainer = $('#conversation-table');
        const search = $(this).val();
        tableContainer.find('tr').each(function (index, element) {
            if (search === '') {
                $(element).show();
                return;
            }
            const row = $(element);
            const ticketId = row?.text()?.toLowerCase();
            if (ticketId && ticketId.toString().indexOf(search) === -1) {
                row.hide();
            } else {
                row.show();
            }
        });

        // Show no results message if no results found
        if (tableContainer.find('tr:visible').length === 0) {
            tableContainer.find('#no-results').show();
        } else {
            tableContainer.find('#no-results').hide();
        }
    });

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
                            <a target="_blank" href="${item.link}">
                                <div class="td-search-content">
                                    <span class="td-search-tag">${item.categories}</span>
                                    <span class="td-search-title">${item.title}</span>
                                    <span class="td-search-excerpt">${item.excerpt}</span>
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
