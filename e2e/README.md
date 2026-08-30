# Browser tests

Playwright specs that drive the plugin the way a site owner does: the admin
screens under `wp-admin`, and the portal shortcode on the front end.

They run against a **real WordPress with this plugin active and connected to a
ThriveDesk account** — there is no fixture site and nothing is stubbed. Point
them at a local install, a staging site, or a throwaway box; the suite only
needs the URL and two logins.

## Running

The quickest way to get a site these can run against is the bench:
`scripts/dev.sh up`, `scripts/dev.sh connect <api-key>`, pick an assistant and an
inbox on the settings screen, then `scripts/dev.sh e2e` (`e2e-ui` to watch it,
`e2e-report` for the last run). It supplies every variable below, so the rest of
this section is for pointing the suite at a site of your own.

```
npm install
npx playwright install chromium   # skip if the browsers are already present

WP_BASE_URL=https://example.test \
WP_ADMIN_USER=admin WP_ADMIN_PASSWORD=... \
WP_CUSTOMER_USER=shopper WP_CUSTOMER_PASSWORD=... \
npm run e2e
```

`npm run e2e:ui` opens the Playwright UI runner instead.

| Variable | Required | Purpose |
| --- | --- | --- |
| `WP_BASE_URL` | yes | Site root, no trailing slash |
| `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` | yes | An account with `manage_options` |
| `WP_CUSTOMER_USER` / `WP_CUSTOMER_PASSWORD` | yes | A customer with at least one ThriveDesk ticket |
| `WP_PORTAL_PATH` | no | Path of the page holding `[thrivedesk_portal]` (default `/thrivedesk-support-portal/`) |

## What the site needs

- The plugin connected to a ThriveDesk account, with at least one assistant and
  one inbox on that account.
- A published page containing the `[thrivedesk_portal]` shortcode.
- The customer account's email matching a ThriveDesk contact that has tickets —
  without one there is nothing for the portal to list.
- WooCommerce active, for the connect-handshake spec.

The portal specs skip themselves, with a reason, on an account whose plan does
not include the WP Portal.

## Writing specs

- **The suite runs serially and shares one site.** Anything a spec changes it
  changes for every spec after it, so put back what you touch — see
  `helpers/connection.ts`, which captures the whole settings form and restores
  it through the same controls a person would use.
- **Verification is destructive.** Submitting a key stores it before checking
  it, and storing it clears the assistant, inbox and knowledge base. Any spec
  that verifies has to restore.
- **Prefer the ids already in the markup** (`#td_helpdesk_api_key`,
  `#td-assistants`, `#td_helpdesk_page_id`, `#td-search-card`, `.td-toast`,
  `[data-plugin]` on an integration card)
  over adding new hooks. The integrations grid is React, so its card carries
  `data-plugin` and `data-connected` and its buttons carry
  `data-test="integration-connect"` / `"integration-disconnect"`.
- **The screen is tabbed.** A React `TabPanel` moves each `#td-panel-*` out of
  the settings form on mount, so the form itself never becomes visible and a
  field is only on screen when its tab is. Reach a tab with
  `gotoSettings(page, 'livechat' | 'portal' | 'integrations')` rather than
  clicking through the strip.
- **Assertions go through the UI.** A spec that reaches around the interface to
  make its assertions stops describing what a user can do. Teardown is the one
  exception — `helpers/connection.ts` posts to the plugin's own admin-ajax
  actions, for the reason below.
- **Normalise state you depend on; don't assume it.** An integration's button is
  Connect or Disconnect depending on stored state, and each posts a different
  action — so a spec that assumes one silently drives the other and then waits for
  a response that never comes. Seeded sites can arrive either way. See
  `disconnected()` in `woocommerce-connect.e2e.ts`.
- **When a click-plus-wait times out, log the requests before blaming the click.**
  `Promise.all([waitFor…, click])` reports the *sibling* promise's timeout, so the
  error names the wait and every artifact shows a healthy page with an enabled
  button.
- **A rendered button is not a working button.** Every handler in `admin.js` is
  registered in one jQuery ready callback, and that bundle carries sweetalert2 and
  wp-i18n, so it lands well after the page is interactive — later still on a site
  serving unminified core scripts. A click before then hits a live, visible,
  enabled button and does nothing, which surfaces as whatever the test was waiting
  on timing out, pointing anywhere but the click. `gotoSettings` and
  `gotoApiVerify` wait for the binding; if you navigate by hand, call
  `waitForHandlers` yourself.
- **The settings screen sometimes stops producing animation frames.** When it
  does, every Playwright call that waits for an element to settle
  (`click`, `fill`, `scrollIntoViewIfNeeded`) waits forever, and the call log
  stops at "waiting for element to be visible, enabled and stable" without ever
  saying why. Measured on that page: `requestAnimationFrame` never fires while
  `readyState` is `complete`, the element is visible with `pointer-events: auto`,
  and its box is identical across samples; a reload does not clear it, and
  `force: true` and `page.evaluate` still work. Where a spec has to click through
  it, assert visibility and enabled state explicitly and then click with
  `force: true` — that waives the "has it stopped moving" wait and nothing else.
