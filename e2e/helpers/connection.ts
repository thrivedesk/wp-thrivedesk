import { expect, Page } from '@playwright/test';

import { API_VERIFY_URL, gotoSettings } from './wp';

/**
 * Everything the settings form owns. td_save_helpdesk_form() replaces the whole
 * td_helpdesk_settings option with what the form posted, and a verification run
 * additionally clears the assistant, inbox and knowledge base — so anything a
 * test disturbs has to be captured up front and posted back in one save.
 */
export interface ConnectionState {
	assistantId: string;
	inboxId: string;
	knowledgebaseSlug: string;
	ticketPageId: string;
	excludedRoutes: string[];
	postTypes: string[];
	postSync: string[];
	userAccountPages: string[];
}

interface AjaxHandle {
	url: string;
	nonce: string;
}

declare global {
	interface Window {
		thrivedesk?: { ajax_url: string; nonce: string };
	}
}

async function selectValue(page: Page, selector: string): Promise<string> {
	const select = page.locator(selector);

	return (await select.count()) > 0 ? select.inputValue() : '';
}

async function checkedValues(page: Page, selector: string): Promise<string[]> {
	return page
		.locator(`${selector}:checked`)
		.evaluateAll((boxes) => boxes.map((box) => (box as HTMLInputElement).value));
}

export async function captureConnection(page: Page): Promise<ConnectionState> {
	await gotoSettings(page);

	return {
		assistantId: await selectValue(page, '#td-assistants'),
		inboxId: await selectValue(page, '#td-inboxes'),
		knowledgebaseSlug: await selectValue(page, '#td_knowledgebase_slug'),
		ticketPageId: await selectValue(page, '#td_helpdesk_page_id'),
		excludedRoutes: await page
			.locator('#td-excluded-routes option:checked')
			.evaluateAll((options) => options.map((option) => (option as HTMLOptionElement).value)),
		postTypes: await checkedValues(page, '.td_helpdesk_post_types'),
		postSync: await checkedValues(page, '.td_helpdesk_post_sync'),
		userAccountPages: await checkedValues(page, '.td_user_account_pages'),
	};
}

/** The ajax url and nonce the plugin hands its own admin scripts. */
async function ajaxHandle(page: Page): Promise<AjaxHandle> {
	const handle = await page.evaluate(() => window.thrivedesk ?? null);

	expect(handle, 'thrivedesk was not localized — is this a plugin admin screen?').not.toBeNull();

	return { url: handle!.ajax_url, nonce: handle!.nonce };
}

function formBody(action: string, handle: AjaxHandle, data: Record<string, string | string[]>): string {
	const body = new URLSearchParams();
	body.set('action', action);
	body.set('nonce', handle.nonce);

	for (const [key, value] of Object.entries(data)) {
		if (Array.isArray(value)) {
			value.forEach((item) => body.append(`data[${key}][]`, item));
		} else {
			body.set(`data[${key}]`, value);
		}
	}

	return body.toString();
}

async function postAjax(
	page: Page,
	action: string,
	handle: AjaxHandle,
	data: Record<string, string | string[]>,
): Promise<string> {
	const response = await page.request.post(handle.url, {
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		data: formBody(action, handle, data),
	});

	expect(response.ok(), `${action} returned ${response.status()}`).toBe(true);

	return response.text();
}

/**
 * Puts the connection back the way captureConnection() found it.
 *
 * Teardown goes through the plugin's own admin-ajax actions — the very ones the
 * settings screen posts — rather than by clicking through the form again. Two
 * requests replace a dozen interactions, and it keeps teardown out of the way of
 * how the admin page renders: the screen reached through the onboarding
 * verification flow stops producing animation frames, which is enough to stall
 * every Playwright action that waits for an element to settle.
 */
export async function restoreConnection(page: Page, state: ConnectionState): Promise<void> {
	// The API-verify screen renders whatever state the connection is in, so it is
	// the one plugin page guaranteed to be there — and to carry the ajax handle.
	await page.goto(API_VERIFY_URL);

	const handle = await ajaxHandle(page);

	// The key is deliberately never rendered into the page, so it cannot be read
	// back and does not need to be: td_save_helpdesk_form() reads an empty key as
	// "unchanged" and keeps the stored one.
	const saved = await postAjax(page, 'thrivedesk_helpdesk_form', handle, {
		td_helpdesk_api_key: '',
		td_helpdesk_assistant: state.assistantId,
		td_helpdesk_inbox_id: state.inboxId,
		td_knowledgebase_slug: state.knowledgebaseSlug,
		td_helpdesk_page_id: state.ticketPageId,
		td_assistant_route_list: state.excludedRoutes,
		td_helpdesk_post_types: state.postTypes,
		td_helpdesk_post_sync: state.postSync,
		td_user_account_pages: state.userAccountPages,
	});

	expect(saved, 'restoring the settings failed').toContain('success');

	// What this cannot put back is td_helpdesk_verified. Only a successful
	// verification sets it, thrivedesk_system_info refuses an empty key, and the
	// stored key is unreadable by design. So a spec that clears the verified flag
	// leaves it cleared, which is why the one that does runs in its own project,
	// after every other admin spec (see playwright.config.ts).
}
