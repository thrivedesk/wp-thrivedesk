import { defineConfig, devices } from '@playwright/test';

import { ADMIN_STATE, CUSTOMER_STATE, requireEnv } from './e2e/helpers/env';

/**
 * The suite drives a real WordPress with this plugin active — there is no
 * fixture site to fall back to, so a missing WP_BASE_URL is a hard stop
 * rather than a silent default that fails later with a confusing 404.
 */
const baseURL = requireEnv('WP_BASE_URL');

export default defineConfig({
	testDir: './e2e',
	testMatch: ['**/*.e2e.ts'],
	forbidOnly: !!process.env['CI'],
	retries: process.env['CI'] ? 2 : 0,

	/* Serial, always. The tests drive one shared WordPress and several of them
	 * write site-wide options (thrivedesk_options on connect, td_helpdesk_settings
	 * on save). Parallel workers would interleave those writes against the same
	 * rows and the restore in one test would land on top of another's edit. */
	workers: 1,

	reporter: [['list'], ['html', { open: 'never' }]],

	use: {
		baseURL,
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
		/* Dev and staging sites are commonly fronted by a private CA. */
		ignoreHTTPSErrors: true,
	},

	/* Portal first, admin last, and the key-verifying specs after even those.
	 * Re-verifying an API key clears the cached portal entitlement, and an
	 * account whose plan is read straight from ThriveDesk gets it straight back
	 * — but one relying on the cached answer would not, and the portal tests
	 * would skip for the rest of the run. That applies inside the admin project
	 * too: clear-cache and the post-type batching spec both need the portal
	 * entitlement, and api-key-verify sorts ahead of them alphabetically.
	 *
	 * It cannot simply restore what it clears, either. Only a successful
	 * verification sets td_helpdesk_verified, and the stored key is no longer
	 * rendered into the page for a spec to re-submit, so the connection it
	 * breaks stays broken for whatever runs next. Running it last is the
	 * containment. */
	projects: [
		{ name: 'setup', testMatch: /auth\.setup\.ts/ },
		{
			name: 'anonymous',
			use: { ...devices['Desktop Chrome'], storageState: { cookies: [], origins: [] } },
			testMatch: /portal-anonymous\.e2e\.ts/,
		},
		{
			name: 'customer',
			use: { ...devices['Desktop Chrome'], storageState: CUSTOMER_STATE },
			dependencies: ['setup'],
			testMatch: /portal-customer-scope\.e2e\.ts/,
		},
		{
			name: 'admin',
			use: { ...devices['Desktop Chrome'], storageState: ADMIN_STATE },
			// Portal first is a dependency, not an ordering convention: array
			// order does not survive another project joining the graph.
			dependencies: ['setup', 'anonymous', 'customer'],
			testIgnore: /(portal-.*|api-key-verify)\.e2e\.ts/,
		},
		{
			name: 'admin-verify',
			use: { ...devices['Desktop Chrome'], storageState: ADMIN_STATE },
			dependencies: ['admin'],
			testMatch: /api-key-verify\.e2e\.ts/,
		},
	],
});
