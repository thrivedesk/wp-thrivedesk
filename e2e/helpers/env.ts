import path from 'path';

export const ADMIN_STATE = path.join(__dirname, '..', '.auth', 'admin.json');
export const CUSTOMER_STATE = path.join(__dirname, '..', '.auth', 'customer.json');

/**
 * Every credential and URL comes from the environment so the suite can point at
 * any WordPress running the plugin. Missing values throw at config load instead
 * of surfacing as a login that silently redirects back to wp-login.php.
 */
export function requireEnv(name: string): string {
	const value = process.env[name];

	if (!value) {
		throw new Error(`${name} is not set — see e2e/README.md for the variables the suite needs`);
	}

	return value;
}

export const ADMIN_USER = () => requireEnv('WP_ADMIN_USER');
export const ADMIN_PASSWORD = () => requireEnv('WP_ADMIN_PASSWORD');
export const CUSTOMER_USER = () => requireEnv('WP_CUSTOMER_USER');
export const CUSTOMER_PASSWORD = () => requireEnv('WP_CUSTOMER_PASSWORD');

/** Path of a published page carrying the [thrivedesk_portal] shortcode. */
export const PORTAL_PATH = () => process.env['WP_PORTAL_PATH'] ?? '/thrivedesk-support-portal/';

/**
 * The portal page is reached by a pretty permalink on some sites and by
 * `/?page_id=N` on others, so query parameters have to be joined with whichever
 * separator the configured path leaves free.
 */
export function portalUrl(query = ''): string {
	const path = PORTAL_PATH();

	if (!query) {
		return path;
	}

	return `${path}${path.includes('?') ? '&' : '?'}${query}`;
}
