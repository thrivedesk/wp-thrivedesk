import { expect, test } from '@playwright/test';

import { captureConnection, ConnectionState, restoreConnection } from './helpers/connection';
import { confirmSwal, gotoApiVerify, gotoSettings, revealApiKeyField, swalText, swalTitle } from './helpers/wp';

/**
 * Verification is destructive by design — the submitted key is stored before it
 * is checked, and storing it clears the assistant, inbox and knowledge base. So
 * both tests hand the site back the way they found it.
 */
let saved: ConnectionState;

// Restoring means verifying twice over and saving the form, each step waiting on
// a live API round-trip — comfortably past the default per-test budget.
test.describe.configure({ timeout: 150_000 });

test.beforeEach(async ({ page }) => {
	saved = await captureConnection(page);
});

test.afterEach(async ({ page }) => {
	await restoreConnection(page, saved);
});

test('rejects a key the API does not recognise', async ({ page }) => {
	await gotoApiVerify(page);
	await page.fill('#td_helpdesk_api_key', 'this-is-not-a-thrivedesk-api-key');
	await page.locator('#submit-btn').click();

	await expect(swalTitle(page)).toHaveText('Error');
	await expect(swalText(page)).toContainText('401');
	await confirmSwal(page);

	// With no verified key on file the menu page drops back to the welcome
	// screen instead of the settings form.
	await page.goto('/wp-admin/admin.php?page=thrivedesk');
	await expect(page.locator('#td_helpdesk_form')).toHaveCount(0);
	await expect(page.getByText("Welcome, Let's Setup Your HelpDesk")).toBeVisible();
});

test('verifies the stored key and unlocks the connected settings', async ({ page }) => {
	await gotoSettings(page);
	await revealApiKeyField(page);
	await page.locator('#td-api-verification-btn').click();

	await expect(swalTitle(page)).toHaveText('Success');

	const verifyButton = page.locator('#td-api-verification-btn');
	await expect(verifyButton).toHaveText('Verified');
	await expect(verifyButton).toBeDisabled();

	// Verifying is what releases the two selects the rest of the form depends on.
	await expect(page.locator('#td-assistants')).toBeEnabled();

	await confirmSwal(page);
});
