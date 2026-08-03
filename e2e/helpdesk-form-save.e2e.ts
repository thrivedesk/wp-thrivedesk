import { expect, test } from '@playwright/test';

import { confirmSwal, gotoSettings, swalTitle } from './helpers/wp';

/**
 * td_save_helpdesk_form() replaces the whole settings option rather than merging
 * into it, so "the value survived a reload" is the only assertion that proves a
 * save actually landed.
 */
test('saves the selected assistant and reads it back', async ({ page }) => {
	await gotoSettings(page);

	const assistants = page.locator('#td-assistants');
	const original = await assistants.inputValue();

	const target = await assistants
		.locator('option')
		.nth(1)
		.getAttribute('value');

	expect(target, 'the site under test has no assistant to select').toBeTruthy();

	await assistants.selectOption(target!);
	await page.locator('#td_setting_btn_submit').click();

	await expect(swalTitle(page)).toHaveText('Success');
	await confirmSwal(page);

	await gotoSettings(page);
	await expect(page.locator('#td-assistants')).toHaveValue(target!);

	await page.locator('#td-assistants').selectOption(original);
	await page.locator('#td_setting_btn_submit').click();
	await expect(swalTitle(page)).toHaveText('Success');
	await confirmSwal(page);

	await gotoSettings(page);
	await expect(page.locator('#td-assistants')).toHaveValue(original);
});
