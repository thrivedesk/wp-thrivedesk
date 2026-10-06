# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

WordPress plugin that connects a WP site to the ThriveDesk SaaS help desk. Two directions of traffic:

- **Inbound** — the SaaS calls the site at `?listener=thrivedesk&plugin=<x>&action=<y>` to read/mutate store data (orders, subscriptions, CRM contacts, posts). Handled by `ThriveDesk\Api`.
- **Outbound** — the site calls `api.thrivedesk.com` for conversations, assistants, inboxes, knowledge base. Handled by `ThriveDesk\Services\TDApiService`.

PHP 7.4 minimum (enforced in CI). PSR-4 autoload maps `ThriveDesk\` → `src/`, `src/Abstracts/`, `Hooks/`.

## Commands

Everything runs through `td wp ...` in the ThriveDesk workspace; the verbs are in the `td-cli` skill, or `td wp --help`.

| Task | Command |
| --- | --- |
| Build assets | `td wp build` |
| Watch assets | `td wp watch` |
| Any npm script | `td wp npm <args>` |
| PHP tests | `td wp test` (PHPUnit args pass through, e.g. `--filter test_name tests/HmacSignatureTest.php`) |
| Lint | `td wp phpcs` |
| Browser suite | `td wp e2e` (provisions its own isolated stack; the env vars in `e2e/README.md` are for standalone runs) |
| wp-cli | `td wp cli plugin list` |
| Logs (apache + PHP + debug.log) | `td wp logs` |
| Users | `td wp user ls`, `td wp user passwd <login>` |
| Wipe and start over | `td wp reset` |

Composer scripts with no verb (`phpcs-i18n`, `phpcompat`) run inside `td wp shell`; `npm run release` runs as `td wp npm run release`.

`td wp test` runs the suite against an ephemeral MySQL and the WP test library that `td setup` installs, and needs the stack up. WooCommerce is installed and active in that WordPress. `.github/workflows/phpunit.yml` shows the same environment assembled by hand.

**The bench never ships.** `scripts/dev.sh` is the standalone bench for contributors working outside the ThriveDesk workspace. `docker/` and `scripts/` are excluded in both `.distignore` (the wp.org manifest) and `.gitattributes`, the deploy workflow fails the release if either reaches the package, and `scripts/release.sh` copies an explicit allowlist that does not include them.

## Architecture

### Inbound listener (`src/Api.php`)

`Api::api_listener()` runs on `wp_loaded` (**not `init`** — WooCommerce Subscriptions finishes its own setup at `init` 10). Order of checks, which is security-relevant:

1. resolve `plugin` → integration class via `_available_plugins()`
2. `is_plugin_active()`
3. `verify_token()` — HMAC-SHA1 of `wp_json_encode()` over **only** the params in `Api::SIGNED_PARAMS`, keyed by the integration's stored `api_token`, compared against the `X-TD-Signature` header. Never hash raw `$_REQUEST`: third-party plugins inject their own query vars and would break the signature. Empty token or empty signature ⇒ reject.
4. every action except `connect`/`disconnect` also requires `get_plugin_data('connected')`
5. dispatch to the action handler

`Api::SIGNED_PARAMS` is a contract shared with the SaaS signers — changing it needs a matching change server-side.

### Integrations (`src/Plugins/`)

Each extends `ThriveDesk\Plugin` (`src/Abstracts/Plugin.php`) and is a singleton: `EDD`, `WooCommerce`, `FluentCRM`, `WPPostSync`, `Autonami`. The abstract defines the contract — `is_plugin_active()`, `is_customer_exist()`, `accepted_statuses()`, `get_customer()`, `get_plugin_data()`, `connect()`, `disconnect()`. Per-integration state (including `api_token` and `connected`) lives in the `thrivedesk_options` option.

### Everything is a singleton

`thrivedesk.php` boots `ThriveDesk::instance()`, which calls `::instance()` on `Api`, `FluentCrmHooks`, `RestRoute`, `Admin` (admin only), `MigrationScript`, `Conversation`, `Assistant`, `Inbox`, `PortalService`, `UserAccountPages`, `KnowledgeBase`. Constructors register their own hooks — that's where to look for what a class does.

Lifecycle hooks (`register_activation_hook` etc.) stay at file scope in `thrivedesk.php`, outside the `is_admin()` gate, so WP-CLI activation still runs them.

### Admin screen (React)

`admin.php?page=thrivedesk` is a React app built with `wp-scripts`, entry
`resources/js/wp-scripts/thrivedesk-admin-app.js`, using `@wordpress/components`.
WordPress provides `wp-components` as a script and style, so it is a build
external and costs no npm dependency — `Admin::enqueue_admin_app()` reads the
generated `.asset.php` for the handles rather than hardcoding them.

`TabPanel` owns the page: Overview, Integrations, Live Chat, Portal. Integrations
is fully ported and renders from `thrivedesk_integrations()`, bootstrapped into
`window.thrivedeskAdmin`. The other three are still server-rendered PHP in
`includes/views/partials/overview.php` and
`includes/views/partials/settings.php`, split into `#td-panel-*` divs that
`HostedPanel` adopts into their tab — a staging device, not the end state.

