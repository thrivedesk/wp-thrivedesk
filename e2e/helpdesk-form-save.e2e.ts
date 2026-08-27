import { expect, test } from '@playwright/test';

import { gotoSettings } from './helpers/wp';

/**
 * td_save_helpdesk_form() replaces the whole settings option rather than merging
 * into it, so "the value survived a reload" is the only assertion that proves a
 * save actually landed.
 *
 * There is no Save button: changing a field persists it. The toast is the only
 * signal the user gets, so this waits for it rather than sleeping - and then
 * reloads anyway, because a toast proves a response arrived, not that anything
 * was written.
 */
const savedToast = (page: import('@playwright/test').Page) =>
	page.locator('.td-toast').getByText('Changes saved');

async function selectAssistantAndWaitForSave(
	page: import('@playwright/test').Page,
	value: string
) {
	await page.locator('#td-assistants').selectOption(value);
	await expect(savedToast(page)).toBeVisible({ timeout: 15_000 });
	await expect(savedToast(page)).toBeHidden({ timeout: 15_000 });
}

test('saves the selected assistant automatically and reads it back', async ({ page }) => {
	await gotoSettings(page);

	const assistants = page.locator('#td-assistants');
	const original = await assistants.inputValue();

	const target = await assistants.locator('option').nth(1).getAttribute('value');

	expect(target, 'the site under test has no assistant to select').toBeTruthy();

	await selectAssistantAndWaitForSave(page, target!);

	await gotoSettings(page);
	await expect(page.locator('#td-assistants')).toHaveValue(target!);

	await selectAssistantAndWaitForSave(page, original);

	await gotoSettings(page);
	await expect(page.locator('#td-assistants')).toHaveValue(original);
});

/**
 * The debounce exists so that ticking several boxes is one request. If it ever
 * regresses to one save per change, the later responses can land out of order
 * and the stored value stops matching the last thing that was clicked.
 */
test('batches rapid changes into a single save', async ({ page }) => {
	await gotoSettings(page);

	const saves: string[] = [];
	page.on('request', (request) => {
		if (request.method() === 'POST' && request.postData()?.includes('thrivedesk_helpdesk_form')) {
			saves.push(request.url());
		}
	});

	const boxes = page.locator('.td_helpdesk_post_types');
	const count = await boxes.count();

	test.skip(count < 2, 'needs at least two post types to batch');

	await boxes.nth(0).click();
	await boxes.nth(1).click();
	await boxes.nth(0).click();
	await boxes.nth(1).click();

	await expect(savedToast(page)).toBeVisible({ timeout: 15_000 });
	await page.waitForTimeout(2_000);

	expect(saves.length, 'four rapid changes should collapse into one save').toBeLessThanOrEqual(2);
});
