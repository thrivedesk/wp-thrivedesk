import { expect, test } from '@playwright/test';

import { PORTAL_PATH } from './helpers/env';

test('sends a signed-out visitor to the login form instead of a ticket list', async ({ page }) => {
	await page.goto(PORTAL_PATH());

	await expect(page.getByText('You must be logged in to view the ticket or conversation')).toBeVisible();
	await expect(page.locator('#conversation-table')).toHaveCount(0);

	const login = page.getByRole('link', { name: 'here' });
	await expect(login).toHaveAttribute('href', /wp-login\.php/);
});
