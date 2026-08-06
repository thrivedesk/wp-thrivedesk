import { expect, Page, test } from '@playwright/test';

import { portalUrl } from './helpers/env';
import { portalIsEntitled } from './helpers/wp';

const TICKET_ROWS = '#conversation-table tbody tr[data-ticket-id]';

async function ticketIds(page: Page): Promise<string[]> {
	return page.locator(TICKET_ROWS).evaluateAll((rows) =>
		rows.map((row) => row.getAttribute('data-ticket-id') ?? ''),
	);
}

test('shows the signed-in customer their own tickets', async ({ page }) => {
	await page.goto(portalUrl());

	test.skip(!(await portalIsEntitled(page)), 'the account under test has no portal access');

	await expect(page.locator('#conversation-table')).toBeVisible();
	expect((await ticketIds(page)).length, 'the customer under test has no tickets to list').toBeGreaterThan(0);
});

/**
 * Regression guard for the portal ticket list being scoped to whoever is asking.
 *
 * cv_page used to reach the request URL unsanitised, so a logged-in customer
 * could append a second customer_email to it and read another customer's list —
 * the API takes the last value of a duplicated parameter. absint() on the page
 * plus http_build_query() on the whole query closes that.
 *
 * The injected load has to come first. Before the fix the raw cv_page also went
 * into the cache key, so the poisoned request always missed the cache and hit
 * the API; a clean load beforehand would not mask it, but ordering it this way
 * keeps the failure immediate and obvious.
 */
test('ignores a customer_email smuggled in through cv_page', async ({ page }) => {
	const injected = portalUrl(`cv_page=${encodeURIComponent('1&customer_email=someone.else@example.com')}`);

	await page.goto(injected);

	// Guard the guard: if the parameter never lands in $_GET there is nothing to
	// smuggle and the assertions below would pass against vulnerable code.
	expect(new URL(page.url()).searchParams.get('cv_page')).toBe('1&customer_email=someone.else@example.com');

	test.skip(!(await portalIsEntitled(page)), 'the account under test has no portal access');

	// A successful injection asks ThriveDesk for a contact this customer has
	// nothing to do with; the list comes back empty and the table is replaced
	// by the empty state.
	await expect(page.locator('#conversation-table')).toBeVisible();

	const smuggled = await ticketIds(page);

	await page.goto(portalUrl());
	const own = await ticketIds(page);

	expect(smuggled).toEqual(own);
});
