import { expect, Locator, Page, test } from '@playwright/test';

import { gotoSettings } from './helpers/wp';

interface ConnectPayload {
	store_url: string;
	api_token: string;
	org_id: string;
	cancel_url: string;
	success_url: string;
}

/**
 * Returns the integration's button with the integration disconnected, whatever
 * state it was in.
 *
 * The same button is Connect or Disconnect depending on stored state, and each
 * posts a different action — so a spec that assumes one of them silently drives
 * the other. Seeded sites can arrive either way (the WooCommerce demo seed marks
 * it connected), and connecting is what these specs are about, so normalise here
 * rather than depending on how the site was left.
 */
async function disconnected(page: Page, plugin: string): Promise<Locator> {
	await gotoSettings(page);

	const button = page.locator(`button.connect[data-plugin="${plugin}"]`);
	await expect(button).toBeVisible();
	await expect(button).toBeEnabled();

	if ((await button.getAttribute('data-connected')) === '1') {
		// The disconnect path confirms through a native alert, then reloads.
		page.once('dialog', (dialog) => dialog.accept());
		await Promise.all([
			page.waitForResponse(
				(candidate) =>
					candidate.url().includes('admin-ajax.php') &&
					(candidate.request().postData() ?? '').includes('action=thrivedesk_disconnect_plugin'),
			),
			button.click(),
		]);

		await gotoSettings(page);
		await expect(button).toHaveAttribute('data-connected', '0');
	}

	return button;
}

/**
 * Clicks Connect and decodes the payload the plugin hands back.
 *
 * Read from the ajax response, which IS the redirect URL the plugin built, not
 * from the navigation that follows it. The navigation leaves for the ThriveDesk
 * app — a single-page app that may not even be served wherever this suite is
 * pointed, and which routes away and drops the query when it is. What belongs to
 * WordPress is the URL it produced, and that is what this asserts.
 */
async function connectPayload(page: Page, plugin: string): Promise<ConnectPayload> {
	const button = await disconnected(page, plugin);

	const [response] = await Promise.all([
		page.waitForResponse(
			(candidate) =>
				candidate.url().includes('admin-ajax.php') &&
				(candidate.request().postData() ?? '').includes('action=thrivedesk_connect_plugin'),
		),
		button.click(),
	]);

	expect(response.status(), 'the connect action did not return 200').toBe(200);

	// Read the body before anything navigates: the response resource belongs to
	// the current document and is discarded the moment one is attempted.
	const redirect = (await response.text()).trim();

	// Then wait for the hand-off to be attempted (and refused, see beforeEach)
	// before handing control back. A navigation still pending here would abort
	// whatever this page is asked to do next.
	await page.waitForRequest((candidate) => candidate.url().includes(`/apps/${plugin}?connect=`));

	const connect = new URL(redirect).searchParams.get('connect') ?? '';
	expect(connect, `no connect param in the redirect the plugin built: ${redirect}`).not.toBe('');

	return JSON.parse(Buffer.from(connect, 'base64').toString('utf8')) as ConnectPayload;
}

test.beforeEach(async ({ page }) => {
	// Three quarters of a second after the ajax call the plugin sends the browser
	// to ThriveDesk. Refuse the hand-off: the contract under test is the URL
	// WordPress built, and following it would depend on an app that isn't
	// necessarily served wherever this suite runs. An aborted navigation leaves
	// the settings screen exactly where it is.
	await page.route('**/apps/**', (route) => route.abort());
});

/**
 * Connecting an integration hands ThriveDesk a base64 payload in the URL and
 * lets the app take it from there. Both sides parse that payload, so its shape
 * is a contract — this asserts the WordPress half of it.
 */
test('hands ThriveDesk a connect payload for WooCommerce', async ({ page, baseURL }) => {
	const payload = await connectPayload(page, 'woocommerce');

	// get_bloginfo('url') — the site's own address, which is what ThriveDesk
	// stores as the store it calls back into.
	expect(payload.store_url).toBe(baseURL?.replace(/\/$/, ''));
	expect(payload.org_id).not.toBe('');

	// 32 random bytes, hex-encoded. This token is the HMAC key for the inbound
	// listener, so anything shorter or guessable is a security regression.
	expect(payload.api_token).toMatch(/^[0-9a-f]{64}$/);

	expect(payload.success_url).toContain('td-activated=true');
	expect(payload.cancel_url).toContain('td-activated=false');
});

test('issues a fresh token on every connect attempt', async ({ page }) => {
	const first = await connectPayload(page, 'woocommerce');
	const second = await connectPayload(page, 'woocommerce');

	expect(first.api_token).not.toBe(second.api_token);
});
