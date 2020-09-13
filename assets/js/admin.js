jQuery(document).ready(($) => {
	$(document.body).on('click', '.thrivedesk button.connect-plugin', (e) => {
		e.preventDefault();

		jQuery.post(
			thrivedesk.ajax_url,
			{
				action: 'thrivedesk_connect_plugin',
				data: {
					plugin: $(e.target).data('plugin'),
					nonce: $(e.target).data('nonce'),
				},
			},
			(response) => {
				if (response) {
					window.location.href = response;
				} else {
					alert('Failed to create plugin connect url');
				}
			}
		);
	});
});
