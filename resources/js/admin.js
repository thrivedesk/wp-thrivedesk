import Swal from 'sweetalert2';
import { __, _n, sprintf } from '@wordpress/i18n';
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
const TD_DISCONNECTED_FLAG = 'td_setup_just_disconnected';

/**
 * Read a one-shot flag left for the next page load, and clear it.
 *
 * Cleared before the caller acts on it, so a refresh does not replay whatever
 * it announces. Storage can be unavailable - private mode, or a browser set to
 * block it - and a missing celebration is not worth an exception.
 */
function tdTakeFlag(name) {
	try {
		const found = window.sessionStorage.getItem(name) === '1';
		window.sessionStorage.removeItem(name);

		return found;
	} catch (e) {
		return false;
	}
}

function tdSetFlag(name) {
	try {
		window.sessionStorage.setItem(name, '1');
	} catch (e) {
		// Nothing to do about it, and nothing that matters is lost.
	}
}

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

/**
 * How long the workspace card is allowed to glow, in milliseconds.
 *
 * Kept in step with the .is-celebrating rule in admin.css - the animation is
 * declared there and the page is reloaded here, and the two disagreeing would
 * either cut the glow off or leave the page sitting on stale panels.
 */
const TD_CELEBRATE_MS = 3000;

/**
 * Bring `root`'s contents in a line at a time.
 *
 * Marked up server-side with data-td-reveal so the order is the reading order
 * rather than whatever the DOM happens to hand back. The delay is set here and
 * not in CSS because the number of rows depends on the plan - an :nth-child
 * ladder would need a rule for every row the card might ever have.
 */
function tdRevealIn(root) {
	if (!root || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
		return;
	}

	root.querySelectorAll('[data-td-reveal]').forEach((el, i) => {
		el.style.animationDelay = i * 70 + 'ms';
		el.classList.add('td-reveal');
	});
}

/**
 * Turn a `<select multiple>` into a dropdown of checkboxes.
 *
 * Progressive enhancement, not replacement: the original select stays in the
 * DOM and stays the source of truth. Ticking a box sets `option.selected` on
 * it, so `$(el).val()` keeps returning an array and the save handler never
 * learns that anything changed. If this function never runs - a script error,
 * a blocked bundle - what is left on the page is a working list box.
 *
 * A native `<select multiple>` cannot render as a dropdown, and asking people
 * to ctrl-click rows in a scrolling box is the part of this screen that most
 * needed replacing.
 */
