import Swal from 'sweetalert2';
import { __, sprintf } from '@wordpress/i18n';
var assistants = [];
// Was never declared. Under webpack's strict mode the assignment in
// loadInboxes() threw a ReferenceError and aborted the callback, which is why
// the inbox dropdown never populated.
var inboxes = [];

/**
 * Build an <option> as a DOM node.
 *
 * Never concatenate API-supplied values into an HTML string for .append():
 * jQuery parses the fragment and evaluates any script in it, so an assistant or
 * inbox named with a payload on the ThriveDesk side would run in a
 * manage_options session. Setting `value`/`text` on a node assigns them as
 * data, so there is no markup to parse.
 */
function tdOption(value, text) {
	return jQuery('<option>', { value: value == null ? '' : String(value), text: text == null ? '' : String(text) });
}

/**
 * Copy `text` using the pre-Clipboard-API route, synchronously.
 *
 * The textarea is positioned off-screen rather than hidden because a
 * display:none element cannot be selected.
 *
 * @return {boolean} whether the copy landed.
 */
function tdLegacyCopy(text) {
	const area = document.createElement('textarea');
	area.value = text;
	area.setAttribute('readonly', '');
	area.style.position = 'fixed';
	area.style.top = '-1000px';
	area.style.opacity = '0';
	document.body.appendChild(area);

	let copied = false;
	try {
		area.select();
		copied = document.execCommand('copy');
	} catch (e) {
		copied = false;
	}

	document.body.removeChild(area);

	return copied;
}

/**
 * Put `text` on the clipboard, resolving to whether it landed there.
 *
 * navigator.clipboard exists only in a secure context, and plenty of WordPress
 * admins are still served over plain http, so the legacy route is the one that
 * actually runs on those sites rather than a museum piece. It is also the
 * fallback when writeText() rejects, which a secure context does not rule out:
 * an embedded webview or a hardened browser profile can deny clipboard-write
 * outright, and reporting failure while a working path is still untried would
 * leave the button dead on exactly those setups.
 */
function tdCopyToClipboard(text) {
	if (navigator.clipboard && window.isSecureContext) {
		return navigator.clipboard.writeText(text).then(
			() => true,
			() => tdLegacyCopy(text)
		);
	}

	return Promise.resolve(tdLegacyCopy(text));
}

/**
 * Set on the setup screen, read once on the page it redirects to.
 */
const TD_CONNECTED_FLAG = 'td_setup_just_completed';

/**
 * A short confetti burst over the viewport.
 *
 * Hand-rolled rather than pulled from npm. This is decoration on a single
 * screen, and a dependency for it would be supply-chain surface the plugin
 * does not otherwise carry - which is the wrong trade for something nobody
 * sees twice.
 *
 * Two cannons fire inward from the lower corners so the streams cross over the
 * middle of the screen, rather than raining down over the content the user has
 * just arrived to read. Nothing runs at all under prefers-reduced-motion.
 */
