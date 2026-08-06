import { expect, Locator, Page } from '@playwright/test';

export const SETTINGS_URL = '/wp-admin/admin.php?page=thrivedesk';
export const API_VERIFY_URL = '/wp-admin/admin.php?page=td-api';

/**
 * The ThriveDesk menu page renders one of three views depending on stored state
 * (welcome / api-verify / settings). Landing on the wrong one means the site
 * under test isn't connected, so say that rather than time out on a selector.
 */
export async function gotoSettings(page: Page): Promise<void> {
	await page.goto(SETTINGS_URL);

	const form = page.locator('#td_helpdesk_form');

	if ((await form.count()) === 0) {
		throw new Error(
			'The ThriveDesk settings form did not render — the site under test must be connected to a ThriveDesk account before running the suite (see e2e/README.md)',
		);
	}

	await expect(form).toBeVisible();
	await waitForHandlers(page, 'button.connect');
}

export async function gotoApiVerify(page: Page): Promise<void> {
	await page.goto(API_VERIFY_URL);
	await expect(page.locator('#submit-btn')).toBeVisible();
	await waitForHandlers(page, '#submit-btn');
}

/**
 * Waits until the plugin's admin script has bound its click handlers.
 *
 * Rendered markup is not a ready button here: every handler is registered inside
 * one jQuery ready callback in admin.js, and that bundle carries sweetalert2 and
 * wp-i18n, so it lands well after the page is interactive — later still on a site
 * serving unminified core scripts. A click before then hits a live, visible,
 * enabled button and does absolutely nothing, which surfaces much later as
 * whatever the test was waiting on timing out.
 *
 * Checking one bound element is enough: the callback binds them all at once.
 */
export async function waitForHandlers(page: Page, selector: string): Promise<void> {
	await page.waitForFunction((sel) => {
		// Minimal shape of what this needs from jQuery; the plugin ships no types.
		type Bound = ((s: string) => ArrayLike<Element>) & {
			_data: (el: Element, key: string) => { click?: unknown } | undefined;
		};

		const jq = (window as unknown as { jQuery?: Bound }).jQuery;
		if (!jq) {
			return false;
		}
		const el = jq(sel)[0];

		return !!el && !!jq._data(el, 'events')?.click;
	}, selector);
}

/** The stored key, read off the settings form. Hidden until "Update" is clicked. */
export async function readStoredApiKey(page: Page): Promise<string> {
	const key = await page.locator('#td_helpdesk_api_key').inputValue();

	expect(key, 'the site under test has no stored API key').not.toBe('');

	return key;
}

/** Reveals the API key field on the settings page so it can be edited. */
export async function revealApiKeyField(page: Page): Promise<void> {
	await page.locator('.api-key-preview .trigger').click();
	await expect(page.locator('#td_helpdesk_api_key')).toBeVisible();
}

export function swalTitle(page: Page): Locator {
	return page.locator('.swal2-title');
}

export function swalText(page: Page): Locator {
	return page.locator('.swal2-html-container');
}

export async function confirmSwal(page: Page): Promise<void> {
	await page.locator('.swal2-confirm').click();
}

/**
 * Portal rendering is entitlement-gated: an account whose plan doesn't include
 * the WP Portal gets a notice in place of the ticket list. Tests that need the
 * list skip themselves rather than fail on an environment they can't fix.
 */
export async function portalIsEntitled(page: Page): Promise<boolean> {
	return (await page.locator('.td-portal-notice').count()) === 0;
}
