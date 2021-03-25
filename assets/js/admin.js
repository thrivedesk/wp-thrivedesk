jQuery(document).ready(($) => {
	$('.thrivedesk button.connect').on('click', function(e) {
		e.preventDefault();

		if (1 == $(this).data('connected')) {
			alert('Are you sure to disconnect this integration?');
			jQuery.post(
				thrivedesk.ajax_url,
				{
					action: 'thrivedesk_disconnect_plugin',
					data: {
						plugin: $(this).data('plugin'),
						nonce: $(this).data('nonce'),
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
						plugin: $(this).data('plugin'),
						nonce: $(this).data('nonce'),
					},
				},
				(response) => {
					console.log('TD:' + response);
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