function tdConfetti() {
	if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		return;
	}

	const canvas = document.createElement('canvas');
	canvas.setAttribute('aria-hidden', 'true');
	canvas.style.cssText =
		'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:100000';
	document.body.appendChild(canvas);

	const ctx = canvas.getContext('2d');
	// Capped at 2: past that the extra pixels cost more than they show.
	const dpr = Math.min(window.devicePixelRatio || 1, 2);
	let w = 0;
	let h = 0;

	const size = () => {
		w = window.innerWidth;
		h = window.innerHeight;
		canvas.width = Math.floor(w * dpr);
		canvas.height = Math.floor(h * dpr);
		ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
	};
	size();

	const COLORS = ['#3858e9', '#2563eb', '#22c55e', '#f59e0b', '#ec4899', '#8b5cf6'];
	const particles = [];

	const cannon = (originX, angle) => {
		for (let i = 0; i < 70; i++) {
			const spread = (Math.random() - 0.5) * 0.9;
			const speed = 14 + Math.random() * 16;

			particles.push({
				x: originX,
				y: h + 8,
				vx: Math.cos(angle + spread) * speed,
				vy: Math.sin(angle + spread) * speed,
				w: 6 + Math.random() * 6,
				h: 4 + Math.random() * 5,
				color: COLORS[Math.floor(Math.random() * COLORS.length)],
				rot: Math.random() * Math.PI,
				spin: (Math.random() - 0.5) * 0.3,
				wobble: Math.random() * Math.PI * 2,
				life: 0,
				ttl: 150 + Math.random() * 70,
			});
		}
	};

	cannon(0, -Math.PI / 3);
	cannon(w, (-Math.PI * 2) / 3);

	const GRAVITY = 0.32;
	const DRAG = 0.994;
	let raf = 0;

	const frame = () => {
		ctx.clearRect(0, 0, w, h);
		let alive = 0;

		for (const p of particles) {
			if (p.life > p.ttl) {
				continue;
			}

			alive++;
			p.life++;
			p.vx *= DRAG;
			p.vy = p.vy * DRAG + GRAVITY;
			p.x += p.vx;
			p.y += p.vy;
			p.rot += p.spin;
			p.wobble += 0.1;

			// Anything that has fallen well clear of the viewport is finished,
			// whatever its ttl says - otherwise the last frames animate nothing.
			if (p.vy > 0 && p.y - h > 60) {
				p.life = p.ttl + 1;
				continue;
			}

			ctx.save();
			ctx.globalAlpha = Math.max(0, Math.min(1, (p.ttl - p.life) / 40));
			ctx.translate(p.x, p.y);
			ctx.rotate(p.rot);
			// Squashing the width on a sine reads as a ribbon turning over,
			// rather than a flat rectangle sliding across the screen.
			ctx.scale(Math.cos(p.wobble), 1);
			ctx.fillStyle = p.color;
			ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
			ctx.restore();
		}

		if (alive === 0) {
			cancelAnimationFrame(raf);
			window.removeEventListener('resize', size);
			canvas.remove();
			return;
		}

		raf = requestAnimationFrame(frame);
	};

	window.addEventListener('resize', size);
	raf = requestAnimationFrame(frame);
}

/**
 * The ThriveDesk settings screen, derived from ajax_url rather than assumed to
 * live under /wp-admin/ - that path is configurable.
 */
function tdSettingsUrl() {
	const ajax = (typeof thrivedesk !== 'undefined' && thrivedesk.ajax_url) || '';

	return ajax.indexOf('admin-ajax.php') !== -1
		? ajax.replace('admin-ajax.php', 'admin.php?page=thrivedesk')
		: '/wp-admin/admin.php?page=thrivedesk';
}

