jQuery(document).ready(($) => {
	$(document.body).on('click', '.thrivedesk button.connect-plugin', (e) => {
		e.preventDefault();

		if (1 == $(e.target).data('connected')) {
			alert('Are you sure to disconnect?');
			jQuery.post(
				thrivedesk.ajax_url,
				{
					action: 'thrivedesk_disconnect_plugin',
					data: {
						plugin: $(e.target).data('plugin'),
						nonce: $(e.target).data('nonce'),
					},
				},
				(response) => {
					if (response) {
						location.reload();
					} else {
						//
					}
				}
			);
		} else {
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
		}
	});
});