function tdEnhanceMultiselect( container ) {
	const select = container.querySelector( '.td-multiselect__source' );

	if ( ! select || container.classList.contains( 'is-enhanced' ) ) {
		return;
	}

	const options = Array.from( select.options );

	const toggle = document.createElement( 'button' );
	toggle.type = 'button';
	toggle.className = 'td-multiselect__toggle';
	toggle.id = select.id + '-toggle';
	toggle.setAttribute( 'aria-expanded', 'false' );

	const value = document.createElement( 'span' );
	value.className = 'td-multiselect__value';

	const caret = document.createElement( 'span' );
	caret.className = 'td-multiselect__caret';
	caret.setAttribute( 'aria-hidden', 'true' );
	caret.innerHTML =
		'<svg viewBox="0 0 24 24" width="16" height="16" fill="none"><path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

	toggle.append( value, caret );

	const panel = document.createElement( 'div' );
	panel.className = 'td-multiselect__panel';
	panel.hidden = true;

	const search = document.createElement( 'input' );
	search.type = 'search';
	search.className = 'td-multiselect__search';
	search.placeholder = __( 'Search pages', 'thrivedesk' );
	panel.appendChild( search );

	const list = document.createElement( 'ul' );
	list.className = 'td-multiselect__list';

	const empty = document.createElement( 'div' );
	empty.className = 'td-multiselect__empty';
	empty.textContent = __( 'Nothing matches that.', 'thrivedesk' );
	empty.hidden = true;

	const rows = options.map( ( option ) => {
		const row = document.createElement( 'li' );

		const label = document.createElement( 'label' );
		label.className = 'td-multiselect__option';

		const box = document.createElement( 'input' );
		box.type = 'checkbox';
		box.checked = option.selected;

		const text = document.createElement( 'span' );
		text.textContent = option.text.trim();

		// The path, when the markup carries one, so two pages sharing a title
		// are still tellable apart.
		const path = option.getAttribute( 'data-td-path' ) || '';
		let hint = null;

		if ( path ) {
			hint = document.createElement( 'span' );
			hint.className = 'td-multiselect__path';
			hint.textContent = path;
		}

		box.addEventListener( 'change', () => {
			option.selected = box.checked;
			// Anything else watching the select - now or later - should hear
			// about this the same way it would hear about a real interaction.
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			render();
		} );

		label.append( box, text );

		if ( hint ) {
			label.appendChild( hint );
		}

		row.appendChild( label );
		list.appendChild( row );

		return { row, box, option, path };
	} );

	panel.append( list, empty );

	const footer = document.createElement( 'div' );
	footer.className = 'td-multiselect__footer';

	const count = document.createElement( 'span' );
	count.className = 'text-[12px] text-gray-400';

	const clear = document.createElement( 'button' );
	clear.type = 'button';
	clear.className = 'td-multiselect__clear';
	clear.textContent = __( 'Clear', 'thrivedesk' );
	clear.addEventListener( 'click', () => {
		rows.forEach( ( { box, option } ) => {
			box.checked = false;
			option.selected = false;
		} );
		select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		render();
	} );

	footer.append( count, clear );
	panel.appendChild( footer );

	function render() {
		const chosen = rows.filter( ( { option } ) => option.selected );

		if ( ! chosen.length ) {
			value.textContent = __( 'Shown on every page', 'thrivedesk' );
		} else if ( 1 === chosen.length ) {
			value.textContent = chosen[ 0 ].option.text.trim();
		} else {
			value.textContent = sprintf(
				/* translators: %d: how many pages the widget is hidden on. */
				_n( '%d page hidden', '%d pages hidden', chosen.length, 'thrivedesk' ),
				chosen.length
			);
		}

		count.textContent = sprintf(
			/* translators: %1$d: chosen count, %2$d: total available. */
			__( '%1$d of %2$d selected', 'thrivedesk' ),
			chosen.length,
			rows.length
		);

		clear.disabled = ! chosen.length;
	}

	function open( isOpen ) {
		panel.hidden = ! isOpen;
		toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );

		if ( isOpen ) {
			search.focus();
		}
	}

	toggle.addEventListener( 'click', () => open( panel.hidden ) );

	search.addEventListener( 'input', () => {
		const needle = search.value.trim().toLowerCase();
		let shown = 0;

		rows.forEach( ( { row, option, path } ) => {
			// Searches the path too: people look for /cart/ as readily as "Cart".
			const haystack = ( option.text + ' ' + path ).toLowerCase();
			const match = ! needle || haystack.includes( needle );
			row.hidden = ! match;
			shown += match ? 1 : 0;
		} );

		empty.hidden = shown > 0;
	} );

	// Escape closes and returns focus to the control that opened it, rather
	// than leaving the caret somewhere inside a panel that is now gone.
	container.addEventListener( 'keydown', ( e ) => {
		if ( 'Escape' === e.key && ! panel.hidden ) {
			open( false );
			toggle.focus();
		}
	} );

	document.addEventListener( 'click', ( e ) => {
		if ( ! panel.hidden && ! container.contains( e.target ) ) {
			open( false );
		}
	} );

	container.append( toggle, panel );
	container.classList.add( 'is-enhanced' );

	// The label points at the select, which is hidden now; send it to the
	// control that actually takes the click.
	const label = document.querySelector( 'label[for="' + select.id + '"]' );
	if ( label ) {
		label.setAttribute( 'for', toggle.id );
	}

	render();
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

	// Arriving from a disconnect. Says what happened, because the screen
	// changing back to "add your API key" reads the same as never having
	// connected at all.
	if (tdTakeFlag(TD_DISCONNECTED_FLAG)) {
		Swal.fire({
			toast: true,
			position: 'top-end',
			icon: 'success',
			title: __('Disconnected from ThriveDesk', 'thrivedesk'),
			text: __('Your settings for this site were kept. Add a key to connect again.', 'thrivedesk'),
			showConfirmButton: false,
			timer: 6000,
			timerProgressBar: true,
			customClass: { container: 'td-toast' },
		});
	}

	/*
	 * Arriving from a completed setup.
	 *
	 * On the load after connecting rather than in place, because the workspace
	 * card does not exist on a screen with no account - nor do the Live Chat
	 * and Portal panels, nor the unlocked integrations, and all of it is filled
	 * server-side. The flag is what carries the celebration across that load.
	 */
	(() => {
		if (!tdTakeFlag(TD_CONNECTED_FLAG)) {
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

		// The workspace card is the one thing on the page that was blank a
		// moment ago and is not now, so it is what fills in and what glows.
		const card = document.getElementById('td-workspace-card');

		if (card) {
			card.classList.add('is-celebrating');
			card.scrollIntoView({ block: 'center', behavior: 'smooth' });
			tdRevealIn(document.getElementById('td-workspace-card-body'));
		}
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

	/*
	 * The empty Live Chat and Portal tabs point at the connect card, which is
	 * on another tab. The React app owns which tab is showing, so this asks
	 * rather than reaches in - see SELECT_TAB_EVENT in admin-app/App.js.
	 */
	$(document).on('click', '[data-td-goto-tab]', function (e) {
		e.preventDefault();

		const name = this.getAttribute('data-td-goto-tab');

		if (document.querySelector('.td-tabs')) {
			document.dispatchEvent(new CustomEvent('thrivedesk:select-tab', { detail: name }));
			window.scrollTo({ top: 0, behavior: 'smooth' });
			return;
		}

		// No app on the page - it failed to boot, or scripts are off. The URL
		// selects the tab on its own; see initialTab().
		const url = new URL(window.location.href);
		url.searchParams.set('td_tab', name);
		window.location.href = url.toString();
	});

	/*
	 * Disconnecting cannot be undone from this screen - the key has to be typed
	 * back in - and one of its effects is invisible from here, so the
	 * consequences are written out before it happens rather than reported
	 * after. The revoked integrations are the part nobody expects.
	 */
	$(document).on('click', '#td-disconnect-account', async function (e) {
		e.preventDefault();

		const answer = await Swal.fire({
			icon: 'warning',
			title: __('Disconnect this site?', 'thrivedesk'),
			html:
				'<ul style="text-align:left;margin:0;padding-left:1.1em;line-height:1.7">' +
				'<li>' + __('This site stops sending conversations to ThriveDesk.', 'thrivedesk') + '</li>' +
				'<li>' + __('The chat widget stops appearing on your site.', 'thrivedesk') + '</li>' +
				'<li>' + __('Connected integrations are revoked and have to be reconnected.', 'thrivedesk') + '</li>' +
				'<li>' + __('Nothing is deleted in your ThriveDesk account.', 'thrivedesk') + '</li>' +
				'</ul>',
			showCancelButton: true,
			// The safe answer is the one under the cursor and the one Enter
			// takes, because this dialog is destructive by default.
			focusCancel: true,
			reverseButtons: true,
			confirmButtonText: __('Disconnect', 'thrivedesk'),
			cancelButtonText: __('Cancel', 'thrivedesk'),
			confirmButtonColor: '#e11d48',
		});

		if (!answer.isConfirmed) {
			return;
		}

		const $btn = $(this);
		$btn.prop('disabled', true);

		try {
			await jQuery.post(thrivedesk.ajax_url, {
				action: 'thrivedesk_disconnect_account',
				data: { nonce: (window.thrivedeskAdmin || {}).pluginActionNonce || '' },
			});

			tdSetFlag(TD_DISCONNECTED_FLAG);
			window.location.reload();
		} catch (error) {
			$btn.prop('disabled', false);

			Swal.fire({
				icon: 'error',
				title: __('Could not disconnect', 'thrivedesk'),
				text: __('Something went wrong. Reload the page and try again.', 'thrivedesk'),
			});
		}
	});

	/*
	 * The search settings describe what happens on the way to a ticket form, so
	 * they stay shut until one is chosen.
	 *
	 * The server renders the same state, and this keeps it honest as the select
	 * changes - without it the card would stay locked until the page was
	 * reloaded, right after the user did the one thing that unlocks it.
	 *
	 * `disabled`, not just dimmed: an opacity that still takes tab focus and
	 * still saves is a worse lie than no dimming at all. Disabled controls are
	 * still read by the save handler, which goes by id, so nothing already
	 * chosen is lost while the gate is shut.
	 */
	function tdSyncSearchGate() {
		const card = document.getElementById('td-search-card');

		if (!card) {
			return;
		}

		const page = document.getElementById('td_helpdesk_page_id');
		const locked = !page || '' === page.value;
		const hint = card.querySelector('.td-gated__hint');

		card.classList.toggle('is-locked', locked);

		// The toggle too: admin.js builds a dropdown over the multiple
		// <select>, and a live toggle over a disabled select would let someone
		// tick boxes that go nowhere.
		card.querySelectorAll('select, input, .td-multiselect__toggle').forEach((el) => {
			el.disabled = locked;
		});

		if (hint) {
			hint.hidden = !locked;
		}
	}

	$(document).on('change', '#td_helpdesk_page_id', tdSyncSearchGate);
	tdSyncSearchGate();

	document.querySelectorAll( '[data-td-multiselect]' ).forEach( tdEnhanceMultiselect );

	// Video posters. The iframe is created when the dialog opens and its src is
	// cleared when it closes - an embed left in the DOM keeps playing behind a
	// closed dialog, and clearing the src is what actually stops it.
	$( document ).on( 'click', '[data-td-video]', function () {
		const src = $( this ).attr( 'data-td-video' );
		const title = $( this ).attr( 'data-td-video-title' ) || '';

		if ( ! src ) {
			return;
		}

		const dialog = document.createElement( 'dialog' );
		dialog.className = 'td-video-modal';
		dialog.setAttribute( 'aria-label', title );

		const frame = document.createElement( 'iframe' );
		frame.className = 'td-video-modal__frame';
		frame.setAttribute( 'allow', 'autoplay; fullscreen; picture-in-picture' );
		frame.setAttribute( 'allowfullscreen', 'true' );
		frame.src = src;

		const close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'td-video-modal__close';
		close.setAttribute( 'aria-label', __( 'Close video', 'thrivedesk' ) );
		close.innerHTML =
			'<svg viewBox="0 0 24 24" width="18" height="18" fill="none"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>';
		/*
		 * Cleanup is called from every dismissal path, not just the `close`
		 * event. That event is documented to fire and does not always - measured
		 * on the bench, close() left the dialog and its loaded embed in the DOM -
		 * and an iframe that is still there is an iframe that may still be
		 * playing. Idempotent, so being called twice costs nothing.
		 */
		const dismiss = () => {
			frame.src = '';
			dialog.remove();
		};

		close.addEventListener( 'click', () => {
			dialog.close();
			dismiss();
		} );

		dialog.append( frame, close );
		document.body.appendChild( dialog );

		// A click on the backdrop lands on the dialog itself, never on its
		// children, which is what tells the two apart.
		dialog.addEventListener( 'click', ( e ) => {
			if ( e.target === dialog ) {
				dialog.close();
				dismiss();
			}
		} );

		// `cancel` is the Escape key, and is the only hook for it - nothing of
		// ours runs on that path otherwise.
		dialog.addEventListener( 'cancel', dismiss );
		dialog.addEventListener( 'close', dismiss );

		if ( typeof dialog.showModal === 'function' ) {
			dialog.showModal();
		} else {
			// No <dialog> support: send them to the video rather than opening a
			// box that cannot be dismissed.
			dialog.remove();
			window.open( src, '_blank', 'noopener' );
		}
	} );

	/*
	 * Live preview of the selected assistant.
	 *
	 * A frame per assistant, rebuilt on change. This is not a styling choice: the
	 * bundle calls customElements.define('thrivedesk-assistant'), a name can only
	 * be registered once per window, and there is no way to withdraw it. Mounted
	 * on this page the first assistant works and every switch after it throws
	 * NotSupportedError. A frame is a fresh window, so switching is immediate.
	 *
	 * What goes inside is the scaffolding the front end emits, unchanged - the
	 * same queue shim, the same bootloader, the same init and identify calls. No
	 * styling is applied to the widget.
	 */
	let tdPreviewId = null;

	function tdRenderAssistantPreview() {
		const host = document.querySelector( '[data-td-assistant-preview]' );
		const select = document.getElementById( 'td-assistants' );

		if ( ! host || ! select ) {
			return;
		}

		const assistantId = select.value;

		if ( assistantId === tdPreviewId ) {
			return;
		}

		tdPreviewId = assistantId;

		const empty = host.querySelector( '.td-assistant-preview__empty' );
		const existing = host.querySelector( 'iframe' );

		if ( existing ) {
			existing.remove();
		}

		if ( ! assistantId ) {
			if ( empty ) {
				empty.hidden = false;
			}

			return;
		}

		if ( empty ) {
			empty.hidden = true;
		}

		const frame = document.createElement( 'iframe' );
		frame.setAttribute( 'title', __( 'Assistant preview', 'thrivedesk' ) );

		// JSON.stringify does the escaping. These are a WordPress display name
		// and email, but they are still being written into a script.
		frame.srcdoc = [
			'<!doctype html><html><head><meta charset="utf-8">',
			// Only the default body margin, so the widget sits where it would on
			// a real page rather than being inset by 8px. Nothing else.
			'<style>html,body{margin:0;background:transparent}</style>',
			'</head><body><script>',
			'!function(t,e,n){function s(){var t=e.getElementsByTagName("script")[0],n=e.createElement("script");',
			'n.type="text/javascript",n.async=!0,n.src=' + JSON.stringify( host.dataset.bootloader ) + '+"?"+Date.now(),',
			't.parentNode.insertBefore(n,t)}if(t.Assistant=n=function(e,n,s){t.Assistant.readyQueue.push({method:e,options:n,data:s})},',
			'n.readyQueue=[],"complete"===e.readyState)return s();',
			't.attachEvent?t.attachEvent("onload",s):t.addEventListener("load",s,!1)}',
			'(window,document,window.Assistant||function(){});',
			'window.Assistant("init",' + JSON.stringify( assistantId ) + ');',
			'window.Assistant("identify",{name:' + JSON.stringify( host.dataset.name || '' ) + ',email:' + JSON.stringify( host.dataset.email || '' ) + '});',
			'<\/script></body></html>',
		].join( '' );

		host.appendChild( frame );
	}

	$( document ).on( 'change', '#td-assistants', tdRenderAssistantPreview );

	// The select is populated asynchronously, so the first paint has to wait for
	// whatever put the options there to finish.
	tdRenderAssistantPreview();
	setTimeout( tdRenderAssistantPreview, 1500 );

	// Copy-to-clipboard buttons (the ThriveDesk IP list on the setup screen).
	// Delegated from document so the buttons work wherever they are rendered.
	/*
	 * Walk the candidate icon URLs until one loads.
	 *
	 * wordpress.org does not agree with itself about the extension - Contact
	 * Form 7 is a png, Forminator a gif, Everest Forms has no 256 at all - and
	 * there is no way to know which exists without asking, which would mean an
	 * HTTP request while the page renders. So the server offers every shape it
	 * allows and the browser finds out for free. When the list runs out the
	 * image goes and the lettermark behind it shows.
	 *
	 * A capturing listener rather than a delegated jQuery one: `error` does not
	 * bubble, so a delegated handler would never see it.
	 */
	document.addEventListener(
		'error',
		(event) => {
			const img = event.target;

			if (!(img instanceof HTMLImageElement) || !img.classList.contains('td-plugin__icon')) {
				return;
			}

			let remaining = [];

			try {
				remaining = JSON.parse(img.getAttribute('data-td-icons') || '[]');
			} catch (e) {
				remaining = [];
			}

			const next = Array.isArray(remaining) ? remaining.shift() : undefined;

			if (!next) {
				img.remove();
				return;
			}

			img.setAttribute('data-td-icons', JSON.stringify(remaining));
			img.src = next;
		},
		true
	);

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
							// Celebrate on the page that comes back, not this
							// one: a burst started here is cut off by the load
							// a moment later, and everything worth showing off
							// is rendered server-side for a connected site.
							tdSetFlag(TD_CONNECTED_FLAG);

							// Already on the tabbed screen - reload it rather
							// than navigating, which keeps the tab in the URL.
							if (document.getElementById('td-admin-app')) {
								window.location.reload();
								return;
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
		// A multiple <select>, like the excluded routes above: .val() is already
		// the array this wants, so there is nothing to map.
		let td_helpdesk_post_types = $('#td_helpdesk_post_types').val() || [];

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
	/*
	 * Settings persist as they are changed. There is no Save button any more, so
	 * everything below exists to make that trustworthy rather than merely
	 * automatic.
	 *
	 * The API key is deliberately not in this list. It has its own verify step,
	 * and persisting it on every keystroke would disconnect the site halfway
	 * through typing a new one.
	 */
	/*
	 * The fields that decide what the portal serves. Changing any of them makes
	 * whatever is cached for it wrong - the ticket page, the searched content,
	 * the account pages - so the cache is dropped as part of the save rather
	 * than left for someone to notice and clear by hand.
	 *
	 * The Live Chat fields are deliberately absent: the widget is not served
	 * from this cache, and clearing it on every assistant change would be a
	 * round trip for nothing.
	 */
	const TD_PORTAL_FIELDS = [
		'#td-inboxes',
		'#td_helpdesk_page_id',
		'#td_knowledgebase_slug',
		'#td_helpdesk_post_types',
		'.td_user_account_pages',
		'.td_helpdesk_post_sync',
	].join( ',' );

	const TD_AUTOSAVE_FIELDS = [ '#td-assistants', '#td-excluded-routes', TD_PORTAL_FIELDS ].join( ',' );

	let tdSaveTimer = null;
	let tdSaving = false;
	let tdChangedWhileSaving = false;
	let tdPortalChanged = false;

	/**
	 * Drop the cached portal.
	 *
	 * Resolves either way. A save that worked followed by a cache that did not
	 * clear is worth a line in the console, not an error thrown at someone who
	 * has just changed a setting and been told it saved.
	 */
	function tdClearPortalCache() {
		return jQuery
			.get( thrivedesk.ajax_url, {
				action: 'thrivedesk_clear_cache',
				nonce: thrivedesk.nonce,
			} )
			.then(
				() => true,
				() => {
					// eslint-disable-next-line no-console
					console.warn( 'ThriveDesk: the portal cache could not be cleared.' );

					return false;
				}
			);
	}

	function tdSaveToast( options ) {
		return Swal.fire(
			Object.assign(
				{
					toast: true,
					position: 'bottom-end',
					showConfirmButton: false,
					customClass: { container: 'td-toast' },
				},
				options
			)
		);
	}

	function tdSaveNow() {
		// One request at a time. A change that lands mid-flight is remembered and
		// saved after, so the last thing the user did is always what ends up
		// stored - two overlapping requests could land in either order.
		if ( tdSaving ) {
			tdChangedWhileSaving = true;
			return;
		}

		tdSaving = true;

		// Only announce saving if it is slow enough to be worth announcing.
		// Flashing "Saving..." for 200ms reads as a glitch.
		const pending = setTimeout( () => {
			tdSaveToast( { title: __( 'Saving...', 'thrivedesk' ), timer: undefined, showConfirmButton: false } );
		}, 400 );

		handleThriveDeskMainForm()
			.then( ( response ) => {
				if ( response && 'success' === response.status ) {
					// Cleared after the save, not before: clearing first would
					// refill the cache from the settings that are about to
					// change.
					if ( ! tdPortalChanged ) {
						tdSaveToast( { icon: 'success', title: __( 'Changes saved', 'thrivedesk' ), timer: 2000 } );
						return;
					}

					tdPortalChanged = false;

					return tdClearPortalCache().then( ( cleared ) => {
						tdSaveToast( {
							icon: 'success',
							title: __( 'Changes saved', 'thrivedesk' ),
							text: cleared
								? __( 'Portal cache cleared.', 'thrivedesk' )
								: __( 'The portal cache could not be cleared.', 'thrivedesk' ),
							timer: 2500,
						} );
					} );
				}

				// The old Save button showed nothing at all on a non-success
				// response and stayed disabled forever. Silence is worse without a
				// button, not better: nothing was clicked, so nothing else would
				// tell the user their change did not stick.
				tdSaveToast( {
					icon: 'error',
					title: __( 'Could not save', 'thrivedesk' ),
					text: response && response.message ? response.message : undefined,
					timer: 6000,
				} );
			} )
			.catch( () => {
				tdSaveToast( {
					icon: 'error',
					title: __( 'Could not save', 'thrivedesk' ),
					text: __( 'Check your connection and try again.', 'thrivedesk' ),
					timer: 6000,
				} );
			} )
			.then( () => {
				clearTimeout( pending );
				tdSaving = false;

				if ( tdChangedWhileSaving ) {
					tdChangedWhileSaving = false;
					tdSaveNow();
				}
			} );
	}

	$( document ).on( 'change', TD_AUTOSAVE_FIELDS, function () {
		// Remembered rather than acted on: the save is debounced, so this has to
		// survive until whichever save eventually runs.
		if ( $( this ).is( TD_PORTAL_FIELDS ) ) {
			tdPortalChanged = true;
		}

		// Debounced: ticking four post types is one save, not four.
		clearTimeout( tdSaveTimer );
		tdSaveTimer = setTimeout( tdSaveNow, 700 );
	} );

	// No submit button remains, but Enter in a field still submits a form.
	// Catch it so that reloads the page for nobody.
	$( '#td_helpdesk_form' ).on( 'submit', function ( e ) {
		e.preventDefault();
		clearTimeout( tdSaveTimer );
		tdSaveNow();
	} );

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
										// Not a hardcoded /wp-admin/: that path is
										// configurable, and this ran on a screen
										// that is already at the right URL.
										setTimeout(() => {
											window.location.href = tdSettingsUrl();
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

	// Swap the fact for the form, and back.
	$(document).on('click', '.api-key-preview .trigger', function (e) {
		e.preventDefault();

		$('.api-key-preview').addClass('hidden');
		$('.api-key-editable').removeClass('hidden');
		$('#td_helpdesk_api_key').trigger('focus');
	});

	// Opening the form used to be one-way until the page was reloaded. The
	// field is cleared on the way out so a half-typed key is not still sitting
	// there, ready to be posted, the next time it is opened.
	$(document).on('click', '.api-key-cancel', function (e) {
		e.preventDefault();

		$('#td_helpdesk_api_key').val('');
		$('.api-key-editable').addClass('hidden');
		$('.api-key-preview').removeClass('hidden');
	});
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
	/*
	 * The same toast the saves use, rather than a modal with an OK button and a
	 * page reload behind it. Clearing a cache changes nothing on this screen, so
	 * there was nothing for the reload to show and nothing to acknowledge.
	 */
	$(document).on('click', '#thrivedesk_clear_cache_btn', function (e) {
		e.preventDefault();

		const $btn = $(this).prop('disabled', true);

		tdClearPortalCache()
			.then((cleared) => {
				tdSaveToast(
					cleared
						? { icon: 'success', title: __('Portal cache cleared', 'thrivedesk'), timer: 2000 }
						: {
								icon: 'error',
								title: __('Could not clear the cache', 'thrivedesk'),
								text: __('Check your connection and try again.', 'thrivedesk'),
								timer: 6000,
						  }
				);
			})
			.then(() => $btn.prop('disabled', false));
	});
});

