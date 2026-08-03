import { expect, test } from '@playwright/test';

import { confirmSwal, gotoSettings, swalText, swalTitle } from './helpers/wp';

test('clears the portal cache from the settings screen', async ({ page }) => {
	await gotoSettings(page);

	const button = page.locator('#thrivedesk_clear_cache_btn');

	// The button only renders for accounts entitled to the WP Portal.
	test.skip((await button.count()) === 0, 'the account under test has no portal access');

	await button.click();

	await expect(swalTitle(page)).toHaveText('Success');
	await expect(swalText(page)).toHaveText('Cache Cleared');

	// Confirming reloads the settings screen.
	await confirmSwal(page);
	await expect(page.locator('#td_helpdesk_form')).toBeVisible();
});
