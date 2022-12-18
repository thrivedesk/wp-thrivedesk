import Swal from "sweetalert2";

jQuery(document).ready(($) => {

	function thrivedeskTabManager(tabElement, contentElement, currentTab = null, innerTab= false){
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

		const tabElement = document.querySelectorAll(
			'.thrivedesk .tab-link a'
		);
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
	$('#td_helpdesk_form').submit(function(e) {
		e.preventDefault();
		let td_helpdesk_api_key = $("#td_helpdesk_api_key").val();
		let td_helpdesk_organization = $("#td-organizations").val();
		let td_helpdesk_assistant = $("#td-assistants").val();
		let td_helpdesk_page_id = $("#td_helpdesk_page_id").val();
		let td_helpdesk_post_types = $(".td_helpdesk_post_types:checked").map((i, item) => item.value).get();
		// let td_helpdesk_form_style = $('input[name="td_helpdesk_form_style"]:checked').val();

		// jquery post with action
		jQuery.post(
			thrivedesk.ajax_url,
			{
				action: 'thrivedesk_helpdesk_form',
				data: {
					td_helpdesk_api_key: td_helpdesk_api_key,
					td_helpdesk_organization: td_helpdesk_organization,
					td_helpdesk_assistant: td_helpdesk_assistant,
					td_helpdesk_page_id: td_helpdesk_page_id,
					td_helpdesk_post_types: td_helpdesk_post_types,
					// td_helpdesk_form_style: td_helpdesk_form_style,
				},
			}
		).success(function (response) {
			let icon;
			if (response) {
				response.status === 'success' ? icon = 'success' : icon = 'error';
				Swal.fire({
					icon: icon,
					title: response.status.charAt(0).toUpperCase() + `${response.status}`.slice(1),
					text: response.message,
				});
			}
		});
	});

	// $('.td-assistant-item').on('change', function(e){
	// 	// make a axios request
	// 	let $target = $(this);
	// 	// check the value of the checkbox
	// 	let checked = $target.is(':checked');
	//
	// 	$('.td-assistant-item').prop('checked', false);
	// 	if (checked) {
	// 		$target.prop('checked', true);
	// 	} else {
	// 		$target.prop('checked', false);
	// 	}
	// 	// $target.prop('checked', true);
	//
	// 	$.ajax({
	// 		type: "POST",
	// 		url:  thrivedesk.wp_json_url + "/thrivedesk/v1/assistant/submit",
	// 		data: {
	// 			selected_assistant_id: $target.val(),
	// 			status: checked,
	// 		}
	// 	}).success(function(data){
	// 		Swal.fire({
	// 			title: 'Great',
	// 			icon: 'success',
	// 			text: data,
	// 			showClass: {
	// 				popup: 'animate__animated animate__fadeInDown'
	// 			},
	// 			hideClass: {
	// 				popup: 'animate__animated animate__fadeOutUp'
	// 			},
	// 			timer: 4000
	// 		});
	// 	}).error(function(data){
	// 		Swal.fire({
	// 			title: 'Error',
	// 			icon: 'error',
	// 			text: 'Something went wrong',
	// 			showClass: {
	// 				popup: 'animate__animated animate__fadeInDown'
	// 			},
	// 			hideClass: {
	// 				popup: 'animate__animated animate__fadeOutUp'
	// 			},
	// 			timer: 4000
	// 		})
	// 	})
	// });

	// verify the API key
	$('#td-api-verification-btn').on('click', async function (e) {
		e.preventDefault();
		let $target = $(this);
		let apiKey = $('#td_helpdesk_api_key').val().trim();

		// get request to verify the API key using fetch
		await fetch('https://api.thrivedesk.xyz/v1/me', {
			method: 'GET',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'Authorization': 'Bearer ' + apiKey,
			},
		}).then(response => {
			if (response.ok) {
				return response.json();
			} else {
				throw new Error('Something went wrong');
			}
		}).then(data => {
			console.log(data.organizations);
			// show the organization list
			let orgList = $('#td-organizations');
			orgList.html('');
			orgList.append('<option value="">Select Organization</option>');
			data.organizations.forEach(function (item) {
				orgList.append('<option value="' + item.slug + '">' + item.company + '</option>');
			});

			// change the button text to verified then disable the button
			$target.text('Verified');
			$target.prop('disabled', true);

			Swal.fire({
				title: 'Great',
				icon: 'success',
				text: 'API key verified successfully',
				showClass: {
					popup: 'animate__animated animate__fadeInDown'
				},
				hideClass: {
					popup: 'animate__animated animate__fadeOutUp'
				},
				timer: 4000
			});
		}).catch(error => {
			console.log(error);
			Swal.fire({
				title: 'Error',
				icon: 'error',
				text: 'Something went wrong',
				showClass: {
					popup: 'animate__animated animate__fadeInDown'
				},
				hideClass: {
					popup: 'animate__animated animate__fadeOutUp'
				},
				timer: 4000
			})
		});
	});

	// load the assistant list on change of organization
	$('#td-organizations').on('change', async function (e) {
		let $target = $(this);
		let orgSlug = $target.val();
		let apiKey = $('#td_helpdesk_api_key').val().trim();
		// get request to verify the API key using fetch
		await fetch('https://api.thrivedesk.xyz/v1/assistants', {
			method: 'GET',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'Authorization': 'Bearer ' + apiKey,
				'X-Td-Organization-Slug': orgSlug,
			},
		}).then(response => {
			if (response.ok) {
				return response.json();
			} else {
				throw new Error('Something went wrong');
			}
		}).then(data => {
			console.log(data.assistants);
			// show the assistants list
			let assistantList = $('#td-assistants');
			assistantList.html('');
			assistantList.append('<option value="">Select Assistant</option>');
			data.assistants.forEach(function (item) {
				assistantList.append('<option value="' + item.id + '">' + item.name + '</option>');
			});

		}).catch(error => {
			console.log(error);
			Swal.fire({
				title: 'Error',
				icon: 'error',
				text: 'Something went wrong',
				showClass: {
					popup: 'animate__animated animate__fadeInDown'
				},
				hideClass: {
					popup: 'animate__animated animate__fadeOutUp'
				},
				timer: 4000
			})
		});
	});
});
