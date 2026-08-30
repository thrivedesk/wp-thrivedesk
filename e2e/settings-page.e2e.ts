import { expect, test } from '@playwright/test';

import { gotoSettings } from './helpers/wp';

/**
 * The assistant and inbox lists are rendered server-side from live API calls, so
 * an empty list here means the round-trip to ThriveDesk is broken — not that a
 * template changed. That makes this the cheapest signal that the connection
 * actually works, as opposed to merely being flagged as verified.
 */
test('lists the assistants and inboxes fetched from ThriveDesk', async ({ page }) => {
	await gotoSettings(page, 'livechat');

	const assistants = page.locator('#td-assistants option');
	await expect(assistants.first()).toHaveText(/Select an assistant/);
	expect(await assistants.count(), 'no assistants came back from the API').toBeGreaterThan(1);

	// Inboxes are served on the Portal tab, so this needs the other panel.
	await gotoSettings(page, 'portal');

	const inboxes = page.locator('#td-inboxes option');
	expect(await inboxes.count(), 'no inboxes came back from the API').toBeGreaterThan(1);
});

test('offers every integration the plugin supports', async ({ page }) => {
	await gotoSettings(page, 'integrations');

	const woocommerce = page.locator('[data-plugin="woocommerce"] [data-test="integration-connect"], [data-plugin="woocommerce"] [data-test="integration-disconnect"]');
	await expect(woocommerce).toBeVisible();

	// WooCommerce is active on the site under test, so its button must be live.
	// The inactive integrations render disabled with a "Not installed" hint.
	await expect(woocommerce).toBeEnabled();

	for (const plugin of ['edd', 'fluentcrm', 'wppostsync', 'autonami']) {
		await expect(page.locator(`[data-plugin="${plugin}"]`)).toHaveCount(1);
	}
});
