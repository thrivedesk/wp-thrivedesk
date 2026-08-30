import { expect, test } from '@playwright/test';

import { captureConnection, ConnectionState, restoreConnection } from './helpers/connection';
import { confirmSwal, gotoApiVerify, gotoSettings, swalText, swalTitle } from './helpers/wp';

/**
 * Submitting a key no longer stores it before checking it, so verification is
 * not destructive the way it was. What a rejection still costs is the verified
 * flag and the cached portal entitlement, and neither can be handed back: only a
 * successful verification sets the flag, and the stored key is deliberately
 * never rendered into the page for a spec to re-submit.
 *
 * So these run in their own project, after every other admin spec. The settings
 * are still captured and restored, because a save is the one part that can be.
 */
let saved: ConnectionState;

// Restoring waits on a live API round-trip, comfortably past the default budget.
test.describe.configure({ timeout: 150_000 });

test.beforeEach(async ({ page }) => {
	saved = await captureConnection(page);
});

test.afterEach(async ({ page }) => {
	await restoreConnection(page, saved);
});

/**
 * Declared first, and it must stay first: the rejection below leaves the site
 * unverified, and this describes a connected one.
 *
 * It asserts the state rather than producing it. Re-verifying the key on file
 * used to be the way in, but the field is blank now and an empty submission is
 * "unchanged", so there is no longer a way to re-check an existing connection
 * from this screen. Worth a button one day; not something a spec can fake.
 */
test('shows the connection and unlocks the settings that depend on it', async ({ page }) => {
	await gotoSettings(page);

	// The Overview leads with the connection rather than the connect card.
	await expect(page.getByText('Connection details')).toBeVisible();
	await expect(page.locator('#td-setup-split')).toHaveCount(0);

	// Only a key on file releases the selects the rest of the form is built on.
	await gotoSettings(page, 'livechat');
	await expect(page.locator('#td-assistants')).toBeEnabled();

	await gotoSettings(page, 'portal');
	await expect(page.locator('#td-inboxes')).toBeEnabled();
});

test('rejects a key the API does not recognise', async ({ page }) => {
	await gotoApiVerify(page);
	await page.fill('#td_helpdesk_api_key', 'this-is-not-a-thrivedesk-api-key');
	await page.locator('#submit-btn').click();

	await expect(swalTitle(page)).toHaveText('Error');
	await expect(swalText(page)).toContainText('401');
	await confirmSwal(page);

	// And the connection it already had survives it. Nothing is written until a
	// key authenticates, so a rejected submission cannot take the site offline -
	// which is the point: a bad paste, or a key an attacker pre-filled through
	// ?token=, used to become the key on file before the check came back.
	await page.goto('/wp-admin/admin.php?page=thrivedesk');
	await expect(page.getByText('Connection details')).toBeVisible();
	await expect(page.locator('#td-setup-split')).toHaveCount(0);
});
