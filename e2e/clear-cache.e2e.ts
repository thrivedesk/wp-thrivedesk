import { expect, test } from '@playwright/test';

import { gotoSettings } from './helpers/wp';

/**
 * Clearing the cache changes nothing on this screen, so it reports in the same
 * toast the saves use rather than a modal with an OK button and a reload behind
 * it. There is nothing to acknowledge and nothing for a reload to show.
 */
test('clears the portal cache from the settings screen', async ({ page }) => {
	await gotoSettings(page);

	// The Portal tab owns the button, and every panel is rendered on every tab
	// with only `hidden` toggling - so it has to be shown before it is clickable.
	await page.locator('[role="tab"]', { hasText: 'Portal' }).click();

	const button = page.locator('#thrivedesk_clear_cache_btn');

	// The button only renders for accounts entitled to the WP Portal.
	test.skip((await button.count()) === 0, 'the account under test has no portal access');

	await button.click();

	const toast = page.locator('.td-toast').getByText('Portal cache cleared');

	await expect(toast).toBeVisible({ timeout: 15_000 });

	// It dismisses itself, and the screen is still the screen: no reload, and
	// nothing to click away.
	await expect(toast).toBeHidden({ timeout: 15_000 });
	await expect(page.locator('#td_helpdesk_form')).toBeVisible();
});
