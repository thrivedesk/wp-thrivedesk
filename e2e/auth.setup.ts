import { expect, Page, test as setup } from '@playwright/test';

import {
	ADMIN_PASSWORD,
	ADMIN_STATE,
	ADMIN_USER,
	CUSTOMER_PASSWORD,
	CUSTOMER_STATE,
	CUSTOMER_USER,
} from './helpers/env';

async function logIn(page: Page, user: string, password: string, statePath: string): Promise<void> {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', user);
	await page.fill('#user_pass', password);
	await page.click('#wp-submit');

	// A wrong password re-renders wp-login.php with #login_error and no auth
	// cookie, so assert on the cookie rather than on wherever WordPress chose to
	// redirect — that lands on the dashboard for an admin and My Account for a
	// customer, and themes move both.
	await page.waitForURL((url) => !url.pathname.endsWith('/wp-login.php'));

	const cookies = await page.context().cookies();
	const authenticated = cookies.some((cookie) => cookie.name.startsWith('wordpress_logged_in_'));

	expect(authenticated, `${user} did not get a WordPress auth cookie — check the credentials`).toBe(true);

	await page.context().storageState({ path: statePath });
}

setup('authenticate as administrator', async ({ page }) => {
	await logIn(page, ADMIN_USER(), ADMIN_PASSWORD(), ADMIN_STATE);
});

setup('authenticate as customer', async ({ page }) => {
	await logIn(page, CUSTOMER_USER(), CUSTOMER_PASSWORD(), CUSTOMER_STATE);
});
