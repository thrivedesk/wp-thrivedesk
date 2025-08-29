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

	// on click complete setup button to verify API key
	$('#submit-btn').on('click', function (e) {
		e.preventDefault();
		
		// Check if thrivedesk object exists
		if (typeof thrivedesk === 'undefined') {
			console.error('ThriveDesk: Configuration not loaded');
			Swal.fire({
				icon: 'error',
				title: 'Error',
				text: 'ThriveDesk configuration not loaded. Please refresh the page.',
			});
			return;
		}

		// Add loading state
		let $btn = $(this);
		$btn.prop('disabled', true)
		   .html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');

		let td_helpdesk_api_key = $('#td_helpdesk_api_key').val();

		jQuery.post(thrivedesk.ajax_url, {
			action: 'thrivedesk_api_key_verify',
			nonce: thrivedesk.nonce,
			data: {
				td_helpdesk_api_key: td_helpdesk_api_key,
			},
		}).done((response) => {
			let parsedResponse;
			try {
				parsedResponse = typeof response === 'string' ? JSON.parse(response) : response;
			} catch (e) {
				console.error('ThriveDesk: Failed to parse response:', e);
				Swal.fire({
					icon: 'error',
					title: 'Error',
					text: 'Invalid response from server',
				});
				return;
			}
			
			let data = parsedResponse?.data;
			let status = parsedResponse?.status;

			if(handleFailedResponse(status, parsedResponse) === false){
				return;
			}

			jQuery.post(thrivedesk.ajax_url, {
				action: 'thrivedesk_load_assistants',
				nonce: thrivedesk.nonce,
				data: {
					td_helpdesk_api_key: td_helpdesk_api_key,
				},
			}).success(function (response) {
				let parsedResponse = JSON.parse(response);
				let data = parsedResponse?.data;
				let status = parsedResponse?.status;

				let payload = {
					td_helpdesk_api_key: td_helpdesk_api_key,
					td_helpdesk_assistant: (data?.assistants?.length == 1) ? data.assistants[0].id : null,
				}

				debugger;

				jQuery.post(thrivedesk.ajax_url, {
					action: 'thrivedesk_helpdesk_form',
					nonce: thrivedesk.nonce,
					data: {
						td_helpdesk_api_key: payload.td_helpdesk_api_key,
						td_helpdesk_assistant: payload.td_helpdesk_assistant,
					},
				}).success(function (response) {
					let parsedResponse;
					try {
						parsedResponse = typeof response === 'string' ? JSON.parse(response) : response;
					} catch (e) {
						console.error('ThriveDesk: Failed to parse helpdesk form response:', e);
						Swal.fire({
							icon: 'error',
							title: 'Error',
							text: 'Invalid response from helpdesk form submission',
						});
						return;
					}
					
					let icon;
					if (parsedResponse) {
						parsedResponse.status === 'success' ? (icon = 'success') : (icon = 'error');
						Swal.fire({
							icon: icon,
							title: parsedResponse.status.charAt(0).toUpperCase() + `${parsedResponse.status}`.slice(1),
							text: parsedResponse.message,
							confirmButtonText: 'Continue to settings',
						}).then((result) => {
							localStorage.setItem('shouldTriggerConfetti', 'true');
							if (result.isConfirmed) {
								window.location.href = '/wp-admin/admin.php?page=thrivedesk';
							}
						});
					}
				}).fail(function(xhr, status, error) {
					console.error('ThriveDesk: Helpdesk form submission failed:', error);
					Swal.fire({
						icon: 'error',
						title: 'Error',
						text: 'Failed to save helpdesk form: ' + error,
					});
				});
			});
		}).fail(function (error) {
			Swal.fire({
				icon: 'error',
				title: 'Error',
				text: 'Something went wrong',
			});
		}).always(function() {
			// Remove loading state
			setTimeout(function() {
				$btn.prop('disabled', false)
					.html('Complete Setup');
			}, 1500);
		});

	});

	async function handleThriveDeskMainForm() {
		let td_helpdesk_api_key = $('#td_helpdesk_api_key').val();
		let td_helpdesk_assistant = $('#td-assistants').val();
		let td_helpdesk_inbox_id = $('#td-inboxes').val();
		// Get the selected routes as an array
		let td_assistant_route_list = $('#td-excluded-routes').val() || [];
		let td_helpdesk_page_id = $('#td_helpdesk_page_id').val();
		let td_knowledgebase_slug = $('#td_knowledgebase_slug').val();
		let td_helpdesk_post_types = $('.td_helpdesk_post_types:checked')
			.map((i, item) => item.value)
			.get();
		let td_helpdesk_post_sync = $('.td_helpdesk_post_sync:checked')
			.map((i, item) => item.value)
			.get();
		let td_user_account_pages = $('.td_user_account_pages:checked')
			.map((i, item) => item.value)
			.get();

		// Get the nonce from the form
		let nonce = thrivedesk.nonce;
		
		let data = {
			td_helpdesk_api_key: td_helpdesk_api_key,
			td_helpdesk_assistant: td_helpdesk_assistant,
			td_helpdesk_inbox_id: td_helpdesk_inbox_id,
			td_helpdesk_page_id: td_helpdesk_page_id,
			td_knowledgebase_slug: td_knowledgebase_slug,
			td_helpdesk_post_types: td_helpdesk_post_types,
			td_helpdesk_post_sync: td_helpdesk_post_sync,
			td_user_account_pages: td_user_account_pages,
			td_assistant_route_list: td_assistant_route_list
		};

		// Returning the AJAX call as a Promise
		return await jQuery.post(thrivedesk.ajax_url, {
			action: 'thrivedesk_helpdesk_form',
			nonce: nonce,
			data: data,
		});
	}

	// helpdesk form
	$('#td_helpdesk_form').submit(async function (e) {
		let $btn = $('#td_setting_btn_submit');
		$btn.prop('disabled', true)
			.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');


		e.preventDefault();
		handleThriveDeskMainForm().then(function (response) {
			let icon;
			if (response.status === 'success') {
				response.status === 'success' ? (icon = 'success') : (icon = 'error');
				Swal.fire({
					icon: icon,
					title: response.status.charAt(0).toUpperCase() + `${response.status}`.slice(1),
					text: response.message,
				});
				// .then((result) => {
				// 	localStorage.setItem('shouldTriggerConfetti', 'true');
				// 	if (result.isConfirmed) {
				// 		window.location.href = '/wp-admin/admin.php?page=thrivedesk';
				// 	}
				// });

				// Remove loading state
				setTimeout(function() {
					$btn.prop('disabled', false)
						.html('Save');
				}, 1000);
			}
		}).catch(()=>{
			Swal.fire({
				icon: 'error',
				title: 'Error',
				text: 'Form submition failed',
			});
		});
	});

	// Confetti
	async function triggerConfetti() {
		var confettiElement = document.getElementById('confetti-canvas');
		confettiElement.style.display = 'block';

		var confettiSettings = {
			target: confettiElement,
			max: 600,
			size: 0.5,
			animate: true,
			props: ['circle', 'square', 'triangle'],
			colors: [[255, 0, 64], [0, 255, 64], [0, 64, 255]],
			clock: 60,
			rotate: true,
			start_from_edge: false,
			respawn: true,
			width: 960,
			height: 767,
		};


		var confetti = new ConfettiGenerator(confettiSettings);
		confetti.render();

		setTimeout(() => {
			confetti.clear();
			confettiElement.style.display = 'none';
		}, 2500);

	}
	// Confetti for API Key validation
	var $key = $('#td_helpdesk_api_key').val().trim();
	if ($key) {
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
				nonce: thrivedesk.nonce,
				data: {
					td_helpdesk_api_key: apiKey,
				},
			})
			.success(function (response) {
				let parsedResponse = JSON.parse(response);
				let status = parsedResponse.status;
				let data = parsedResponse?.data;

				if(handleFailedResponse(status, parsedResponse) === false){
					return;
				}

				loadAssistants(apiKey);
				loadInboxes(apiKey);
				isAllowedPortal();

				const buttons = document.querySelectorAll('.disConnectBtn');
				buttons.forEach(target => {
					if (1 == target.dataset.connected) {
						jQuery.post(
							thrivedesk.ajax_url,
							{
								action: 'thrivedesk_disconnect_plugin',
								data: {
									plugin: target.dataset.plugin,
									nonce: target.dataset.nonce,
								},
							},
							(response) => {

							}
						);
					}
				})

				$target.text('Verified');
				$target.prop('disabled', true);

				// remove the disabled attribute from the id td-assistants and td-inboxes
				$('#td-assistants').prop('disabled', false);
				$('#td-inboxes').prop('disabled', false);
				// add hidden class to the id td-api-verification-btn
				$('#api_key_alert').addClass('hidden');

				Swal.fire({
					icon: 'success',
					title: 'Success',
					text: data?.message,
				}).then(async (result)=>{
					if (result.isConfirmed) {
						jQuery.post(thrivedesk.ajax_url, {
							action: 'thrivedesk_system_info',
							data: {
								td_helpdesk_api_key: apiKey,
							},
						})
							.success(function (response) {
								handleThriveDeskMainForm().then((response) => {
									if (response.status === 'success') {
										localStorage.setItem('shouldTriggerConfetti', 'true');
										setTimeout(() => {
											window.location.href = '/wp-admin/admin.php?page=thrivedesk';
										}, 1000);
									}
								}).catch(() => {
									Swal.fire({
										icon: 'error',
										title: 'Error',
										text: 'Form submition failed',
									});
								});
							}).error(function (error) {
							Swal.fire({
								icon: 'error',
								title: 'Error',
								text: 'Something went wrong',
							});
						});
					}
				});
				// disable api editable
				$('.api-key-preview').removeClass('hidden');
				$('.api-key-editable').addClass('hidden');

			})
			.error(function (error) {
				Swal.fire({
					icon: 'error',
					title: 'Error',
					text: 'Something went wrong',
				});
			});
	});

	function handleFailedResponse(status, parsedResponse) {
		let data = parsedResponse?.data;

		if (status === 'false' || status === 'error') {
			if (parsedResponse?.code === 422) {
				Swal.fire({
					icon: 'error',
					title: 'Error',
					text: data?.message,
				});

				return false;
			}
			if(data?.message==='Unauthenticated.'){
				Swal.fire({
					icon: 'error',
					title: 'Error',
					text: 'Invalid API Key',
				});

				return false;
			}
			else if (data?.message==='Server Error'){
				Swal.fire({
					icon: 'error',
					title: 'Error',
					text: 'Server Error',
				});
				return false;
			}
			else {
				Swal.fire({
					icon: 'error',
					title: 'Error',
					text: data?.message || parsedResponse?.message || 'Something went wrong',
				});

				return false;
			}
		} else if (status === 'success') {
			return true;
		} else {
			return true;
		}
	}

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
				nonce: thrivedesk.nonce,
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
    
    // Load inboxes
    async function loadInboxes(apiKey) {
        jQuery
            .post(thrivedesk.ajax_url, {
                action: 'thrivedesk_load_inboxes',
                data: {
                    td_helpdesk_api_key: apiKey,
                },
                timeout: 25000 // 25 second timeout to prevent fatal errors
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
                    let inboxList = $('#td-inboxes');
                    
                    // Get the saved inbox ID from the data attribute (set by PHP)
                    let savedInboxId = inboxList.data('selected') || inboxList.val();
                    
                    inboxList.html('');

                    if (data?.data?.length > 0) {
                        inboxes = data?.data;
                        inboxList.append('<option value="">All inboxes</option>');
                        data.data.forEach(function (item) {
                            let isSelected = (savedInboxId === item.id);
                            inboxList.append(
                                '<option value="' + item.id + '"' + (isSelected ? ' selected' : '') + '>' + item.name + '</option>'
                            );
                        });
                        
                        // Restore the selected value
                        if (savedInboxId) {
                            inboxList.val(savedInboxId);
                        }
                    }else {
                        inboxList.append(
                            '<option value="">No Inbox Found</option>'
                        );

                        inboxList.prop('disabled', true);
                    }
                }
            })
            .error(function (xhr, status, error) {
                let errorMessage = 'Something went wrong';
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (error) {
                    errorMessage = 'Error: ' + error;
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMessage,
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