jQuery(document).ready(($) => {
	// plugin connection 
	$('.thrivedesk button.connect').on('click', function (e) {
		e.preventDefault();

		let $target = $(this);

		if (1 == $target.data('connected')) {
			alert(__('Are you sure to disconnect this integration?', 'thrivedesk'));
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
							__('Unable to connect with ThriveDesk. Make sure you are using this plugin on a live site.', 'thrivedesk')
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

	// Arriving from a completed setup. The flag is cleared before anything is
	// shown, so a refresh does not replay the celebration.
	(() => {
		let justConnected = false;

		try {
			justConnected = window.sessionStorage.getItem(TD_CONNECTED_FLAG) === '1';
			window.sessionStorage.removeItem(TD_CONNECTED_FLAG);
		} catch (e) {
			justConnected = false;
		}

		if (!justConnected) {
			return;
		}

		Swal.fire({
			toast: true,
			position: 'top-end',
			icon: 'success',
			title: __('Connected to ThriveDesk', 'thrivedesk'),
			text: __('Your site is set up and ready to take conversations.', 'thrivedesk'),
			showConfirmButton: false,
			timer: 5000,
			timerProgressBar: true,
			// Swal renders outside the plugin's own markup, so it needs its own
			// hook to clear the admin bar and the confetti canvas.
			customClass: { container: 'td-toast' },
			didOpen: (toast) => {
				toast.addEventListener('mouseenter', Swal.stopTimer);
				toast.addEventListener('mouseleave', Swal.resumeTimer);
			},
		});

		tdConfetti();
	})();

	// The setup screen's reference column: a 40px rail until it is asked for.
	// The rail opens it, the chevron beside the panel heading puts it back.
	$(document).on('click', '.td-aside-toggle, .td-aside-close', function (e) {
		e.preventDefault();

		const $split = $(this).closest('.td-split');
		const open = !$split.hasClass('is-open');

		$split.toggleClass('is-open', open);
		// The panel is display:none when closed, so it leaves the accessibility
		// tree on its own; aria-expanded is what tells a screen reader what the
		// rail will do before it is pressed.
		$split.find('.td-aside-toggle').attr('aria-expanded', open ? 'true' : 'false');
	});

	// Copy-to-clipboard buttons (the ThriveDesk IP list on the setup screen).
	// Delegated from document so the buttons work wherever they are rendered.
	$(document).on('click', '.td-copy', function (e) {
		e.preventDefault();

		const $button = $(this);
		// .attr(), not .data(): jQuery coerces data values, and an IP address is
		// a string even when it looks numeric.
		const value = $button.attr('data-td-copy');

		if (!value) {
			return;
		}

		tdCopyToClipboard(value).then((copied) => {
			const $status = $('#td-copy-status');

			if (!copied) {
				$status.text(__('Copy failed. Select the text and copy it manually.', 'thrivedesk'));
				return;
			}

			// Restart the timer on a repeat click rather than letting the first
			// one clear the tick early.
			clearTimeout($button.data('td-copy-timer'));
			$button.addClass('is-copied');
			// translators: %s: an IP address.
			$status.text(sprintf(__('Copied %s', 'thrivedesk'), value));

			$button.data(
				'td-copy-timer',
				setTimeout(() => {
					$button.removeClass('is-copied');
					$status.text('');
				}, 2000)
			);
		});
	});

	// on click complete setup button to verify API key
	$('#submit-btn').on('click', function (e) {
		e.preventDefault();
		
		// Check if thrivedesk object exists
		if (typeof thrivedesk === 'undefined') {
			console.error('ThriveDesk: Configuration not loaded');
			Swal.fire({
				icon: 'error',
				title: __('Error', 'thrivedesk'),
				text: __('ThriveDesk configuration not loaded. Please refresh the page.', 'thrivedesk'),
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
					title: __('Error', 'thrivedesk'),
					text: __('Invalid response from server', 'thrivedesk'),
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
							title: __('Error', 'thrivedesk'),
							text: __('Invalid response from helpdesk form submission', 'thrivedesk'),
						});
						return;
					}
					
					if (parsedResponse) {
						if (parsedResponse.status === 'success') {
							// Celebrate on the page being navigated to, not the
							// one being left: a burst started here is cut off by
							// the navigation a moment later. A failure still gets
							// a modal - that one has to be read before anything
							// else happens.
							try {
								window.sessionStorage.setItem(TD_CONNECTED_FLAG, '1');
							} catch (e) {
								// Private mode or storage disabled. Only the
								// celebration is lost; the redirect still matters.
							}

							window.location.href = tdSettingsUrl();
							return;
						}

						Swal.fire({
							icon: 'error',
							title: __('Error', 'thrivedesk'),
							text: parsedResponse.message,
						});
					}
				}).fail(function(xhr, status, error) {
					console.error('ThriveDesk: Helpdesk form submission failed:', error);
					Swal.fire({
						icon: 'error',
						title: __('Error', 'thrivedesk'),
						// translators: %s: error message
						text: sprintf(__('Failed to save helpdesk form: %s', 'thrivedesk'), error),
					});
				});
			});
		}).fail(function (error) {
			Swal.fire({
				icon: 'error',
				title: __('Error', 'thrivedesk'),
				text: __('Something went wrong', 'thrivedesk'),
			});
		}).always(function() {
			// Remove loading state
			setTimeout(function() {
				$btn.prop('disabled', false)
					.html(__('Complete Setup', 'thrivedesk'));
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
			.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + __('Processing...', 'thrivedesk'));


		e.preventDefault();
		handleThriveDeskMainForm().then(function (response) {
			let icon;
			if (response.status === 'success') {
				response.status === 'success' ? (icon = 'success') : (icon = 'error');
				Swal.fire({
					icon: icon,
					title: ( response.status === 'success' ? __('Success', 'thrivedesk') : __('Error', 'thrivedesk') ),
					text: response.message,
				});

				// Remove loading state
				setTimeout(function() {
					$btn.prop('disabled', false)
						.html(__('Save', 'thrivedesk'));
				}, 1000);
			}
		}).catch(()=>{
			Swal.fire({
				icon: 'error',
				title: __('Error', 'thrivedesk'),
				text: __('Form submition failed', 'thrivedesk'),
			});
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
				title: __('Error', 'thrivedesk'),
				text: __('API Key is required', 'thrivedesk'),
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

				$target.text(__('Verified', 'thrivedesk'));
				$target.prop('disabled', true);

				// remove the disabled attribute from the id td-assistants and td-inboxes
				$('#td-assistants').prop('disabled', false);
				$('#td-inboxes').prop('disabled', false);
				// add hidden class to the id td-api-verification-btn
				$('#api_key_alert').addClass('hidden');

				Swal.fire({
					icon: 'success',
					title: __('Success', 'thrivedesk'),
					text: data?.message,
				}).then(async (result)=>{
					if (result.isConfirmed) {
						jQuery.post(thrivedesk.ajax_url, {
							action: 'thrivedesk_system_info',
							nonce: thrivedesk.nonce,
							data: {
								td_helpdesk_api_key: apiKey,
							},
						})
							.success(function (response) {
								handleThriveDeskMainForm().then((response) => {
									if (response.status === 'success') {
										setTimeout(() => {
											window.location.href = '/wp-admin/admin.php?page=thrivedesk';
										}, 1000);
									}
								}).catch(() => {
									Swal.fire({
										icon: 'error',
										title: __('Error', 'thrivedesk'),
										text: __('Form submition failed', 'thrivedesk'),
									});
								});
							}).error(function (error) {
							Swal.fire({
								icon: 'error',
								title: __('Error', 'thrivedesk'),
								text: __('Something went wrong', 'thrivedesk'),
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
					title: __('Error', 'thrivedesk'),
					text: __('Something went wrong', 'thrivedesk'),
				});
			});
	});

	function handleFailedResponse(status, parsedResponse) {
		let data = parsedResponse?.data;

		if (status === 'false' || status === 'error') {
			if (parsedResponse?.code === 422) {
				Swal.fire({
					icon: 'error',
					title: __('Error', 'thrivedesk'),
					text: data?.message,
				});

				return false;
			}
			if(data?.message==='Unauthenticated.'){
				Swal.fire({
					icon: 'error',
					title: __('Error', 'thrivedesk'),
					text: __('Invalid API Key', 'thrivedesk'),
				});

				return false;
			}
			else if (data?.message==='Server Error'){
				Swal.fire({
					icon: 'error',
					title: __('Error', 'thrivedesk'),
					text: __('Server Error', 'thrivedesk'),
				});
				return false;
			}
			else {
				Swal.fire({
					icon: 'error',
					title: __('Error', 'thrivedesk'),
					text: data?.message || parsedResponse?.message || __('Something went wrong', 'thrivedesk'),
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
						title: __('Error', 'thrivedesk'),
						text: __('Invalid API Key', 'thrivedesk'),
					});
				}
				else if (data?.message==='Server Error'){
					Swal.fire({
						icon: 'error',
						title: __('Error', 'thrivedesk'),
						text: __('Server Error', 'thrivedesk'),
					});
				} else {

					let assistantList = $('#td-assistants');
					assistantList.html('');

					if (data?.assistants?.length > 0) {
						assistants = data?.assistants;
						assistantList.append(tdOption('', __('Select Assistant', 'thrivedesk')));
						data.assistants.forEach(function (item) {
							assistantList.append(tdOption(item.id, item.name));
						});
					}else {
						assistantList.append(tdOption('', __('No Assistant Found', 'thrivedesk')));

						assistantList.prop('disabled', true);

					}
				}
			})
			.error(function () {
				Swal.fire({
					icon: 'error',
					title: __('Error', 'thrivedesk'),
					text: __('Something went wrong', 'thrivedesk'),
				});
			                    });
    }
    
    // Load inboxes
    async function loadInboxes(apiKey) {
        jQuery
            .post(thrivedesk.ajax_url, {
                action: 'thrivedesk_load_inboxes',
                nonce: thrivedesk.nonce,
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
                        title: __('Error', 'thrivedesk'),
                        text: __('Invalid API Key', 'thrivedesk'),
                    });
                }
                else if (data?.message==='Server Error'){
                    Swal.fire({
                        icon: 'error',
                        title: __('Error', 'thrivedesk'),
                        text: __('Server Error', 'thrivedesk'),
                    });
                } else {
                    let inboxList = $('#td-inboxes');
                    
                    // Get the saved inbox ID from the data attribute (set by PHP)
                    let savedInboxId = inboxList.data('selected') || inboxList.val();
                    
                    inboxList.html('');

                    if (data?.data?.length > 0) {
                        inboxes = data?.data;
                        inboxList.append(tdOption('', __('All inboxes', 'thrivedesk')));
                        data.data.forEach(function (item) {
                            let isSelected = (savedInboxId === item.id);
                            inboxList.append(tdOption(item.id, item.name).prop('selected', isSelected));
                        });

                        // Restore the selected value
                        if (savedInboxId) {
                            inboxList.val(savedInboxId);
                        }
                    }else {
                        inboxList.append(tdOption('', __('No Inbox Found', 'thrivedesk')));

                        inboxList.prop('disabled', true);
                    }
                }
            })
            .error(function (xhr, status, error) {
                let errorMessage = __('Something went wrong', 'thrivedesk');
                if (status === 'timeout') {
                    errorMessage = __('Request timed out. Please try again.', 'thrivedesk');
                } else if (error) {
                    // translators: %s: error message
                    errorMessage = sprintf(__('Error: %s', 'thrivedesk'), error);
                }
                
                Swal.fire({
                    icon: 'error',
                    title: __('Error', 'thrivedesk'),
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
				nonce: thrivedesk.nonce,
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
					title: __('Error', 'thrivedesk'),
					text: __('Something went wrong', 'thrivedesk'),
				});
			});
	}
	// clear cache
	$('#thrivedesk_clear_cache_btn').on('click', function (e) {
		jQuery
			.get(thrivedesk.ajax_url, {
				action: 'thrivedesk_clear_cache',
				nonce: thrivedesk.nonce,
			})
			.success(function (response) {
				Swal.fire({
					icon: 'success',
					title: __('Success', 'thrivedesk'),
					text: __('Cache Cleared', 'thrivedesk'),
				}).then((result) => {
					location.reload();
				});
			})
			.error(function () {
				Swal.fire({
					icon: 'error',
					title: __('Error', 'thrivedesk'),
					text: __('Something went wrong', 'thrivedesk'),
				});
			});
	});
});

