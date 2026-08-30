# Security audit — ThriveDesk WordPress plugin

**Date:** 2026-08-26 · **Baseline:** `develop` @ `d038c8b` · **Standard applied:** the five non-negotiables
(sanitize input, escape output, verify nonce, check capability, `$wpdb->prepare()`) plus REST/AJAX
hardening, ownership checks, secret handling and supply-chain hygiene.

Scope: all PHP in `src/`, `includes/`, `Hooks/`, `database/`, `thrivedesk.php`, `uninstall.php`; the
client-side sources in `resources/js/`; and CI/packaging config.

> **Handling:** this document describes exploitable defects in a plugin running on live sites.
> Keep it in the repository until the corresponding fixes have shipped.

---

## Summary

| Severity | Count |
|---|---|
| High | 6 |
| Medium | 11 |
| Low | 18 |
| Informational | 5 |

The plugin's authorization *structure* is mostly sound — all eleven AJAX handlers pair a nonce with a
capability check, both REST routes have a `permission_callback`, conversation caches are per-user
scoped, `sslverify` is never disabled, and there are no `eval`/`exec`/`unserialize`/file-write
surfaces anywhere in the tree. Production Composer dependencies are empty, so `vendor/` ships only an
autoloader.

The defects cluster in three places: **the HMAC scheme on the unauthenticated inbound listener**,
**secret handling around the API key and per-store token**, and **unbounded work reachable by any
logged-in customer**.

---

## High

### H1 — Signed payload is not the executed payload (`src/Api.php`) · **proven exploitable**

`verify_token()` hashes `$_REQUEST` (`src/Api.php:610`); the dispatcher reads `$_GET`
(`:123`, `:129-144`, `:190`). WordPress's `wp_magic_quotes()` forces
`$_REQUEST = array_merge( $_GET, $_POST )`, so a POST body wins the hash while the query string
drives execution.

Demonstrated with a live test against a real WordPress: a signature captured for `action=connect`,
replayed with `action=disconnect` in the query string, produced
`{"message":"Site has been disconnected"}`. One captured signature becomes the ability to invoke
*any* listener action — including order-status changes, subscription cancellation, coupon application
and line-item edits.

**Fix:** build the contract array once, verify it, and have every handler read from that same array.
Do *not* switch to `$_GET`-only — the sync actions may receive `extra` by POST.

### H2 — HMAC covers a different value than the one executed (`src/Api.php:643-646`)

The signature is taken over `sanitize_text_field()` output while handlers use `sanitize_key()` /
`sanitize_email()`. `%20123` hashes as `123` and executes as `20123`.

The repo's own `HmacSignatureTest::test_raw_signed_value_that_sanitization_alters_is_rejected_today`
records that **the SaaS signs the raw value** — so this is a plugin-side defect, and hashing raw
*aligns* the plugin with the sender. It additionally fixes legitimate signed requests currently
being rejected with 401. Plugin-only; no coordinated change required.

### H3 — `?token=` one-click helpdesk takeover (`src/Admin.php:274` → `Conversation.php:224`)

`admin.php?page=td-api&token=<attacker key>` forces the API-verify screen even on a connected site
and pre-fills the key field. One admin click posts it to `thrivedesk_api_key_verify`, which calls
`reset_td_settings()` **before** the `/v1/me` validation — so the attacker's key is persisted even
when verification then fails. No nonce, no state parameter, nothing binding the value to a redirect
ThriveDesk actually issued.

Consequence: every portal visitor's email and ticket body flows to the attacker's tenant, and the
attacker's assistant id boots on every front-end page.

### H4 — Quantity update decouples fulfilment from price (`src/Api.php:457`)

`wc_update_order_item_meta( $item_id, '_qty', $quantity )` writes meta directly, bypassing
`WC_Order_Item::set_quantity()`. `calculate_totals()` then sums stale in-memory line totals, so the
persisted `_qty` changes while `_line_total` does not. No upper bound and no stock check: set
`quantity=1000` and 1000 units ship at the single-unit price already captured.

### H5 — Any Subscriber can exhaust the PHP worker pool

