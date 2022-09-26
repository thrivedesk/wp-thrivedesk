import Swal from "sweetalert2";

jQuery(document).ready(($) => {
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
	$('.wrap.thrivedesk .admin-tabs a').on('click', function (e) {
		// e.preventDefault();

		var tabElement = document.querySelectorAll(
			'.wrap.thrivedesk .admin-tabs a'
		);
		var contentElement = document.querySelectorAll(
			'.wrap.thrivedesk #tab-content>div'
		);

		tabElement.forEach(function (linkElement) {
			$(linkElement).removeClass('border-blue-600 active border-b-2');
		});
		contentElement.forEach(function (contentElement) {
			$(contentElement).removeClass('block').addClass('hidden');
		});

		const selectedTab = this.getAttribute('data-target');

		$(this).addClass('border-blue-600 active border-b-2');
		document
			.getElementById('tab-content')
			.getElementsByClassName(selectedTab)[0]
			.classList.remove('hidden');
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
	$('#td_helpdesk_form').submit(function(e){
		e.preventDefault();
		let td_helpdesk_api_key = $("#td_helpdesk_api_key").val();
		let td_helpdesk_page_id = $("#td_helpdesk_page_id").val();
		let td_helpdesk_post_types = $(".td_helpdesk_post_types:checked").map((i, item)=>item.value).get();
		// let td_helpdesk_form_style = $('input[name="td_helpdesk_form_style"]:checked').val();

		$.ajax({
			type: "POST",
			url: thrivedesk.wp_json_url + "/td-settings/form/submit",
			data: {
				td_helpdesk_api_key: td_helpdesk_api_key,
				td_helpdesk_page_id: td_helpdesk_page_id,
				td_helpdesk_post_types: td_helpdesk_post_types,
				// td_helpdesk_form_style: td_helpdesk_form_style,
			},
			success: function(data){
				Swal.fire({
					title: 'Great',
					icon: 'success',
					text: data,
					showClass: {
						popup: 'animate__animated animate__fadeInDown'
					},
					hideClass: {
						popup: 'animate__animated animate__fadeOutUp'
					},
					timer: 4000
				});
			},
			error: function(data){
				Swal.fire({
					title: 'Error',
					icon: 'error',
					showClass: {
						popup: 'animate__animated animate__fadeInDown'
					},
					hideClass: {
						popup: 'animate__animated animate__fadeOutUp'
					},
					timer: 4000
				});
			}
		});

	});

	$('#td-assistant-checked-toggle').on('change', function(e){
		// make a axios request
		e.preventDefault();
	});

});