Two things that will bite if changed carelessly: every panel is rendered on every
tab with only `hidden` toggling, because unmounting a `HostedPanel` destroys the
markup it adopted; and the save handler reads fields **by id**, not by
serialising the form, which is the only reason panels can live outside it.

### Options

`thrivedesk_options` (integration connections), `td_helpdesk_settings` (API key + helpdesk config), `td_helpdesk_verified`, `td_assistant_settings`, `td_inbox_settings`, `td_user_account_pages`, `td_db_version`.

### Database

One custom table, `{prefix}td_conversations`. Migrations run through `database/DBMigrator.php` → `database/TDConversation.php`, dispatched by `MigrationScript` on `plugins_loaded` (not just activation, so plugin updates reach existing stores) and gated on `THRIVEDESK_DB_VERSION` / `td_db_version`. Sync payloads from the SaaS are funnelled through `ThriveDesk\Data\ConversationSyncData::fromExtra()`, an allowlist of writable columns — untrusted `extra` never reaches `$wpdb` directly.

## Layout

```
thrivedesk.php            Bootstrap: constants, singleton wiring, activation hooks
uninstall.php             Drops table + options on delete
src/
  Api.php                 Inbound ?listener=thrivedesk dispatcher + HMAC verification
  Admin.php               Admin menu, settings pages, connect/disconnect AJAX
  RestRoute.php           REST: thrivedesk/v1/conversations/contact/{id} and thrivedesk/v1/docs
  Abstracts/Plugin.php    Base class for every integration
  Api/ApiResponse.php     JSON success/error envelope for listener replies
  Plugins/                EDD, WooCommerce, FluentCRM, WPPostSync, Autonami
  Conversations/          Ticket list/detail, [thrivedesk_portal] shortcode, most helpdesk AJAX
  Services/TDApiService.php   Outbound HTTP to api.thrivedesk.com
  Services/PortalService.php  Plan allowlist gating WP Portal access
  Services/BusinessHoursService.php, ConnectionState.php, WorkspaceService.php
  Portal/UserAccountPages.php WooCommerce my-account "Support" tab + rewrite rules
  Assistants/, Inboxes/, KnowledgeBase/   Fetch + render SaaS-side resources
  Data/ConversationSyncData.php  Allowlisted decode of SaaS sync payloads
Hooks/FluentCrmHooks.php  Registers ThriveDesk as a FluentCRM ticket provider
database/                 Custom table migrations
includes/helper.php       thrivedesk_view() renderer + shared helpers (registers hooks at file scope)
includes/views/           PHP templates: pages/, partials/, shortcode/, icons/
resources/                Source js/css (mix input)
languages/                thrivedesk.pot + translations
assets/                   Built js/css — committed, do not hand-edit
tests/                    PHPUnit; includes/ = shared test cases, stubs/ = fake plugin APIs, golden/ = snapshots
docs/                     Security audit notes
e2e/                      Playwright specs against a real connected WP site
docker/                   The bench: compose.yml + the image it builds (not shipped)
scripts/dev.sh            Standalone bench CLI: scripts/dev.sh help (not shipped)
scripts/release.sh        Builds releases/thrivedesk.zip
```

## Conventions

- **Translations are gated in three places, and all three matter.** `phpcs-i18n.xml`
  runs `WordPress.WP.I18n` repo-wide (`composer phpcs-i18n`) — a separate ruleset for the
  same reason `phpcs-security.xml` is one: `phpcs.xml`'s path excludes also suppress the
  sniff, and those paths are where the strings live. `.github/workflows/i18n-pot-check.yml`
  fails the PR when `languages/thrivedesk.pot` drifts. And `tests/I18nSetupTest.php`
  pins the plumbing — `Domain Path`, `load_plugin_textdomain()` on `init`, and the third
  argument to `wp_set_script_translations()`. All three of those were broken at once and
  nothing noticed, because the failure mode is an English UI rather than an error.
- **PHPCS is incrementally adopted.** `phpcs.xml` excludes legacy paths one at a time; anything under `src/` *not* listed there is already clean and must stay clean. When you bring an excluded path up to WPCS, delete its exclude line.
- **Listener behaviour is pinned by golden files.** `tests/ListenerGoldenTest.php` snapshots the JSON bodies in `tests/golden/listener/`. Intentional contract changes: regenerate by running `tests/ListenerGoldenTest.php` with `TD_UPDATE_GOLDEN=1` and review the diff. td has no verb that passes that variable (`td wp test` takes no env), so td has no route for it.
- **Test signing must mirror production.** `td_test_sign_payload()` in `tests/includes/listener-helpers.php` reimplements `Api::verify_token()`; keep them in step.
- **E2E is serial and destructive** — one shared site, specs must restore whatever they change. Read `e2e/README.md` before writing one.
- **Version lives in four places** and they must match: the plugin header and `$version` in `thrivedesk.php`, `package.json`, and `Stable tag` in `readme.md` (the wp.org asset workflow greps that exact casing). `readme.md` is copied to `readme.txt` at release; `changelog.txt` drives the GitHub release notes via `.github/scripts/parse-changelog.js`.
- PRs target `develop`.