`PortalService::has_portal_access()` caches only the *positive* answer, `TDApiService`'s default
timeout is 90 seconds, `get_plan()` passes no override (against its own docblock's advice), and
`td_reload_tickets` has no throttle while explicitly deleting the cache before fetching. A
Subscriber-level account can hold a PHP worker for up to 90s per cheap request, in parallel.

### H6 — wp.org release credentials handed to a mutable third-party action

`.github/workflows/wordpress-org-deploy.yml:47-52` uses
`10up/action-wordpress-plugin-deploy@stable` — a mutable tag — while passing `SVN_USERNAME` /
`SVN_PASSWORD`. An upstream compromise exfiltrates the publishing credentials and ships backdoored
PHP to every installed site via auto-update. The sibling workflow
`push-asset-readme-update.yml:34` already SHA-pins the same vendor's action correctly.

---

## Medium

| # | Finding | Location |
|---|---|---|
| M1 | No replay protection — signatures are permanent bearer credentials (no timestamp/nonce) | `src/Api.php:603-647` |
| M2 | Missing order reaches 4 of 6 mutators as `false` → uncatchable `\Error` (`catch (\Exception)` misses it) | `src/Api.php:346,362,453,473` |
| M3 | `add_item` accepts negative quantity (`sanitize_key` permits `-`) → negative line total | `src/Api.php:329-351` |
| M4 | `update_order_status` has no allowlist; accepts `trash`, silently coerces unknown → `pending` | `src/Api.php:382`, `WooCommerce.php:349` |
| M5 | Pre-auth plugin-activation oracle — 4 anonymous requests enumerate the commerce/CRM stack | `src/Api.php:147-170` |
| M6 | `td-search-query/docs` is `permission_callback => true` on a POST route running uncached `LIKE` scans | `src/RestRoute.php:65-71` |
| M7 | API key rendered unmasked into the admin DOM (found independently by two auditors) | `partials/settings.php:311,315` |
| M8 | Per-store `api_token` (the HMAC key) base64'd into a URL query string and navigated to | `src/Admin.php:341-349` |
| M9 | Customer reply body forwarded upstream with no sanitization (`stripslashes` is not a sanitizer) | `Conversation.php:678-680` |
| M10 | Unescaped jQuery `.append()` of API-supplied assistant/inbox names → XSS in a `manage_options` session | `resources/js/admin.js:499,563` |
| M11 | Self-joined `DELETE` on `wp_options` on every portal page render | `Conversation.php:532` |

---

## Low and Informational

Grouped by theme; full detail in the per-area agent reports.

- **Escaping / markup** — `href` accepts any scheme in portal search results; `esc_url_raw()` used in a JS
  string context; unterminated `title` attribute swallowing an `<svg>`; 8 template files lacking the
  `ABSPATH` guard; portal reply form's nonce input has no `name`.
- **Input handling** — API key read from `$_POST` unsanitized at four sites (an array value fatals `md5()`
  on PHP 8) while a fifth site in the same class does it correctly; `$_GET['query']` is the one
  unsanitized read in the listener; `shipping_param` honoured on presence rather than value, defeating a
  signed `false`.
- **SQL** — `SHOW TABLES LIKE` unprepared at one of four sibling sites; `$types_sql` interpolated into a
  `prepare()` format string; `esc_like()` missing where `$wpdb->prefix` contributes a `_` wildcard.
  **No SQL injection was found** — all 29 `$wpdb` call sites were individually verified.
- **Lifecycle** — unauthenticated `delete_option()` + redirect reachable via `admin-ajax.php`;
  `wp_cache_flush()` on deactivate empties the entire shared object cache; `create_portal_page()` fires on
  *every* plugin's activation; `uninstall.php` cleans only one site on multisite, leaving other subsites'
  API keys behind.
- **Reliability** — `postRequest()` never checks `is_wp_error()` or the status code, so a support reply
  that never left the site is reported to the customer as sent; schema version is a float compared with
  `(float)` casting (locale-sensitive on PHP 7.4) and the create branch uses `add_option`, a no-op on a
  stale row.
- **Process** — `phpcs.xml` excludes every file that reads a superglobal or touches `$wpdb`, so the
  `WordPress.Security` and `WordPress.DB.PreparedSQL` sniffs currently protect almost nothing and CI is
  green regardless. Two workflows inherit the default `GITHUB_TOKEN` scope. `.distignore` and
  `.gitattributes` disagree on four files.

---

## Checked and found correct

Recorded so the coverage is auditable, and so these are not re-litigated:

- **No IDOR on the contact REST route.** `thrivedesk/v1/conversations/contact/<id>` is
  `current_user_can('manage_options')`, and the id is `\d+`-constrained. A Subscriber gets 401.
- **All 11 AJAX handlers verify a nonce**, and 10 of 11 also check `manage_options`. The eleventh
  (`td_reload_tickets`) is deliberately customer-facing and uses `is_user_logged_in()` with a per-user
  cache key. The shared `thrivedesk-nonce` is minted for every logged-in portal visitor — assessed, and
  safe today only because every consumer also checks the capability.
- **Portal scoping.** `customer_email` is always derived from `wp_get_current_user()`, never the request;
  conversation ids are allowlisted `^[A-Za-z0-9-]{1,64}$`; queries are built with `http_build_query()`;
  cache keys include the reader's email.
- **`ConversationSyncData` holds.** The allowlist iterates the *allowlist* and pulls from the payload —
  the direction that cannot be bypassed by an unexpected key. Both writers to `td_conversations` go
  through it; there is no direct-write path.
- **No SQL injection, no SSRF, no unauthenticated AJAX, no `eval`/`exec`/`unserialize`, no file writes,
  no path traversal, no hardcoded credentials, no secret in any log.** `sslverify` is never disabled.
  `extract()` in `thrivedesk_view()` is present but **not exploitable** — all 11 call sites pass string
  literals and none passes `$data`.
- **Token generation is sound** — `bin2hex( random_bytes( 32 ) )`, per-integration.

---

## Requires coordinated SaaS change — deliberately not fixed plugin-side

Shipping these before the signers in `app/apps/<Integration>/Services/*Service.php` are updated would
401 every real request:

1. **Replay protection** — adding `timestamp` (and ideally `nonce`) to `SIGNED_PARAMS`.
2. **HMAC-SHA1 → SHA-256.**
3. **Moving the `api_token` out of the connect URL** onto a server-to-server handoff.

Each needs an accept-both transition window. See `docs/security-saas-coordination.md`.
