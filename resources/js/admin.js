import Swal from 'sweetalert2';
import ConfettiGenerator from "confetti-js";
var assistants = [];

jQuery(document).ready(($) => {
	// plugin connection 
	$('.thrivedesk button.connect').on('click', function (e) {
		e.preventDefault();

		let $target = $(this);

		if (1 == $target.data('connected')) {
			alert('Are you sure to disconnect this integration?');
			jQuery.post(
				thrivedesk.ajax_url,
				{
					action: 'thrivedesk_disconnect_plugin',
					data: {
						plugin: $target.data('plugin'),
						nonce: $target.data('nonce'),
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
						plugin: $target.data('plugin'),
						nonce: $target.data('nonce'),
					},
				},
				(response) => {
					if (response) {
						setTimeout(() => {
							window.location.href = response;
						}, 750);
					} else {
						alert(
							'Unable to connect with ThriveDesk. Make sure you are using this plugin on a live site.'
						);
					}
				}
			);
		}
	});

	/**
	 * admin tab
	 */
	$('.thrivedesk .tab-link a').on('click', function (e) {
		// e.preventDefault();

		const tabElement = document.querySelectorAll('.thrivedesk .tab-link a');
		const contentElement = document.querySelectorAll(
			'.thrivedesk #tab-content>div'
		);

		thrivedeskTabManager(tabElement, contentElement, this);
	});

	/**
	 * Inner tab content
	 */
	$('.thrivedesk .inner-tab-link a').on('click', function (e) {
		const innerTabElement = document.querySelectorAll(
			'.thrivedesk .inner-tab-link a'
		);
		const contentElement = document.querySelectorAll(
			'.thrivedesk #inner-tab-content>div'
		);

		thrivedeskTabManager(innerTabElement, contentElement, this, true);
	});

	// get the fragment from url
	let fragment = window.location.hash;
	if (fragment) {
		// remove the # from the fragment
		fragment = fragment.substr(1);
		// get the element with the id of the fragment
		const element = document.querySelector(`a[href="#${fragment}"]`);
		if (element) {
			// if the element exists, click it
			element.click();
		}
	}

	$('#submit-btn').on('click', function (e) {
		e.preventDefault();

		let td_helpdesk_api_key = $('#td_helpdesk_api_key').val();

		jQuery.post(thrivedesk.ajax_url, {
			action: 'thrivedesk_load_assistants',
			data: {
				td_helpdesk_api_key: td_helpdesk_api_key,
			},
		}).success(function (response) {
			let parsedResponse = JSON.parse(response);
			let data = parsedResponse?.data;
			let payload = {
				td_helpdesk_api_key: td_helpdesk_api_key,
				td_helpdesk_assistant: (data?.assistants?.length == 1) ? data.assistants[0].id : null,
			}

			jQuery.post(thrivedesk.ajax_url, {
				action: 'thrivedesk_helpdesk_form',
				data: {
					td_helpdesk_api_key: payload.td_helpdesk_api_key,
					td_helpdesk_assistant: payload.td_helpdesk_assistant,
				},
			}).success(function (response) {
				let icon;
				if (response) {
					response.status === 'success' ? (icon = 'success') : (icon = 'error');
					Swal.fire({
						icon: icon,
						title: response.status.charAt(0).toUpperCase() + `${response.status}`.slice(1),
						text: response.message,
						confirmButtonText: 'Complete',
					}).then((result) => {
						localStorage.setItem('shouldTriggerConfetti', 'true');
						if (result.isConfirmed) {
							window.location.href = '/wp-admin/admin.php?page=thrivedesk';
						}
					});
				}
			});
		});
	});

	// helpdesk form
	$('#td_helpdesk_form').submit(function (e) {
		e.preventDefault();
		let td_helpdesk_api_key = $('#td_helpdesk_api_key').val();
		let td_helpdesk_assistant = $('#td-assistants').val();
		let td_helpdesk_page_id = $('#td_helpdesk_page_id').val();
		let td_helpdesk_post_types = $('.td_helpdesk_post_types:checked')
			.map((i, item) => item.value)
			.get();

		let td_helpdesk_post_sync = $('.td_helpdesk_post_sync:checked')
			.map((i, item) => item.value)
			.get();

		let td_user_account_pages = $('.td_user_account_pages:checked')
			.map((i, item) => item.value)
			.get();

		jQuery
			.post(thrivedesk.ajax_url, {
				action: 'thrivedesk_helpdesk_form',
				data: {
					td_helpdesk_api_key: td_helpdesk_api_key,
					td_helpdesk_assistant: td_helpdesk_assistant,
					td_helpdesk_page_id: td_helpdesk_page_id,
					td_helpdesk_post_types: td_helpdesk_post_types,
					td_helpdesk_post_sync: td_helpdesk_post_sync,
					td_user_account_pages: td_user_account_pages,
				},
			})
			.success(function (response) {
				let icon;
				if (response.status === 'success') {
					response.status === 'success' ? (icon = 'success') : (icon = 'error');
					Swal.fire({
						icon: icon,
						title: response.status.charAt(0).toUpperCase() + `${response.status}`.slice(1),
						text: response.message,
					}).then((result) => {
						localStorage.setItem('shouldTriggerConfetti', 'true');
						if (result.isConfirmed) {
							window.location.href = '/wp-admin/admin.php?page=thrivedesk';
						}
					});
				}
			});
	});
	// Confetti 
	async function triggerConfetti() {
		var confettiElement = document.getElementById('confetti-canvas');

		var confettiSettings = {
			target: confettiElement,
			max: 500,
			size: 1,
			animate: true,
			props: ['circle', 'square', 'triangle', 'line'],
			colors: [[255, 0, 0], [0, 255, 0], [0, 0, 255], [255, 255, 0], [0, 255, 255], [255, 0, 255]],
			clock: 60,
			rotate: true,
			width: window.innerWidth,
			height: window.innerHeight,
			start_from_edge: false,
			respawn: true,
			width: 960,
			height: 767,
		};


		var confetti = new ConfettiGenerator(confettiSettings);
		confetti.render();
		
		setTimeout(() => {
			confetti.clear();
			document.getElementById('confetti-canvas').style.display = 'none';
		}, 3000);

	}
	// Confetti for API Key validation 
	var $key = $('#td_helpdesk_api_key').val().trim();
	if ($key) {
		let td_helpdesk_api_key = $('#td_helpdesk_api_key').val();
		const token = new URLSearchParams(window.location.search).get('token');
		if (localStorage.getItem('shouldTriggerConfetti') === 'true') {
			triggerConfetti();
			localStorage.setItem('shouldTriggerConfetti', 'false');
		}
	}

	// verify the API key
	$('#td-api-verification-btn').on('click', async function (e) {
		e.preventDefault();
		let $target = $(this);
		let apiKey = $('#td_helpdesk_api_key').val().trim();

		if (apiKey === '') {
			Swal.fire({
				icon: 'error',
				title: 'Error',
				text: 'API Key is required',
			});
			return;
		}

		jQuery
			.post(thrivedesk.ajax_url, {
				action: 'thrivedesk_api_key_verify',
				data: {
					td_helpdesk_api_key: apiKey,
				},
			})
			.success(function (response) {
				let parsedResponse = JSON.parse(response);
				let data = parsedResponse?.data;
				if (parsedResponse?.code === 422) {
					Swal.fire({
						icon: 'error',
						title: 'Error',
						text: data?.message,
					});

					return;
				}

				if(data?.message==='Unauthenticated.'){
					Swal.fire({
						icon: 'error',
						title: 'Error',
						text: 'Invalid API Key',
					});
				}
				else if (data?.message==='Server Error'){
					Swal.fire({
						icon: 'error',
						title: 'Error',
						text: 'Server Error',
					});
				} else {
					loadAssistants(apiKey);
					isAllowedPortal()

					$target.text('Verified');
					$target.prop('disabled', true);

					// remove the disabled attribute from the id td-assistants
					$('#td-assistants').prop('disabled', false);
					// add hidden class to the id td-api-verification-btn
					$('#api_key_alert').addClass('hidden');

					Swal.fire({
						icon: 'success',
						title: 'Success',
						text: 'API Key Verified',
					});
					// disable api editable
					$('.api-key-preview').removeClass('hidden');
					$('.api-key-editable').addClass('hidden');
				}
			})
			.error(function (error) {
				Swal.fire({
					icon: 'error',
					title: 'Error',
					text: 'Something went wrong',
				});
			});
	});
	// API key reveal box 
	$('.api-key-preview .trigger').on('click', function(e){
		$('.api-key-preview').addClass('hidden');
		$('.api-key-editable').removeClass('hidden');

	})
	// Load assistant 
	async function loadAssistants(apiKey) {
		jQuery
			.post(thrivedesk.ajax_url, {
				action: 'thrivedesk_load_assistants',
				data: {
					td_helpdesk_api_key: apiKey,
				},
			})
			.success(function (response) {
				let parsedResponse = JSON.parse(response);
				let data = parsedResponse?.data;

				if(data?.message==='Unauthenticated.'){
					Swal.fire({
						icon: 'error',
						title: 'Error',
						text: 'Invalid API Key',
					});
				}
				else if (data?.message==='Server Error'){
					Swal.fire({
						icon: 'error',
						title: 'Error',
						text: 'Server Error',
					});
				} else {

					let assistantList = $('#td-assistants');
					assistantList.html('');

					if (data?.assistants?.length > 0) {
						assistants = data?.assistants;
						assistantList.append('<option value="">Select Assistant</option>');
						data.assistants.forEach(function (item) {
							assistantList.append(
								'<option value="' + item.id + '">' + item.name + '</option>'
							);
						});
					}else {
						assistantList.append(
							'<option value="">No Assistant Found</option>'
						);

						assistantList.prop('disabled', true);

					}
				}
			})
			.error(function () {
				Swal.fire({
					icon: 'error',
					title: 'Error',
					text: 'Something went wrong',
				});
			});
	}
	// Portal check 
	async function isAllowedPortal() {
		let apiKey = $('#td_helpdesk_api_key').val().trim();
		jQuery
			.post(thrivedesk.ajax_url, {
				action: 'thrivedesk_check_portal_access',
				data: {
					td_helpdesk_api_key: apiKey,
				},
			})
			.success(function (response) {
				let data = JSON.parse(response);
				if(data.status == 'success'){
					let parsedResponse = JSON.parse(response);
					let data = parsedResponse?.data;
					if (data === true) {
						$('#api_key_alert').addClass('hidden');
						$('#td_portal').removeClass('hidden');
						
					}
				}
				else{
					$('#portal_feature_alert').removeClass('hidden');
				}
			})
			.error(function () {
				Swal.fire({
					icon: 'error',
					title: 'Error',
					text: 'Something went wrong',
				});
			});
	}
	// clear cache
	$('#thrivedesk_clear_cache_btn').on('click', function (e) {
		jQuery
			.get(thrivedesk.ajax_url, {
				action: 'thrivedesk_clear_cache',
			})
			.success(function (response) {
				Swal.fire({
					icon: 'success',
					title: 'Success',
					text: 'Cache Cleared',
				}).then((result) => {
					location.reload();
				});
			})
			.error(function () {
				Swal.fire({
					icon: 'error',
					title: 'Error',
					text: 'Something went wrong',
				});
			});
	});
});

