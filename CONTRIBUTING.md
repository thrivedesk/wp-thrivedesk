# Contributing

## Running the tests

The PHPUnit suite lives under `tests/` and runs against the WordPress core test
framework (the `wp-phpunit/wp-phpunit` dev dependency). You'll need a real
MySQL/MariaDB database, and the WooCommerce tests need a real WooCommerce
install.

### Prerequisites

- PHP 7.4+ (CI runs on 8.4)
- Composer
- A MySQL or MariaDB server you can create a throwaway test DB on

### Set up the test environment

Install the dev deps, then have the helper script grab WordPress + WooCommerce
and write out `wp-tests-config.php`:

```bash
composer install

export WP_PHPUNIT__TESTS_CONFIG=/tmp/wp-tests-config.php
bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
```

Local example:

```bash
bin/install-wp-tests.sh wordpress_test root root 127.0.0.1
```

The script is idempotent: if a piece is already there it skips it, so it's
safe to re-run. Make sure `WP_PHPUNIT__TESTS_CONFIG` points at the same path
when you run the suite below.

### Run

```bash
composer test
```

`composer test` runs PHPUnit against `phpunit.xml`. Pass extra args after `--`,
e.g. a single class:

```bash
composer test -- --filter TD_Support_Endpoint_Rewrite_Test
```

WC-dependent tests skip themselves when WooCommerce isn't loaded, so the suite
passes fine without it, but the install script pulls WooCommerce in so they
actually execute.

## Coding standards

WordPress Coding Standards. Check your changes with:

```bash
composer phpcs
```
