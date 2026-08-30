# Contributing

Pull requests target `develop`. Everything below runs in Docker, so the only
thing you need installed is Docker itself.

## A WordPress to work against

`scripts/dev.sh` boots one with this plugin active and both test suites wired to
it.

```
git clone https://github.com/thrivedesk/wp-thrivedesk.git
cd wp-thrivedesk
scripts/dev.sh up
```

It prints the address (`http://localhost:8888` unless you set `TD_PORT`), the
admin login, and the portal page it published. WordPress lives in a volume and
only the plugin comes from your working copy, so an edit is live with no copy
step. Everything is idempotent: re-run anything, any time.

`scripts/dev.sh help` lists the rest. The ones you will want are `logs` (apache,
PHP and `wp-content/debug.log` together), `cli` for wp-cli, `shell`, `doctor`
when something looks wrong, and `reset` to wipe the site and start over.

## Tests

```
scripts/dev.sh test                                   # everything
scripts/dev.sh test tests/HmacSignatureTest.php       # one file
scripts/dev.sh test --filter test_rejects_a_bad_token # one test
```

The suite runs inside the bench against its own database. WordPress comes from
the container, the test framework from `vendor/` (`wp-phpunit` is a dev
dependency), and WooCommerce and WPSubscription from the plugins the bench
installs, so the integration tests run here rather than skipping.

Write the test first, watch it fail, then make it pass. A test that passes
against the unfixed code is not testing the fix.

## Sniffs

```
scripts/dev.sh phpcs
```

Four rulesets, all of them gates on your pull request: formatting
(`phpcs.xml`, adopted path by path, so anything not excluded there must stay
clean), security, translations, and PHP 7.4 compatibility. The plugin's declared
floor is PHP 7.4 while the bench runs something newer, so that last one is the
only thing standing between a union type and a fatal error on a supported site.

## Translations

Every user-facing string goes through a translation function with the
`thrivedesk` text domain. If you add, change or move one, regenerate the
template:

```
scripts/dev.sh pot
```

CI fails the pull request when `languages/thrivedesk.pot` and the source
disagree.

## Assets

`resources/` is the source and `assets/` is the build output, which is
committed. Rebuild and include the result:

```
scripts/dev.sh npm run build
```

## Browser tests

The Playwright specs drive a real, connected site: the admin screens and the
support portal, nothing stubbed. Connect the bench to a ThriveDesk account
first, then pick an assistant and an inbox on the settings screen.

```
scripts/dev.sh connect <api-key>
scripts/dev.sh e2e
scripts/dev.sh e2e-ui        # watch it run
scripts/dev.sh e2e-report    # the last run, with traces
```

Unconnected, the specs that drive a connected screen fail and the portal ones
skip themselves. `e2e/README.md` explains what the account needs and how to
write a spec that puts back what it changes.

## Testing against another plugin

Anything on wordpress.org needs nothing special:

```
scripts/dev.sh cli plugin install easy-digital-downloads --activate
```

For something from disk, clone it into `plugins/` (untracked) and mount it from
`docker/compose.override.yml` (untracked too, merged automatically):

```yaml
services:
  wordpress:
    volumes:
      - ../plugins/their-plugin:/var/www/html/wp-content/plugins/their-plugin
```

## Without Docker

Nothing here is required. `composer install` and then a WordPress test
environment of your own works too: `tests/bootstrap.php` takes `WP_TESTS_DIR` or
`WP_PHPUNIT__DIR` plus a `wp-tests-config.php`, and
`.github/workflows/phpunit.yml` assembles exactly that, end to end. The browser
suite is `npm run e2e` with the variables in `e2e/README.md`.

## What a pull request needs

- Tests that fail without your change and pass with it.
- `scripts/dev.sh test` and `scripts/dev.sh phpcs` clean.
- The POT regenerated if you touched a string.
- Rebuilt `assets/` if you touched `resources/`.
- An entry at the top of `changelog.txt`, and the same line in `readme.md`.
- One logical change. A fix and an unrelated refactor are two pull requests.
