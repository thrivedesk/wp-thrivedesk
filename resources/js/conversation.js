import Swal from "sweetalert2";
import { __ } from '@wordpress/i18n';

jQuery(document).ready(($) => {
    $('#openConversationModal').click(function (e) {
        // locked = no ticket page configured, do nothing on click
        if ($(this).attr('data-locked') === 'true') {
            e.preventDefault();
            return;
        }
        if ($(this).data('open-modal') !== true) {
            return;
        }

        e.preventDefault();
        $('.td-modal-container').removeClass('hidden').addClass('is-open').fadeIn(500);
    });

    $('#close-modal').click(function (e) {
        $('.td-modal-container').addClass('hidden').removeClass('is-open').fadeOut(200);
    });

    // reload tickets
    $('#reloadTickets').click(function (e) {
        e.preventDefault();

        const $button = $(this);
        const $labelSpan = $button.find('span');
        const originalHtml = $labelSpan.html();
        // inline spinner, same markup as the one on the reply button
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
                        title: __('Success!', 'thrivedesk'),
                        text: response.data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: __('Error!', 'thrivedesk'),
                        text: response.data.message || __('Failed to reload tickets', 'thrivedesk')
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Reload tickets error:', error);
                Swal.fire({
                    icon: 'error',
                    title: __('Error!', 'thrivedesk'),
                    text: __('Failed to reload tickets. Please try again.', 'thrivedesk')
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
            $('.td-modal-container').addClass('hidden').removeClass('is-open').fadeOut(200);
        }
    });

    function debounce( callback, delay ) {
        let timeout;
        return function() {
            clearTimeout( timeout );
            timeout = setTimeout( callback, delay );
        }
    }

    // hold the active requests so we can abort them if the user
    // types again before the previous search finishes
    let tdActiveKbRequest = null;
    let tdActiveWpRequest = null;
    // bumped on every search, callbacks capture it and bail if
    // they don't match the current value. avoids stale responses
    // overwriting fresh ones when the user types fast.
    let tdActiveToken = 0;

    // modal elements, kept up top so the search logic can reach them
    const tdModalSearchInput = $('#td-search-input');
    const tdModalInitial = $('#td-search-initial');
    const tdModalResults = $('#td-search-results');
    const tdModalEmpty = $('#td-search-empty');
    const tdModalSpinner = $('#td-search-spinner');
    const tdModalSearchClearBtn = $('#td-modal-search-clear');

    // tracks which overlay is currently shown in the body
    let currentModalState = 'initial';

    const updateModalClearBtn = () => {
        if (!tdModalSearchClearBtn.length) return;
        // hide the × while loading or when there's nothing to clear
        const shouldHide = currentModalState === 'loading' || !tdModalSearchInput.val();
        tdModalSearchClearBtn.prop('hidden', shouldHide);
    };

    // states: initial | loading | results | empty
    //   initial  – nothing searched yet, or query was cleared
    //   loading  – request in flight; leave the body alone so we
    //              don't flash between states
    //   results  – at least one section was rendered
    //   empty    – request ran, nothing came back
    const setModalState = (state) => {
        currentModalState = state;
        tdModalInitial.prop('hidden', state !== 'initial');
        tdModalSpinner.prop('hidden', state !== 'loading');
        tdModalEmpty.prop('hidden', state !== 'empty');
        updateModalClearBtn();
    };

    const escAttr = (str) => String(str == null ? '' : str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    const buildSearchItem = (item, index, source) => {
        const link = source === 'kb'
            ? (item.links && item.links.getLink) || '#'
            : (item && item.link) || '#';
        const cats = Array.isArray(item.categories) && item.categories.length > 0
            ? item.categories.map(c => c && c.name).filter(Boolean).join(', ')
            : '';
        return `<li class="td-search-item" id="td-search-item-${source}-${index}">
            <a target="_blank" rel="noopener" href="${escAttr(link)}">
                <span class="td-search-tag">${escAttr(cats)}</span>
                <span class="td-search-title">${escAttr(item.title || '')}</span>
                <span class="td-search-excerpt">${escAttr(item.excerpt || '')}</span>
            </a>
        </li>`;
    };

    const buildSearchSection = (label, count, itemsHtml) => `
        <section class="td-search-section">
            <header class="td-search-section-header">
                <span class="td-search-section-label">${escAttr(label)}</span>
                <span class="td-search-section-count">${count}</span>
            </header>
            <ul class="td-search-list">${itemsHtml}</ul>
        </section>`;

    function search_query() {
        const search_query = $('#td-search-input').val();
        const list = tdModalResults;

        updateModalClearBtn();

        if (!search_query) {
            // empty input, kill any in-flight requests, wipe
            // rendered sections, go back to the hint
            if (tdActiveKbRequest) tdActiveKbRequest.abort();
            if (tdActiveWpRequest) tdActiveWpRequest.abort();
            list.find('.td-search-section').remove();
            setModalState('initial');
            return;
        }

        // cancel anything still pending from a previous keystroke.
        // their callbacks will bail when they see the token has moved on.
        if (tdActiveKbRequest) tdActiveKbRequest.abort();
        if (tdActiveWpRequest) tdActiveWpRequest.abort();

        const myToken = ++tdActiveToken;

        setModalState('loading');

        // count how many endpoints we're waiting on, then render
        // once they all come back. (promise.all + jquery deferred
        // doesn't play nice, jquery's deferred isn't promises/a+)
        let pending = 0;
        if (td_objects.kb_url) pending++;
        if (td_objects.wp_json_url && td_objects.wp_search_enabled) pending++;
        if (pending === 0) {
            list.find('.td-search-section').remove();
            setModalState('empty');
            return;
        }
        const results = { kb: [], wp: [] };
        const tryRender = () => {
            if (myToken !== tdActiveToken) return;
            if (pending > 0) return;
            // we've won the race, hand off tracking so the next
            // search starts fresh
            tdActiveKbRequest = null;
            tdActiveWpRequest = null;

            const kbData = results.kb;
            const wpData = results.wp;

            let combinedResults = '';

            if (td_objects.kb_url && kbData.length > 0) {
                const kbItems = kbData.map(function(item, i) {
                    return buildSearchItem(item, i, 'kb');
                }).join('');
                combinedResults += buildSearchSection(
                    __('Knowledge Base', 'thrivedesk'),
                    kbData.length,
                    kbItems
                );
            }

            if (td_objects.wp_json_url && td_objects.wp_search_enabled && wpData.length > 0) {
                const wpItems = wpData.map(function(item, i) {
                    return buildSearchItem(item, i, 'wp');
                }).join('');
                combinedResults += buildSearchSection(
                    __('WordPress', 'thrivedesk'),
                    wpData.length,
                    wpItems
                );
            }

            if (combinedResults === '') {
                list.find('.td-search-section').remove();
                setModalState('empty');
            } else {
                list.find('.td-search-section').remove();
                list.append(combinedResults);
                setModalState('results');
            }
        };

        if (td_objects.kb_url) {
            tdActiveKbRequest = $.ajax({
                type: "GET",
                url: td_objects.kb_url + "/api/articles",
                data: { q: search_query },
                timeout: 10000,
                success: function(response) {
                    // jquery parses json for us, response is the object
                    results.kb = (response && response.data) || [];
                    pending--;
                    tryRender();
                },
                error: function(xhr, status, error) {
                    // 'abort' = we cancelled it ourselves, not a real error
                    if (status !== 'abort') {
                        console.error('KB Request Error:', error, xhr.status, xhr.responseText);
                    }
                    results.kb = [];
                    pending--;
                    tryRender();
                }
            });
        }

        if (td_objects.wp_json_url && td_objects.wp_search_enabled) {
            tdActiveWpRequest = $.ajax({
                type: "POST",
                // wp_json_url already ends with /?rest_route=, so the
                // route just gets appended. the ?rest_route= form works
                // whether or not pretty permalinks are on, which the
                // /wp-json/ path form doesn't.
                url: td_objects.wp_json_url + "/thrivedesk/v1/docs",
                // the route is logged-in only now. without this header
                // wordpress's rest_cookie_check_errors() calls
                // wp_set_current_user(0) on a cookie-authed rest request,
                // and the permission callback sees an anonymous visitor
                // even though the customer is signed in.
                beforeSend: function (xhr) {
                    if (td_objects.rest_nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', td_objects.rest_nonce);
                    }
                },
                data: {
                    query_string: search_query,
                    action: 'td_search_query_docs',
                },
                timeout: 10000,
                success: function(response) {
                    results.wp = (response && response.data) || [];
                    pending--;
                    tryRender();
                },
                error: function(xhr, status, error) {
                    if (status !== 'abort') {
                        console.error('WP Request Error:', error, xhr.status, xhr.responseText);
                    }
                    results.wp = [];
                    pending--;
                    tryRender();
                }
            });
        }
    }

    // build the debounced wrapper once. if we built it per-event
    // we'd get a fresh closure each time and no debouncing would happen
    const debouncedSearchQuery = debounce(search_query, 1500);

    if (tdModalSearchClearBtn.length) {
        tdModalSearchClearBtn.on('click', function () {
            tdModalSearchInput.val('').trigger('focus');
            // wipe sections so we're back to just the hint
            tdModalResults.find('.td-search-section').remove();
            updateModalClearBtn();
            setModalState('initial');
        });
    }

    $('#td-search-input').on('keyup', function (e) {
        updateModalClearBtn();
        debouncedSearchQuery();
    });
    $('#td-search-input').on('input', updateModalClearBtn);

    const tdTicketSearchId = $('#td-ticket-search');
    const tdSearchClearBtn = $('#td-search-clear');

    // the × next to the ticket search input
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

    // status filter + search. applyFilters walks the rows each time
    // and toggles visibility based on tab + search text
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

        // don't show the no-results row if the server returned zero
        // tickets, the table has its own empty state for that
        const onlyNoResultsRow = rows.length === 0;
        getNoResultsRow().toggle(!onlyNoResultsRow && visibleCount === 0);
    };

    tdTicketSearchId.on('search', function (e) {
        applyFilters();
    });

    // tab links, they're real anchors with cv_status= so they
    // still work without JS, we just hijack and filter client-side
    const filterTabs = $('.td-tabs a[data-filter]');
    filterTabs.on('click', function (e) {
        e.preventDefault();
        const tab = $(this);
        activeFilter = (tab.attr('data-filter') || 'all').toLowerCase();
        filterTabs.removeClass('is-active').attr('aria-selected', 'false');
        tab.addClass('is-active').attr('aria-selected', 'true');
        applyFilters();
    });

    // honor ?cv_status= in the url on first load
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

    // click anywhere on a row to open it. the subject cell has its
    // own <a> for middle-click etc, so let those events through
    $('#conversation-table').on('click', 'tr[data-href]', function (e) {
        if (e.target.closest('a, button, input, select, textarea')) {
            return;
        }
        const href = $(this).attr('data-href');
        if (href) {
            window.location.href = href;
        }
    });

    // enter/space on a focused row opens it too
    $('#conversation-table').on('keydown', 'tr[data-href]', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        if (e.target.closest('a, button, input, select, textarea')) return;
        e.preventDefault();
        const href = $(this).attr('data-href');
        if (href) window.location.href = href;
    });

    $('#td_conversation_reply').submit(function(e){
        e.preventDefault();

        let td_reply_nonce = $("#td_reply_nonce").val();
        let td_conversation_id = $("#td_conversation_id").val();
        let reply_text = $("#td_conversation_editor").val();
        if (reply_text === '') {
            Swal.fire({
                icon: 'error',
                title: __('Oops...', 'thrivedesk'),
                text: __('Reply text can not be empty!', 'thrivedesk'),
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
                            title: __('Reply sent', 'thrivedesk'),
                            text: response.message,
                        }).then(() => {
                            location.reload();
                        })
                    } else {
                        $('#td-reply-spinner').hide();
                        Swal.fire({
                            icon: 'error',
                            title: __('Oops...', 'thrivedesk'),
                            text: response.message,
                        })
                    }
                }
            );
        }
    });
});