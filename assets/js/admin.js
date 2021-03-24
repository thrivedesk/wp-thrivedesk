jQuery(document).ready(($) => {
	$(document.body).on('click', '.thrivedesk button.connect', (e) => {
		e.preventDefault();

		if (1 == $(e.target).data('connected')) {
			alert('Are you sure to disconnect this integration?');
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
						setTimeout(() => {
							window.location.href = response;
						}, 750);
					} else {
						alert('Unable to connect with ThriveDesk. Make sure you are using this plugin on a live site.');
					}
				}
			);
		}
	});
});
