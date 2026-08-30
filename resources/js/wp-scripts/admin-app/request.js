import { __ } from '@wordpress/i18n';

/**
 * POST to one of the plugin's admin-ajax handlers.
 *
 * Not apiFetch: these are `wp_ajax_*` handlers, not REST routes, and they
 * answer with a bare string rather than a JSON envelope. The body shape has to
 * match what the handlers read - `data[plugin]`, `data[nonce]` - because that
 * is the nested form jQuery.post produced and the PHP side indexes it directly.
 *
 * The nonce is added here so no caller can forget it: every one of these
 * handlers rejects without it.
 */
export async function postAction( action, data = {} ) {
	const config = window.thrivedeskAdmin || {};

	const body = new URLSearchParams();
	body.append( 'action', action );

	Object.entries( { ...data, nonce: config.pluginActionNonce } ).forEach( ( [ key, value ] ) => {
		body.append( `data[${ key }]`, value ?? '' );
	} );

	const response = await window.fetch( config.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
		body: body.toString(),
	} );

	const text = await response.text();

	if ( ! response.ok ) {
		// The failure path does answer with JSON - wp_send_json_error() - so
		// prefer its message where there is one.
		let message = __( 'Something went wrong. Please try again.', 'thrivedesk' );

		try {
			message = JSON.parse( text )?.data?.message || message;
		} catch ( e ) {
			// A non-JSON error body tells us nothing useful; keep the default.
		}

		throw new Error( message );
	}

	return text;
}
