import { expect, test } from '@playwright/test';

import { gotoSettings } from './helpers/wp';

/**
 * The disconnected warning renders on `admin_notices`, so it is not confined to
 * this plugin's screen — it appears on every admin page on the site. That reach
 * is the point when a key really has stopped working, and it is the damage when
 * the plugin decides that wrongly: a site whose key is fine gets told, on every
 * screen, that its help desk is offline.
 *
 * That wrong decision is not hypothetical. A single 403 from any endpoint used
 * to clear the verified flag, and a key granted a narrowed set of capabilities
 * 403s on the endpoints it was not granted while authenticating perfectly well
 * against /v1/me. The settings screen reads /v1/inboxes on every render, so such
 * a site cleared its own flag, showed this warning everywhere, healed itself on
 * the next visit to the plugin screen, and did it again on the render after.
 *
 * So this asserts the absence, on a connected site, on both the plugin's own
 * screen and one that has nothing to do with the plugin. The presence case
 * cannot be driven from here: only ThriveDesk refusing the key on file clears
 * the flag, and nothing a browser can reach makes a valid key start failing.
 * `tests/RuntimeAuthFailureTest.php` covers that half at the HTTP boundary.
 *
 * Changes no state, so there is nothing to restore.
 */
const NOTICE = '.td-connection-notice';

test('a connected site is not told its connection is broken', async ({ page }) => {
	await gotoSettings(page);

	// gotoSettings() has already established this site is connected: it throws
	// when the settings form is absent, and the form only renders with a key on
	// file. So an absent warning here means absent, not merely unrendered.
	await expect(page.locator(NOTICE)).toHaveCount(0);
});

test('nor is it told so on admin screens that have nothing to do with the plugin', async ({
	page,
}) => {
	// The regression this guards showed up here rather than on the plugin's own
	// screen, because this is where a site owner spends their time and where the
	// plugin has no card of its own to contradict.
	for (const url of ['/wp-admin/index.php', '/wp-admin/plugins.php']) {
		await page.goto(url);

		// Confirm the screen actually rendered before reading anything off it,
		// or a redirect to a login form would pass this as "no warning".
		await expect(page.locator('#wpbody-content')).toBeVisible();
		await expect(page.locator(NOTICE)).toHaveCount(0);
	}
});
