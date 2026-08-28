import Swal from "sweetalert2";
import { __, sprintf } from '@wordpress/i18n';

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
    const tdTicketCta = $('#td-new-ticket-url');
    const tdFooterNote = $('#td-modal-footer-note');
    const tdEmptyCtaSlot = $('#td-search-empty-cta');
    const tdModalFooter = $('.td-modal-footer');
    // Set in modal.php from td_helpdesk_search_required. Read as an attribute
    // rather than through .data(), which coerces types and caches the first
    // value it saw.
    const tdSearchRequired =
        '1' === ($('#tdConversationModal').attr('data-td-search-required') || '0');

    // tracks which overlay is currently shown in the body
    let currentModalState = 'initial';

    /**
     * Put the "new ticket" button where the current state wants it.
     *
     * Moved, never duplicated: a second copy would be a second element with
     * the same id, and the one in the footer is what every other handler
     * refers to. Moving a node does not detach its listeners.
     *
     *   empty   - after the "no matches" message, because that is where the
     *             reader already is and what they now need.
     *   results - back in the footer, with a line beside it offering the
     *             ticket anyway. Before a search there is nothing to be
     *             unhappy with, so the line only appears here.
     *
     * When searching is compulsory the button is hidden until a search has
     * actually run. Hidden, not disabled: a disabled control invites
     * clicking at it, and there is nothing wrong with the button - there is
     * simply nothing to have read yet.
     */
    const placeTicketCta = (state) => {
        if (!tdTicketCta.length) return;

        const searched = state === 'results' || state === 'empty';

        if (state === 'empty' && tdEmptyCtaSlot.length) {
            tdEmptyCtaSlot.append(tdTicketCta);
        } else if (tdModalFooter.length) {
            tdModalFooter.append(tdTicketCta);
        }

        tdTicketCta.prop('hidden', tdSearchRequired && !searched);
        tdFooterNote.prop('hidden', state !== 'results');
    };

    /*
     * Apply it once at load.
     *
     * Everything else goes through setModalState(), which nothing calls until a
     * search runs - so the button sat there in its server-rendered place until
     * the first keystroke, which is exactly the moment the rule was supposed to
     * be enforced before.
     */
    placeTicketCta(currentModalState);

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
        placeTicketCta(state);
        updateModalClearBtn();
    };

    const escAttr = (str) => String(str == null ? '' : str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    // result links come from the kb api and the wp rest route, so they're
    // only ever navigable http(s) urls (or a site-root path). anything else -
    // javascript:, data: - gets dropped rather than rendered as an href.
    const safeHref = (url) => {
        const raw = String(url == null ? '' : url).trim();
        return (/^https?:\/\//i.test(raw) || /^\/(?!\/)/.test(raw)) ? raw : '#';
    };

    const buildSearchItem = (item, index, source) => {
        const link = source === 'kb'
            ? (item.links && item.links.getLink) || '#'
            : (item && item.link) || '#';
        const cats = Array.isArray(item.categories) && item.categories.length > 0
            ? item.categories.map(c => c && c.name).filter(Boolean).join(', ')
            : '';
        return `<li class="td-search-item" id="td-search-item-${source}-${index}">
            <a target="_blank" rel="noopener" href="${escAttr(safeHref(link))}">
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

    // ---- business hours ----------------------------------------------------
    //
    // The bar above the portal header: open or closed, and how long until that
    // changes. Everything about it is computed here rather than server side,
    // because a countdown rendered into HTML starts being wrong the moment it
    // is sent - and because the boundaries then look after themselves. When the
    // last minute of the working day runs out the bar becomes "closed" on its
    // own, with no request and no reload.
    tdBusinessHours();

    function tdBusinessHours() {
        const bar = document.querySelector('.td-hours');

        if (!bar) {
            return;
        }

        let data;

        try {
            data = JSON.parse(bar.dataset.tdHours || 'null');
        } catch (e) {
            return;
        }

        if (!data) {
            return;
        }

        const DAY = 86400;
        const week = Array.isArray(data.week) ? data.week : [];
        const holidays = Array.isArray(data.holidays) ? data.holidays : [];

        // The visitor's clock is not evidence. Every comparison below is made
        // against the server's, carried in the payload; this is the correction
        // for a machine that is minutes - or hours - out. Without it we would
        // tell someone with a wrong clock, with total confidence, the wrong
        // time to expect a reply.
        const skew = data.now * 1000 - Date.now();
        const now = () => Date.now() + skew;

        // Where an instant falls in the *schedule's* week, which is not
        // necessarily the visitor's. Shifting by the offset and then reading
        // the UTC parts is how you ask "what day and time is it there".
        function parts(ms) {
            const shifted = new Date(ms + data.offset * 1000);

            return {
                day: shifted.getUTCDay(),
                secs:
                    shifted.getUTCHours() * 3600 +
                    shifted.getUTCMinutes() * 60 +
                    shifted.getUTCSeconds(),
            };
        }

        function windowAt(day, secs) {
            return (week[day] || []).find(([start, end]) => secs >= start && secs < end) || null;
        }

        // Seconds until the desk closes. Follows a window that runs to midnight
        // into the next day's, so an overnight shift - which the server splits
        // at midnight so nothing here has to span days - counts down once
        // rather than hitting zero at 00:00 and starting again.
        function closesIn(day, secs) {
            let elapsed = 0;
            let d = day;
            let s = secs;

            for (let i = 0; i <= 7; i++) {
                const open = windowAt(d, s);

                if (!open) {
                    return elapsed;
                }

                elapsed += open[1] - s;

                if (open[1] < DAY) {
                    return elapsed;
                }

                d = (d + 1) % 7;
                s = 0;

                const next = (week[d] || [])[0];

                if (!next || next[0] !== 0) {
                    return elapsed;
                }
            }

            return elapsed;
        }

        // Seconds until the desk opens: later today if there is anything left
        // today, otherwise the first window of the next day that has one.
        function opensIn(day, secs) {
            const later = (week[day] || []).find(([start]) => start > secs);

            if (later) {
                return later[0] - secs;
            }

            for (let i = 1; i <= 7; i++) {
                const windows = week[(day + i) % 7] || [];

                if (windows.length) {
                    return DAY - secs + (i - 1) * DAY + windows[0][0];
                }
            }

            return null;
        }

        function holidayAt(ms) {
            const secs = ms / 1000;

            return holidays.find((h) => secs >= h.from && secs < h.to) || null;
        }

        // A running clock, not a rounded-off estimate: it ticks every second, so
        // it has to read as something counting down rather than as a label.
        //
        // Hours all the way up rather than rolling into days - "39h 08m" is a
        // wait someone can weigh against their afternoon in a way "1d 15h" is
        // not. Minutes and seconds are zero padded so the pill does not change
        // width on every tick and shuffle itself sideways.
        function duration(total) {
            const s = Math.max(0, Math.round(total));
            const h = Math.floor(s / 3600);
            const m = Math.floor((s % 3600) / 60);
            const sec = s % 60;
            const pad = (n) => String(n).padStart(2, '0');

            if (h) {
                /* translators: 1: hours, 2: minutes, 3: seconds. A countdown, e.g. "39h 08m 12s". */
                return sprintf(__('%1$sh %2$sm %3$ss', 'thrivedesk'), h, pad(m), pad(sec));
            }

            if (m) {
                /* translators: 1: minutes, 2: seconds. A countdown under an hour, e.g. "08m 12s". */
                return sprintf(__('%1$sm %2$ss', 'thrivedesk'), m, pad(sec));
            }

            /* translators: %s: seconds. A countdown under a minute, e.g. "12s". */
            return sprintf(__('%ss', 'thrivedesk'), sec);
        }

        // Formatted in the schedule's timezone, not the reader's: a holiday
        // ending at midnight in Dhaka is not a different date because the
        // person reading about it is in Chicago.
        function dayLabel(epochSecs) {
            return new Date((epochSecs + data.offset) * 1000).toLocaleDateString(undefined, {
                timeZone: 'UTC',
                month: 'long',
                day: 'numeric',
            });
        }

        // Said whenever the desk is shut, and only then. Someone reading a
        // closed sign needs to know the door is still open - the wait is on the
        // reply, not on being able to ask.
        const shutNote = () => __('You can still open a ticket — expect a slower reply.', 'thrivedesk');

        // A holiday is not a status, so it is answered separately and takes over
        // the announcement bar. The countdown is deliberately not repeated
        // alongside it: the next scheduled window may well fall inside the
        // holiday, and a confident "opens in 4h" during a week the desk is shut
        // is worse than saying nothing.
        function holidayState() {
            const holiday = holidayAt(now());

            if (!holiday) {
                return null;
            }

            return {
                text: holiday.name
                    ? /* translators: 1: holiday name, 2: the date the desk reopens. */
                      sprintf(__('Closed for %1$s — back on %2$s', 'thrivedesk'), holiday.name, dayLabel(holiday.to))
                    : /* translators: %s: the date the desk reopens. */
                      sprintf(__('Closed for a holiday — back on %s', 'thrivedesk'), dayLabel(holiday.to)),
                note: shutNote(),
            };
        }

        function state() {
            const ms = now();

            if (data.always) {
                return { cls: 'is-open', text: __('Support is online around the clock', 'thrivedesk'), note: '' };
            }

            const { day, secs } = parts(ms);

            if (windowAt(day, secs)) {
                return {
                    cls: 'is-open',
                    /* translators: %s: how long until the desk closes, e.g. "2h 14m". */
                    text: sprintf(__('Support is online — closes in %s', 'thrivedesk'), duration(closesIn(day, secs))),
                    note: '',
                };
            }

            const until = opensIn(day, secs);

            return {
                cls: 'is-closed',
                text:
                    null === until
                        ? __('Support is offline', 'thrivedesk')
                        : /* translators: %s: how long until the desk opens, e.g. "9h 22m". */
                          sprintf(__('Support is offline — opens in %s', 'thrivedesk'), duration(until)),
                note: shutNote(),
            };
        }

        const label = bar.querySelector('.td-hours__text');
        const note = bar.querySelector('.td-hours__note');

        const holidayBar = document.querySelector('.td-holiday');
        const holidayLabel = holidayBar && holidayBar.querySelector('.td-holiday__text');
        const holidayNote = holidayBar && holidayBar.querySelector('.td-holiday__note');

        let shown = '';

        function render() {
            const holiday = holidayState();
            const next = state();
            const key = (holiday ? holiday.text + holiday.note : '') + next.cls + next.text + next.note;

            if (key === shown) {
                return;
            }

            shown = key;

            if (holidayBar) {
                holidayBar.classList.toggle('is-ready', !!holiday);

                if (holiday) {
                    if (holidayLabel) {
                        holidayLabel.textContent = holiday.text;
                    }

                    if (holidayNote) {
                        holidayNote.textContent = holiday.note;
                    }
                }
            }

            // While a holiday is up, the status line beside the filters would only
            // repeat it - and would repeat it with a countdown that cannot be
            // trusted. The announcement above is the whole answer.
            bar.classList.remove('is-open', 'is-closed');

            if (holiday) {
                bar.classList.remove('is-ready');
                return;
            }

            bar.classList.add(next.cls, 'is-ready');

            if (label) {
                label.textContent = next.text;
            }

            if (note) {
                note.textContent = next.note;
            }
        }

        render();
        setInterval(render, 1000);
    }
});