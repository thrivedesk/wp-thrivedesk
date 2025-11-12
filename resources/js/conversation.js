import Swal from "sweetalert2";

jQuery(document).ready(($) => {
    $('#openConversationModal').click(function (e) {
        $('.td-modal-container').removeClass('hidden').fadeIn(500);
    });

    $('#close-modal').click(function (e) {
        $('.td-modal-container').addClass('hidden').fadeOut(200);
    });

    // Reload tickets functionality
    $('#reloadTickets').click(function (e) {
        e.preventDefault();
        
        const $button = $(this);
        const originalText = $button.find('span').text();
        
        // Disable button and show loading state
        $button.prop('disabled', true);
        $button.attr('aria-busy', 'true');
        $button.find('span').text(td_objects.i18n_reloading);
        
        // Make AJAX request to reload tickets
        $.ajax({
            type: 'POST',
            url: td_objects.ajax_url,
            dataType: 'json',
            data: {
                action: 'td_reload_tickets',
                nonce: td_objects.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: td_objects.i18n_success,
                        text: response.data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        // Reload the page to show fresh data after toast finishes
                        location.reload();
                    });
                } else {
                    // Show error message
                    Swal.fire({
                        icon: 'error',
                        title: td_objects.i18n_error,
                        text: response.data.message || td_objects.i18n_failed_reload
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Reload tickets error:', error);
                Swal.fire({
                    icon: 'error',
                    title: td_objects.i18n_error,
                    text: td_objects.i18n_failed_reload_try_again
                });
            },
            complete: function() {
                // Re-enable button and restore original text
                $button.prop('disabled', false);
                $button.removeAttr('aria-busy');
                $button.find('span').text(originalText);
            }
        });
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

    function search_query() {
        let search_query = $('#td-search-input').val();
        let tdSearchSpinner = $('#td-search-spinner');
        let list = $('#td-search-results');
        let kbRequest;
        let wpRequest;

        if (!search_query) return;
        tdSearchSpinner.show();
        
        if(td_objects.kb_url){
            kbRequest = $.ajax({
                type: "GET",
                url: td_objects.kb_url + "/api/articles",
                data: {
                    q: search_query
                },
                timeout: 10000, 
                error: function(xhr, status, error) {
                    console.error('KB Request Error:', error);
                    tdSearchSpinner.hide();
                }
            });
        }
    
        if (td_objects.wp_json_url) {
            wpRequest = $.ajax({
                type: "POST",
                url: td_objects.wp_json_url + "/td-search-query/docs",
                data: {
                    query_string: search_query,
                    action: 'td_search_query_docs',
                },
                timeout: 10000, 
                error: function(xhr, status, error) {
                    console.error('WP Request Error:', error);
                    tdSearchSpinner.hide();
                }
            });
        }
        
        Promise.all([kbRequest, wpRequest])
            .then(function(results) {
                var kbData = results[0] ? results[0].data : [];
                var wpData = results[1] ? results[1].data : [];
                
                // Process KB data
                var kbResultsHtml = '';
                if (kbData.length > 0) {
                    kbData.forEach(function(item, i) {
                        kbResultsHtml += `<li class="td-search-item" id="td-search-item-${i}">
                            <a target="_blank" href="${item.links.getLink}">
                                <div class="td-search-content">
                                    <span class="td-search-tag">${item.categories.map(cat => cat.name).join(', ')}</span>
                                    <span class="td-search-title">${item.title}</span>
                                    <span class="td-search-excerpt">${item.excerpt}</span>
                                </div>
                            </a>
                        </li>`;
                    });
                } else {
                    let new_ticket_url = $('#td-new-ticket-url').attr('href');
                    kbResultsHtml += `<li class="h-36 flex items-center justify-center text-slate-500">
                        <div>No article found on our knowledge base. <a href="${new_ticket_url}" target="_blank" class="text-blue-600">Click here </a>to open a new ticket</div>
                    </li>`;
                }
        
                // Process WP data
                var wpResultsHtml = '';
                var noData = true;
                if (typeof wpData == 'object' && wpData.length > 0) {
                    var noData = false;
                    wpData.forEach(function(item, i) {
                        wpResultsHtml += `<li class="td-search-item" id="td-search-item-${i}">
                            <a target="_blank" href="${item.link}">
                                <div class="td-search-content">
                                    <span class="td-search-tag">${item.categories.map(cat => cat.name).join(', ')}</span>
                                    <span class="td-search-title">${item.title}</span>
                                    <span class="td-search-excerpt">${item.excerpt}</span>
                                </div>
                            </a>
                        </li>`;
                    });
                } else {
                    let new_ticket_url = $('#td-new-ticket-url').attr('href');
                    wpResultsHtml += `<li class="h-36 flex items-center justify-center text-slate-500">
                        <div>No article found. <a href="${new_ticket_url}" target="_blank" class="text-blue-600">Click here </a>to open a new ticket</div>
                    </li>`;
                }
        
                var combinedResults = '';
                
                if (td_objects.kb_url) {
                    combinedResults +=`
                    <div>
                        <p class="px-4 font-bold">Search results from Knowledge Base</p>
                    </div>
                    <ul>${kbResultsHtml}</ul>`;
                };

                if (td_objects.wp_json_url && noData === false) {
                    combinedResults +=`<div>
                    <p class="px-4 font-bold">Search results from WordPress</p>
                    </div>
                    <ul>${wpResultsHtml}</ul>`;
                }
        
                list.html(combinedResults);
                tdSearchSpinner.hide();
            })
            .catch(function(error) {
                console.error('Promise.all Error:', error);
                tdSearchSpinner.hide();
            });
    }

    $('#td-search-input').keyup(debounce(search_query, 1000));

    $('#td_conversation_reply').submit(function(e){
        e.preventDefault();

        let td_reply_nonce = $("#td_reply_nonce").val();
        let td_conversation_id = $("#td_conversation_id").val();
        let reply_text = $("#td_conversation_editor").val();
        if (reply_text === '') {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Reply text can not be empty!',
            })
        } else {
            // make spinner visible
            $('#td-reply-spinner').show();
            jQuery.post(
                td_objects.ajax_url,
                {
                    action: 'td_reply_conversation',
                    data: {
                        nonce: td_reply_nonce,
                        conversation_id: td_conversation_id,
                        reply_text: reply_text,
                    },
                },
                (response) => {
                    if (response.status === 'success') {
                        // make spinner invisible
                        $('#td-reply-spinner').hide();
                        Swal.fire({
                            icon: 'success',
                            title: 'Reply sent',
                            text: response.message,
                        }).then(() => {
                            location.reload();
                        })
                    } else {
                        // make spinner invisible
                        $('#td-reply-spinner').hide();
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
