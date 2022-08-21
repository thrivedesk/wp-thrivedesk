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

    function saveInput(){
        console.log('Saving data');
    }

    $('#td-search').keyup(debounce(saveInput, 500));

    $('#td_conversation_reply').submit(function(e){
        e.preventDefault();
        let reply_text = $("#td_conversation_editor").val();
        if (reply_text === '') {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Reply text can not be empty!',
            })
        }
        console.log(reply_text);

        // $.ajax({
        //     type: "POST",
        //     url: "/wp-json/td-settings/form/submit",
        //     data: {
        //         td_helpdesk_api_key: td_helpdesk_api_key,
        //     },
        //     success: function(data){
        //         Swal.fire({
        //             title: 'Great',
        //             icon: 'success',
        //             text: data,
        //             showClass: {
        //                 popup: 'animate__animated animate__fadeInDown'
        //             },
        //             hideClass: {
        //                 popup: 'animate__animated animate__fadeOutUp'
        //             },
        //             timer: 4000
        //         });
        //     }
        // });

    });
});
