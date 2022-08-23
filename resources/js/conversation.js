import Swal from "sweetalert2";

jQuery(document).ready(($) => {
    $('body').append(`<div id="modal-backdrop" style="display: none" class="bg-gray-900 bg-opacity-50 dark:bg-opacity-80 fixed inset-0 z-40"></div>`)
    $('#openConversationModal').click(function (e) {
        $('.td-modal').fadeIn(500);
        $('#modal-backdrop').fadeIn(500);
    });

    $('#close_modal').click(function (e) {
        $('.td-modal').fadeOut(500);
        $('#modal-backdrop').fadeOut(500);
    });

    function debounce( callback, delay ) {
        let timeout;
        return function() {
            clearTimeout( timeout );
            timeout = setTimeout( callback, delay );
        }
    }

    function search_query(){
        let search_query = $('#td_search_input').val();
        if (!search_query) return;
        $.ajax({
            type: "POST",
            url: "/wp-json/td-search-query/docs",
            data: {
                query_string: search_query,
            },
            success: function(data){
                const list = $('#td_search_list');
                let search_results = '';
                data.data.forEach(function(item, i){
                    search_results += `<li class="tdSearch-Hit hover:bg-blue-700" id="td-search-item-${i}" role="option" aria-selected="false"><a class="tdSearch-Hit--Result" target="_blank" href="${item.link}"><div class="tdSearch-Hit-Container"><div class="tdSearch-Hit-icon"></div><div class="tdSearch-Hit-content-wrapper"><span class="tdSearch-Hit-title">${item.excerpt}</span><spanclass="tdSearch-Hit-path">${item.title}</span></div><div class="tdSearch-Hit-action"></div></div></a></li>`;

                    $(document).on('mouseover',`#td-search-item-${i}`,function(){
                        console.log('on mouse over')
                        $(`#td-search-item-${i}`).attr('aria-selected',true);
                    });
                    $(document).on('mouseout',`#td-search-item-${i}`,function(){
                        $(`#td-search-item-${i}`).attr('aria-selected',false);
                    });

                    $(document).on('keyup', '#td_search_input', function(e){
                        e.preventDefault();
                        e.stopPropagation();
                        const pressedDown = (e.key === 'ArrowDown' || e.keyCode ===40);
                        const pressedUp = (e.key === 'ArrowUp' || e.keyCode ===38);
                        if ( pressedDown || pressedUp) {
                            const current = $(`#td-search-item-${i}`);
                            const next = pressedDown ? current.next() : current.prev();
                            if (next.length) {
                                current.attr('aria-selected',false);
                                next.attr('aria-selected',true);
                                next.focus();
                            }
                        }
                    });
                });
                list.html(search_results);
            }
        });
    }

    $('#td_search_input').keyup(debounce(search_query, 1000));

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
