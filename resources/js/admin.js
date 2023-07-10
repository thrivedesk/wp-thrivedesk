import Swal from 'sweetalert2';

jQuery(document).ready(function($) {
	if (typeof $.fn.select2 === 'undefined') {
		return;
	}

	$('#td_helpdesk_post_types').select2({
		placeholder: 'Select post types',
		width: '100%'
	});

	$('#td_helpdesk_post_sync').select2({
		placeholder: 'Select post sync types',
		width: '100%'
	});
});


jQuery(document).ready(($) => {
	function thrivedeskTabManager(
		tabElement,
		contentElement,
		currentTab = null,
		innerTab = false
	) {
		tabElement.forEach(function (linkElement) {
			$(linkElement).removeClass('active');
		});
		contentElement.forEach(function (contentElement) {
			$(contentElement).removeClass('block').addClass('hidden');
		});

		const selectedTab = currentTab.getAttribute('data-target');

		$(currentTab).addClass('active');

		if (innerTab) {
			document
				.getElementById('inner-tab-content')
				.getElementsByClassName(selectedTab)[0]
				.classList.remove('hidden');
		} else {
			document
				.getElementById('tab-content')
				.getElementsByClassName(selectedTab)[0]
				.classList.remove('hidden');
		}
	}

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

	// helpdesk form
	$('#td_helpdesk_form').submit(function (e) {
		e.preventDefault();
		let td_helpdesk_api_key = $('#td_helpdesk_api_key').val();
		let td_helpdesk_assistant = $('#td-assistants').val();
		let td_helpdesk_page_id = $('#td_helpdesk_page_id').val();

		// 'td_helpdesk_page_id' is implemented by select2, so need to get the value from select2
		let td_helpdesk_post_types = [];
		let selected_post_types = $('#td_helpdesk_post_types').select2('data');
		if (selected_post_types && selected_post_types.length > 0) {
			for (let i = 0; i < selected_post_types.length; i++) {
				td_helpdesk_post_types[i] = selected_post_types[i].id;
			}
		}

		let td_helpdesk_post_sync = [];
		let selected_post_sync = $('#td_helpdesk_post_sync').select2('data');
		if (selected_post_sync && selected_post_sync.length > 0) {
			for (let i = 0; i < selected_post_sync.length; i++) {
				td_helpdesk_post_sync[i] = selected_post_sync[i].id;
			}
		}

		let user_account_pages = $('.user_account_pages:checked')
			.map((i, item) => item.value)
			.get();

		let td_helpdesk_enable_knowledge_base = $('#td_helpdesk_enable_knowledge_base').is(':checked') ? 1 : 0;
		console.log(td_helpdesk_enable_knowledge_base);

		jQuery
			.post(thrivedesk.ajax_url, {
				action: 'thrivedesk_helpdesk_form',
				data: {
					td_helpdesk_api_key: td_helpdesk_api_key,
					td_helpdesk_assistant: td_helpdesk_assistant,
					td_helpdesk_page_id: td_helpdesk_page_id,
					td_helpdesk_post_types: td_helpdesk_post_types,
					td_helpdesk_post_sync: td_helpdesk_post_sync,
					user_account_pages: user_account_pages,
					knowledge_base_search_modal: td_helpdesk_enable_knowledge_base,
				},
			})
			.success(function (response) {
				let icon;
				if (response) {
					response.status === 'success' ? (icon = 'success') : (icon = 'error');
					Swal.fire({
						icon: icon,
						title:
							response.status.charAt(0).toUpperCase() +
							`${response.status}`.slice(1),
						text: response.message,
					}).then((result) => {
						if (result.isConfirmed) {
							location.reload();
						}
					});
				}
			});
	});

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

					$target.text('Verified');
					$target.prop('disabled', true);

					// remove the disabled attribute from the id td-assistants
					$('#td-assistants').prop('disabled', false);
					// add hidden class to the id td-api-verification-btn
					$('#no_api_key_alert').addClass('hidden');

					isAllowedPortal()

					Swal.fire({
						icon: 'success',
						title: 'Success',
						text: 'API Key Verified',
					});
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
				let parsedResponse = JSON.parse(response);
				let data = parsedResponse?.data;

				if (data === true) {
					$('#td_post_content').removeClass('hidden');
				} else {
					$('#portal_feature').removeClass('hidden');
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

