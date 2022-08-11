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
		e.preventDefault();

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

		var selectedTab = e.target.getAttribute('data-target');

		$(e.target).addClass('border-blue-600 active border-b-2');
		document
			.getElementById('tab-content')
			.getElementsByClassName(selectedTab)[0]
			.classList.remove('hidden');
	});

	// settings tab
	$( "#form_provider" ).change(function() {
		let selected_item_id = $("#form_provider").val();
		$.ajax({
			type: "GET",
			url: "/wp-json/td-settings/forms/"+selected_item_id,
			success: function(data){
				console.log(data)
				let option = "";
				option +='<option value="">Please choose a form</option>';
				data.forEach(function(element) {
					option  += "<option value = '"+ element.id + "'>"+ element.title +"</option>";
				});
				$("#form_name").html(option);
			}
		});
	});

	// $( "#form_name" ).change(function() {
	// 	let selected_item_id = $("#form_name").val();
	// 	console.log(selected_item_id)
	// 	$.ajax({
	// 		type: "GET",
	// 		url: "/wp-json/td-settings/form-fields/"+selected_item_id,
	// 		success: function(data){
	// 			console.log(data)
	// 			let option = "";
	// 			option +='<option value="">Please choose a form fields</option>';
	// 			data.fields.forEach(function(element) {
	// 				option  += "<option value = '"+ element.id + "'>"+ element.title +"</option>";
	// 			});
	// 			$("#search_from").html(option);
	// 		}
	// 	});
	// });

});
