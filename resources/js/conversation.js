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
});
