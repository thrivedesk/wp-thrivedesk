import Swal from "sweetalert2";

jQuery(document).ready(($) => {
    $('#openConversationModal').click(function (e) {
        // no-op when the button is locked (no ticket page configured)
        if ($(this).attr('data-locked') === 'true') {
            e.preventDefault();
            return;
        }
        if ($(this).data('open-modal') !== true) {
            return;
        }

        e.preventDefault();
        $('.td-modal-container').removeClass('hidden').fadeIn(500);
    });

    $('#close-modal').click(function (e) {
        $('.td-modal-container').addClass('hidden').fadeOut(200);
    });

    // reload tickets
    $('#reloadTickets').click(function (e) {
        e.preventDefault();

        const $button = $(this);
        const $labelSpan = $button.find('span');
        const originalHtml = $labelSpan.html();
        // same .td-spinner used on the reply button (see conversation-details.php)
        const spinnerHtml = '<svg class="td-spinner" style="width:30px;height:18px" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
            + '<path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>'
            + '<path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>'
            + '</svg>';

        $button.prop('disabled', true);
        $button.attr('aria-busy', 'true');
        $labelSpan.html(spinnerHtml);

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
                    Swal.fire({
                        icon: 'success',
                        title: td_objects.i18n_success,
                        text: response.data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
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
                $button.prop('disabled', false);
                $button.removeAttr('aria-busy');
                $labelSpan.html(originalHtml);
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

    const tdTicketSearchId = $('#td-ticket-search');
    const tdSearchClearBtn = $('#td-search-clear');

    // clear (×) button shows when the input has text
    const updateClearBtn = () => {
        if (!tdSearchClearBtn.length) return;
        tdSearchClearBtn.prop('hidden', !tdTicketSearchId.val());
    };
    tdSearchClearBtn.on('click', function () {
        tdTicketSearchId.val('').trigger('search').trigger('focus');
        updateClearBtn();
    });

    tdTicketSearchId.on('keyup', function (e) {
        updateClearBtn();
        $(this).trigger('search');
    });
    updateClearBtn();

    // search + filter pipeline. both run through applyFilters() — a row
    // passes only if it matches the active status tab AND the search text.
    let activeFilter = 'all';

    const getTableRows = () => $('#conversation-table').find('tbody tr').not('#no-results');
    const getNoResultsRow = () => $('#conversation-table').find('#no-results');

    const matchesFilter = (row, filter) => {
        if (filter === 'all') return true;
        const status = (row.attr('data-status') || '').toLowerCase();
        return status === filter;
    };

    const matchesSearch = (row, search) => {
        if (!search) return true;
        return (row.text() || '').toLowerCase().indexOf(search) !== -1;
    };

    const applyFilters = () => {
        const search = (tdTicketSearchId.val() || '').toLowerCase();
        const rows = getTableRows();
        let visibleCount = 0;
        rows.each(function () {
            const row = $(this);
            const visible = matchesFilter(row, activeFilter) && matchesSearch(row, search);
            row.toggle(visible);
            if (visible) visibleCount++;
        });

        // don't show the no-results row when the server already
        // returned an empty list (it has its own empty state)
        const onlyNoResultsRow = rows.length === 0;
        getNoResultsRow().toggle(!onlyNoResultsRow && visibleCount === 0);
    };

    tdTicketSearchId.on('search', function (e) {
        applyFilters();
    });

    // status filter tabs — anchors so they degrade to plain links
    // when JS is off (the href carries the cv_status query arg).
    // with JS on we preventDefault and filter client-side.
    const filterTabs = $('.td-tabs a[data-filter]');
    filterTabs.on('click', function (e) {
        e.preventDefault();
        const tab = $(this);
        activeFilter = (tab.attr('data-filter') || 'all').toLowerCase();
        filterTabs.removeClass('is-active').attr('aria-selected', 'false');
        tab.addClass('is-active').attr('aria-selected', 'true');
        applyFilters();
    });

    // pick up ?cv_status= deep-links so they land on the right tab
    const urlParams = new URLSearchParams(window.location.search);
    const urlStatus = (urlParams.get('cv_status') || '').toLowerCase();
    const allowedFilters = ['all', 'active', 'pending', 'closed'];
    if (urlStatus && allowedFilters.indexOf(urlStatus) !== -1) {
        activeFilter = urlStatus;
        const matched = filterTabs.filter('[data-filter="' + activeFilter + '"]');
        if (matched.length) {
            filterTabs.removeClass('is-active').attr('aria-selected', 'false');
            matched.addClass('is-active').attr('aria-selected', 'true');
        }
    }

    applyFilters();

    // row click → open. the subject cell has its own <a> for keyboard
    // nav + middle-click "open in new tab" so we let those events through
    $('#conversation-table').on('click', 'tr[data-href]', function (e) {
        if (e.target.closest('a, button, input, select, textarea')) {
            return;
        }
        const href = $(this).attr('data-href');
        if (href) {
            window.location.href = href;
        }
    });

    // Enter / Space on a focused row opens it
    $('#conversation-table').on('keydown', 'tr[data-href]', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        if (e.target.closest('a, button, input, select, textarea')) return;
        e.preventDefault();
        const href = $(this).attr('data-href');
        if (href) window.location.href = href;
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

                var kbResultsHtml = '';
                if (kbData.length > 0) {
                    kbData.forEach(function(item, i) {
                        kbResultsHtml += `<li class="td-search-item" id="td-search-item-${i}">
                            <a target="_blank" href="${item.links?.getLink || '#'}">
                                <div class="td-search-content">
                                    <span class="td-search-tag">${Array.isArray(item.categories) && item.categories.length > 0 ? item.categories.map(cat => cat.name).join(', ') : ''}</span>
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
        
                var wpResultsHtml = '';
                var hasWpResults = false;
                if (typeof wpData == 'object' && wpData.length > 0) {
                    hasWpResults = true;
                    wpData.forEach(function(item, i) {
                        wpResultsHtml += `<li class="td-search-item" id="td-search-item-${i}">
                            <a target="_blank" href="${item?.link || '#'}">
                                <div class="td-search-content">
                                    <span class="td-search-tag">${Array.isArray(item.categories) && item.categories.length > 0 ? item.categories.map(cat => cat.name).join(', ') : ''}</span>
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

                if (td_objects.wp_json_url && hasWpResults) {
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
                        $('#td-reply-spinner').hide();
                        Swal.fire({
                            icon: 'success',
                            title: 'Reply sent',
                            text: response.message,
                        }).then(() => {
                            location.reload();
                        })
                    } else {
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
