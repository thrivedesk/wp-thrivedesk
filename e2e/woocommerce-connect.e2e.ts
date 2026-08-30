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
	await gotoSettings(page, 'integrations');

	const card = page.locator(`[data-plugin="${plugin}"]`);
	await expect(card).toBeVisible();

	if ((await card.getAttribute('data-connected')) === '1') {
		// Disconnect posts, then reloads the page itself — no confirm dialog.
		await Promise.all([
			page.waitForResponse(
				(candidate) =>
					candidate.url().includes('admin-ajax.php') &&
					(candidate.request().postData() ?? '').includes('action=thrivedesk_disconnect_plugin'),
			),
			card.locator('[data-test="integration-disconnect"]').click(),
		]);

		await gotoSettings(page, 'integrations');
		await expect(card).toHaveAttribute('data-connected', '0');
	}

	const button = card.locator('[data-test="integration-connect"]');
	await expect(button).toBeVisible();
	await expect(button).toBeEnabled();

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
	// Buffer the body in a route handler rather than reading it off the Response
	// afterwards. The React card sets window.location the instant its fetch
	// resolves, and a navigation discards the response body — "No resource with
	// given identifier found" — before a later read can get to it. Intercepting
	// means the bytes are already in hand when that happens.
	let captured = '';

	await page.route('**/admin-ajax.php', async (route) => {
		if (!(route.request().postData() ?? '').includes('action=thrivedesk_connect_plugin')) {
			await route.continue();

			return;
		}

		const response = await route.fetch();
		captured = (await response.text()).trim();

		expect(response.status(), 'the connect action did not return 200').toBe(200);

		await route.fulfill({ response, body: captured });
	});

	const button = await disconnected(page, plugin);

	await button.click();

	// Wait for the hand-off to LAND, not merely to be requested. waitForRequest
	// returns the moment the navigation starts, leaving it in flight — and the
	// next connect attempt's goto then dies as "interrupted by another
	// navigation". beforeEach answers it with a blank 200, so this settles.
	await page.waitForURL(new RegExp(`/apps/${plugin}\\?connect=`));

	await page.unroute('**/admin-ajax.php');

	const connect = new URL(captured).searchParams.get('connect') ?? '';
	expect(connect, `no connect param in the redirect the plugin built: ${captured}`).not.toBe('');

	return JSON.parse(Buffer.from(connect, 'base64').toString('utf8')) as ConnectPayload;
}

test.beforeEach(async ({ page }) => {
	// The card sends the browser to ThriveDesk as soon as the ajax call returns.
	// Answer the hand-off with a blank page instead of following it: the contract
	// under test is the URL WordPress built, and following it would depend on an
	// app that isn't necessarily served wherever this suite runs.
	//
	// Fulfilled rather than aborted. An abort lands the tab on
	// chrome-error://chromewebdata, and that navigation is still settling when
	// the next connect attempt calls goto — which then fails as "interrupted by
	// another navigation". A 200 completes cleanly and leaves nothing in flight.
	await page.route('**/apps/**', (route) =>
		route.fulfill({ status: 200, contentType: 'text/html', body: '' }),
	);
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
