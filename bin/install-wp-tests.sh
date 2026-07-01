#!/usr/bin/env bash
#
# Spin up a WordPress + WooCommerce env for the plugin's PHPUnit suite and
# drop a wp-tests-config.php where the framework expects it.
#
# wp-phpunit itself comes in via Composer; we only have to fetch WordPress core
# (for ABSPATH) and WooCommerce (otherwise the WC integration tests just skip),
# then point the config at them. Run `composer install` first.
#
# Usage: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
#
# Config path defaults to $TMPDIR/wp-tests-config.php. Override with
# WP_PHPUNIT__TESTS_CONFIG and pass the same value to PHPUnit.

set -euo pipefail

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]" >&2
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4:-localhost}
WP_VERSION=${5:-latest}

TMPDIR=${TMPDIR:-/tmp}
TMPDIR=${TMPDIR%/}
WP_CORE_DIR=${WP_CORE_DIR:-$TMPDIR/wordpress}
PLUGIN_DIR="$WP_CORE_DIR/wp-content/plugins"
CONFIG_PATH=${WP_PHPUNIT__TESTS_CONFIG:-$TMPDIR/wp-tests-config.php}

install_wp() {
	if [ -f "$WP_CORE_DIR/wp-settings.php" ]; then
		return
	fi

	local archive="https://wordpress.org/latest.tar.gz"
	if [ "$WP_VERSION" != 'latest' ]; then
		archive="https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
	fi

	mkdir -p "$WP_CORE_DIR"
	curl -sSL "$archive" | tar --strip-components=1 -xz -C "$WP_CORE_DIR"
}

install_woocommerce() {
	if [ -f "$PLUGIN_DIR/woocommerce/woocommerce.php" ]; then
		return
	fi

	mkdir -p "$PLUGIN_DIR"
	curl -sSL "https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip" -o "$TMPDIR/woocommerce.zip"
	unzip -q "$TMPDIR/woocommerce.zip" -d "$PLUGIN_DIR"
}

# WC integration tests set _order_number meta directly, so no
# sequential-numbering plugin is needed. Plain WooCommerce is enough.
write_config() {
	cat > "$CONFIG_PATH" <<PHP
<?php
define( 'ABSPATH', '$WP_CORE_DIR/' );
define( 'WP_DEFAULT_THEME', 'default' );

define( 'DB_NAME', '$DB_NAME' );
define( 'DB_USER', '$DB_USER' );
define( 'DB_PASSWORD', '$DB_PASS' );
define( 'DB_HOST', '$DB_HOST' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

\$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
PHP
}

# Best-effort: CI creates the DB via the service's MYSQL_DATABASE, so this
# is really only for local runs against an already-running server.
create_db() {
	if command -v mysqladmin >/dev/null 2>&1; then
		mysqladmin create "$DB_NAME" \
			--user="$DB_USER" --password="$DB_PASS" \
			--host="$DB_HOST" --protocol=tcp 2>/dev/null || true
	fi
}

install_wp
install_woocommerce
write_config
create_db

echo "Test environment ready:"
echo "  WordPress:   $WP_CORE_DIR ($WP_VERSION)"
echo "  WooCommerce: $PLUGIN_DIR/woocommerce"
echo "  Config:      $CONFIG_PATH"
