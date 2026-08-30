#!/usr/bin/env bash
#
# The ThriveDesk plugin bench.
#
#   scripts/dev.sh up              boot a WordPress with this plugin active
#   scripts/dev.sh down            stop it       scripts/dev.sh reset   wipe it and start over
#   scripts/dev.sh test [args]     the PHPUnit suite      scripts/dev.sh phpcs   every sniff
#   scripts/dev.sh e2e [args]      the browser suite, against this bench
#   scripts/dev.sh e2e-ui          watch it run, live, in Playwright's UI mode
#   scripts/dev.sh e2e-report      the last run's report and traces
#   scripts/dev.sh connect <key>   point the bench at a ThriveDesk account
#   scripts/dev.sh npm <args>      npm, for the plugin's assets
#   scripts/dev.sh pot             regenerate languages/thrivedesk.pot
#   scripts/dev.sh cli <args>      wp-cli        scripts/dev.sh shell   a shell in the container
#   scripts/dev.sh logs            apache + PHP + debug.log
#   scripts/dev.sh doctor          check what is missing
#
# Docker is the only requirement. Everything is idempotent: re-run anything.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# The bench's own accounts. Nothing here is a secret; the site is published on
# the loopback address and is not reachable from anywhere but this machine.
ADMIN_USER="${TD_ADMIN_USER:-admin}"
ADMIN_PASS="${TD_ADMIN_PASS:-password}"
CUSTOMER_USER="${TD_CUSTOMER_USER:-customer}"
CUSTOMER_PASS="${TD_CUSTOMER_PASS:-password}"

PORT="${TD_PORT:-8888}"
BIND_IP="${TD_BIND_IP:-127.0.0.1}"
SITE_URL="http://localhost:$PORT"
export TD_PORT="$PORT" TD_BIND_IP="$BIND_IP"
export TD_ADMIN_USER="$ADMIN_USER" TD_ADMIN_PASS="$ADMIN_PASS"
export TD_CUSTOMER_USER="$CUSTOMER_USER" TD_CUSTOMER_PASS="$CUSTOMER_PASS"

# The image ships one browser build and the client asks for another by revision,
# so a version that drifts fails as "browser not found" a long way from its
# cause. The installed client wins over the pin in package.json, because it is
# the one that will actually launch the browser; when the two disagree,
# node_modules is stale and e2e_deps says so.
PW_PINNED="$(sed -n 's/.*"@playwright\/test"[[:space:]]*:[[:space:]]*"[^0-9]*\([0-9][^"]*\)".*/\1/p' "$ROOT/package.json" | head -n 1)"
PW_INSTALLED=""
if [ -f "$ROOT/node_modules/@playwright/test/package.json" ]; then
    PW_INSTALLED="$(sed -n 's/^[[:space:]]*"version"[[:space:]]*:[[:space:]]*"\([0-9][^"]*\)".*/\1/p' \
        "$ROOT/node_modules/@playwright/test/package.json" | head -n 1)"
fi
if [ -z "${TD_PLAYWRIGHT_IMAGE:-}" ]; then
    pw="${PW_INSTALLED:-$PW_PINNED}"
    if [ -n "$pw" ]; then
        export TD_PLAYWRIGHT_IMAGE="mcr.microsoft.com/playwright:v$pw-noble"
    fi
fi

COMPOSE=(docker compose -f "$ROOT/docker/compose.yml")

# Anything else you want in the bench goes in docker/compose.override.yml, which
# is not tracked. Compose only merges an override automatically when it discovers
# the files itself; we name ours, so we have to add it ourselves.
if [ -f "$ROOT/docker/compose.override.yml" ]; then
    COMPOSE+=(-f "$ROOT/docker/compose.override.yml")
fi

say()  { printf '\n\033[1m==> %s\033[0m\n' "$1"; }
ok()   { printf '    \033[32mok\033[0m %s\n' "$1"; }
warn() { printf '    \033[33m!\033[0m  %s\n' "$1"; }
die()  { printf '\n\033[31mx %s\033[0m\n\n' "$1" >&2; exit 1; }

require_docker() {
    command -v docker >/dev/null 2>&1 || die "Docker is not installed.

    macOS:  brew install --cask docker      (or orbstack)
    Linux:  https://docs.docker.com/engine/install/"
    docker info >/dev/null 2>&1 || die "Docker is installed but not running. Start it and try again."
}

running() {
    [ -n "$("${COMPOSE[@]}" ps -q wordpress 2>/dev/null)" ]
}

require_running() {
    require_docker
    running || die "The bench is not running. Start it with:  scripts/dev.sh up"
}

# Run as www-data, the user that owns the WordPress files. WordPress decides
# whether it may touch the filesystem by comparing the owner of its own files
# against the owner of a file it just created; as root those differ, so it falls
# back to FTP, WP_Filesystem() returns false, and code that trusted it fails
# somewhere far away with a nonsense error.
wp() { "${COMPOSE[@]}" exec -T --user www-data -e HOME=/tmp wordpress wp "$@"; }

# Composer runs inside the container, as you. Inside, because that is where PHP
# lives and nobody should need a matching PHP on the host to boot a bench. As
# you, because vendor/ lands in your working copy, and a root-owned vendor/ is a
# directory you cannot delete without sudo.
install_deps() {
    if [ -f "$ROOT/vendor/autoload.php" ]; then
        ok "composer dependencies present"
        return 0
    fi
    printf '    installing composer dependencies...\n'
    "${COMPOSE[@]}" exec -T --user "$(id -u):$(id -g)" \
        -e COMPOSER_HOME=/tmp/composer-cache \
        -w /var/www/html/wp-content/plugins/thrivedesk \
        wordpress composer install --no-interaction --quiet \
        || die "composer install failed. Look around with:  scripts/dev.sh shell"
    ok "composer dependencies installed"
}

# Everything WordPress-side, in one round trip.
#
# Each `wp` call is a docker exec, and each exec costs about half a second. A
# dozen of them makes a no-op `up` slow enough that people start skipping it, and
# a bench you skip steps on is a bench that is quietly wrong. So the steps all
# stay and the cost goes instead: one shell inside the container, reporting what
# it did.
#
# WooCommerce is not a dependency of this plugin and must never become one. It is
# here because the WooCommerce integration is the largest surface in the codebase
# and the one the inbound listener mutates, and neither the suite nor a reviewer
# can exercise it without the plugin present.
provision() {
    "${COMPOSE[@]}" exec -T --user www-data -e HOME=/tmp \
        -e TD_URL="$SITE_URL" \
        -e TD_ADMIN_USER="$ADMIN_USER" -e TD_ADMIN_PASS="$ADMIN_PASS" \
        -e TD_CUSTOMER_USER="$CUSTOMER_USER" -e TD_CUSTOMER_PASS="$CUSTOMER_PASS" \
        wordpress sh -s <<'SH'
set -u
report() { printf 'TD:%s\n' "$1"; }

if wp core is-installed >/dev/null 2>&1; then
    report 'core=present'
elif wp core install --url="$TD_URL" --title='ThriveDesk Bench' \
        --admin_user="$TD_ADMIN_USER" --admin_password="$TD_ADMIN_PASS" \
        --admin_email='admin@thrivedesk.test' --skip-email >/dev/null 2>&1; then
    report 'core=installed'
else
    report 'core=failed'
fi

if wp plugin activate thrivedesk >/dev/null 2>&1; then
    report 'plugin=active'
else
    report 'plugin=failed'
fi

if wp plugin is-installed woocommerce >/dev/null 2>&1; then
    wp plugin activate woocommerce >/dev/null 2>&1 && report 'woo=active' || report 'woo=failed'
elif wp plugin install woocommerce --activate >/dev/null 2>&1; then
    report 'woo=installed'
else
    report 'woo=unavailable'
fi

# WPSubscription is installed but deliberately left inactive. tests/bootstrap.php
# requires its file directly, so the subscription-cancel tests run against the
# real extension the way they do in CI, without the site growing a second
# subscriptions plugin nobody asked for.
if wp plugin is-installed subscription >/dev/null 2>&1; then
    report 'wpsub=present'
elif wp plugin install subscription >/dev/null 2>&1; then
    report 'wpsub=installed'
else
    report 'wpsub=unavailable'
fi

# The browser suite signs in as a customer and reads their own tickets, so the
# bench needs a non-admin account that exists before the suite runs.
if wp user get "$TD_CUSTOMER_USER" >/dev/null 2>&1; then
    report 'customer=present'
elif wp user create "$TD_CUSTOMER_USER" "$TD_CUSTOMER_USER@thrivedesk.test" \
        --role=customer --user_pass="$TD_CUSTOMER_PASS" >/dev/null 2>&1 \
     || wp user create "$TD_CUSTOMER_USER" "$TD_CUSTOMER_USER@thrivedesk.test" \
        --role=subscriber --user_pass="$TD_CUSTOMER_PASS" >/dev/null 2>&1; then
    report 'customer=created'
else
    report 'customer=failed'
fi

# Activating the plugin publishes the portal page and records its id. If someone
# trashed it, put one back: the portal specs have nothing to look at otherwise.
PAGE_ID="$(wp option get thrivedesk_portal_page_id 2>/dev/null || true)"
if [ -z "$PAGE_ID" ] || ! wp post get "$PAGE_ID" --field=ID >/dev/null 2>&1; then
    PAGE_ID="$(wp post create --post_type=page --post_status=publish \
        --post_title='Thrivedesk Support Portal' \
        --post_content='[thrivedesk_portal]' --porcelain 2>/dev/null || true)"
    [ -n "$PAGE_ID" ] && wp option update thrivedesk_portal_page_id "$PAGE_ID" >/dev/null 2>&1
    report 'portal=created'
else
    report 'portal=present'
fi
[ -n "$PAGE_ID" ] && report "portalpath=/$(wp post get "$PAGE_ID" --field=post_name 2>/dev/null)/"

# Rewrite rules are registered by plugins at activation but only written when
# something flushes them. Until that happens every pretty URL and /wp-json/ route
# a plugin just registered answers 404, with nothing to suggest why.
if [ -z "$(wp option get permalink_structure 2>/dev/null)" ]; then
    wp rewrite structure '/%postname%/' >/dev/null 2>&1 && report 'permalinks=set'
fi
wp rewrite flush --hard >/dev/null 2>&1 && report 'rewrite=flushed' || report 'rewrite=failed'
SH
}

PORTAL_PATH="/thrivedesk-support-portal/"

report_provision() {
    local line
    while IFS= read -r line; do
        case "$line" in
            TD:core=present)     ok "wordpress already installed" ;;
            TD:core=installed)   ok "wordpress installed" ;;
            TD:core=failed)      die "wp core install failed. Look at:  scripts/dev.sh logs" ;;
            TD:plugin=active)    ok "thrivedesk active" ;;
            TD:plugin=failed)    warn "could not activate thrivedesk. Look at: scripts/dev.sh logs" ;;
            TD:woo=active)       ok "woocommerce active" ;;
            TD:woo=installed)    ok "woocommerce installed" ;;
            TD:woo=unavailable)  warn "could not install woocommerce, which needs network access to wordpress.org" ;;
            TD:woo=failed)       warn "woocommerce would not activate. Look at: scripts/dev.sh logs" ;;
            TD:wpsub=present)    ok "wpsubscription present" ;;
            TD:wpsub=installed)  ok "wpsubscription installed (inactive, for the test suite)" ;;
            TD:wpsub=unavailable) warn "could not install wpsubscription, so its cancel tests will skip" ;;
            TD:customer=present) ok "customer account present" ;;
            TD:customer=created) ok "customer account created" ;;
            TD:customer=failed)  warn "could not create the customer account, which the portal specs need" ;;
            TD:portal=created)   ok "portal page created" ;;
            TD:portal=present)   ok "portal page present" ;;
            TD:portalpath=*)     PORTAL_PATH="${line#TD:portalpath=}" ;;
            TD:permalinks=set)   ok "permalinks set to /%postname%/" ;;
            TD:rewrite=flushed)  ok "rewrite rules flushed" ;;
            TD:rewrite=failed)   warn "could not flush rewrite rules, so pretty URLs and /wp-json/ may 404" ;;
        esac
    done
}

cmd_up() {
    require_docker

    say "Starting the bench"
    "${COMPOSE[@]}" up -d --build

    say "Waiting for WordPress"
    local i=0
    until "${COMPOSE[@]}" exec -T wordpress wp --allow-root core version >/dev/null 2>&1; do
        i=$(( i + 1 ))
        [ "$i" -gt 60 ] && die "WordPress did not come up. Look at:  scripts/dev.sh logs"
        sleep 2
    done
    ok "wordpress $("${COMPOSE[@]}" exec -T wordpress wp --allow-root core version 2>/dev/null | tr -d '\r')"

    # Anything that ran as root before could have left root-owned directories in
    # the volume, and www-data then cannot write its own uploads.
    "${COMPOSE[@]}" exec -T --user root wordpress sh -c \
        'mkdir -p /var/www/html/wp-content/uploads && chown -R www-data:www-data /var/www/html/wp-content/uploads' \
        >/dev/null 2>&1 || true

    say "Installing dependencies"
    install_deps

    say "Setting up WordPress"
    report_provision < <(provision)

    say "Ready"
    printf '    %s/wp-admin    %s / %s\n' "$SITE_URL" "$ADMIN_USER" "$ADMIN_PASS"
    printf '    %s%s\n' "$SITE_URL" "$PORTAL_PATH"
    printf '\n    The plugin is not connected to a ThriveDesk account yet:\n'
    printf '      scripts/dev.sh connect <api-key>\n\n'
}

cmd_down() { require_docker; "${COMPOSE[@]}" stop; }

cmd_reset() {
    require_docker
    printf 'This wipes the database and the WordPress volume. Your code is untouched. [y/N] '
    read -r answer
    [ "$answer" = "y" ] || { echo "cancelled"; return 0; }
    "${COMPOSE[@]}" down -v
    cmd_up
}

# Apache and PHP write to the container's stdout; WordPress writes fatals to
# wp-content/debug.log, which never reaches it. Showing only the first would
# leave out the white-screen case, which is the one reason anybody runs this.
cmd_logs() {
    require_running
    "${COMPOSE[@]}" exec -T --user www-data wordpress \
        sh -c 'touch /var/www/html/wp-content/debug.log' >/dev/null 2>&1 || true

    "${COMPOSE[@]}" exec -T wordpress tail -F -n 50 /var/www/html/wp-content/debug.log 2>/dev/null \
        | sed 's/^/debug.log  | /' &
    local tail_pid=$!
    # shellcheck disable=SC2064 # expand the pid now, not when the trap fires.
    trap "kill $tail_pid 2>/dev/null || true" EXIT INT TERM

    "${COMPOSE[@]}" logs -f --tail=50 wordpress
}

cmd_shell() { require_running; "${COMPOSE[@]}" exec wordpress bash; }
cmd_cli()   { require_running; wp "$@"; }

# Point the bench at a real ThriveDesk workspace. The plugin is useless without
# one, every outbound call authenticates with this key, and there is no way to
# fake it, so this is its own command rather than part of `up`.
#
# It ends where pressing Verify on the settings screen ends, and by the same
# route: the plugin's own methods, in the order the screen calls them. Storing
# the key is not connecting, and the screen cannot finish the job for you either,
# because it deliberately never renders a stored key back into the field.
cmd_connect() {
    require_running
    local key="${1:-}"
    [ -n "$key" ] || die "usage: scripts/dev.sh connect <api-key>

    Get one from https://app.thrivedesk.com, under Settings then API."

    # Through the environment, not as an argument: `wp eval` takes exactly one
    # positional and rejects a second, and a key on the command line would sit in
    # shell history besides.
    say "Connecting"
    # shellcheck disable=SC2016 # PHP, not shell: nothing here is ours to expand.
    "${COMPOSE[@]}" exec -T --user www-data -e HOME=/tmp -e TD_API_KEY="$key" wordpress \
        wp eval '
            $key = getenv( "TD_API_KEY" );

            /* Ask before storing, exactly as the screen does: a key that fails
               must not become the key on file. */
            if ( empty( \ThriveDesk\Conversations\Conversation::get_system_info( $key ) ) ) {
                WP_CLI::error( "ThriveDesk rejected that key" );
            }

            /* Storing clears the assistant, inbox and knowledge base, and blanks
               the account details, so the details are fetched again after. */
            \ThriveDesk\Conversations\Conversation::instance()->reset_td_settings( $key );
            \ThriveDesk\Conversations\Conversation::get_system_info( $key );
        ' || die "could not connect. Check the key, or look at:  scripts/dev.sh logs"

    ok "connected"
    printf '\n    Pick an assistant and an inbox at\n'
    printf '      %s/wp-admin/admin.php?page=thrivedesk\n\n' "$SITE_URL"
}

# The PHPUnit suite, inside the container, against the bench's own MariaDB.
#
# No SVN export and no separate test-library download: this repository already
# has wp-phpunit as a dev dependency and tests/bootstrap.php finds it in vendor/.
# All it needs from us is a database and a config naming it.
cmd_test() {
    require_running
    install_deps

    # Created here rather than by an entrypoint script, because
    # docker-entrypoint-initdb.d only runs on an empty data directory: a bench
    # that already exists would never get the database, and the failure surfaces
    # much later as "Unknown database 'wordpress_test'". MYSQL_PWD keeps the
    # password off the command line, where the client warns about it.
    "${COMPOSE[@]}" exec -T -e MYSQL_PWD=root mariadb \
        mariadb -uroot -e "CREATE DATABASE IF NOT EXISTS wordpress_test;
                           GRANT ALL ON wordpress_test.* TO 'wordpress'@'%';" \
        || die "could not create the test database"

    # WP_TESTS_DOMAIN matches CI (.github/workflows/phpunit.yml) rather than the
    # bench's own address: tests that build URLs should behave here exactly as
    # they do there.
    # shellcheck disable=SC2016 # the heredoc is written by the container's shell.
    "${COMPOSE[@]}" exec -T --user www-data wordpress sh -c 'cat > /tmp/wp-tests-config.php <<"PHP"
<?php
define( "ABSPATH", "/var/www/html/" );
define( "WP_DEFAULT_THEME", "default" );
define( "DB_NAME", "wordpress_test" );
define( "DB_USER", "wordpress" );
define( "DB_PASSWORD", "wordpress" );
define( "DB_HOST", "mariadb" );
define( "DB_CHARSET", "utf8" );
define( "DB_COLLATE", "" );
$table_prefix = "wptests_";
define( "WP_TESTS_DOMAIN", "example.org" );
define( "WP_TESTS_EMAIL", "admin@example.org" );
define( "WP_TESTS_TITLE", "ThriveDesk Tests" );
define( "WP_PHP_BINARY", "php" );
PHP'

    say "PHPUnit"
    # The result cache goes to /tmp. PHPUnit writes it beside phpunit.xml by
    # default, and www-data cannot write into your working copy, so the run ends
    # on a file_put_contents warning that has nothing to do with the tests.
    "${COMPOSE[@]}" exec -T --user www-data -e HOME=/tmp \
        -e WP_PHPUNIT__TESTS_CONFIG=/tmp/wp-tests-config.php \
        -w /var/www/html/wp-content/plugins/thrivedesk \
        wordpress vendor/bin/phpunit --cache-result-file=/tmp/.phpunit.result.cache "$@"
}

cmd_phpcs() {
    require_running
    install_deps
    local run=("${COMPOSE[@]}" exec -T --user "$(id -u):$(id -g)"
        -e COMPOSER_HOME=/tmp/composer-cache
        -w /var/www/html/wp-content/plugins/thrivedesk wordpress)

    say "PHPCS"
    "${run[@]}" vendor/bin/phpcs "$@"
    say "PHPCS - security"
    "${run[@]}" composer phpcs-security
    say "PHPCS - i18n"
    "${run[@]}" composer phpcs-i18n
    # The declared floor is PHP 7.4 (readme "Requires PHP"), and the bench runs
    # something newer, so nothing here would notice a union type or a match()
    # until CI did. This sniff is the check that does.
    say "PHP 7.4 compatibility"
    "${run[@]}" composer phpcompat
}

cmd_npm() {
    require_docker
    "${COMPOSE[@]}" run --rm --user "$(id -u):$(id -g)" node npm "$@"
}

# The translation template, which CI compares against the committed one and
# fails the pull request when they differ. The exclude list is the one in
# .github/workflows/i18n-pot-check.yml and has to stay in step with it. Peast,
# the JavaScript parser make-pot uses, walks the admin bundles and wants more
# memory than a request would ever need, so this run gets its own limit.
cmd_pot() {
    require_running
    say "make-pot"
    "${COMPOSE[@]}" exec -T --user "$(id -u):$(id -g)" -e HOME=/tmp \
        -w /var/www/html/wp-content/plugins/thrivedesk wordpress \
        php -d memory_limit=2G /usr/local/bin/wp i18n make-pot . languages/thrivedesk.pot \
            --domain=thrivedesk --exclude=vendor,node_modules,tests,docker,plugins,assets
}

# The browsers come with the Playwright image; only the node packages are needed
# here, and they are the ones package.json already pins.
e2e_deps() {
    if [ ! -d "$ROOT/node_modules/@playwright" ]; then
        say "Installing node dependencies"
        "${COMPOSE[@]}" run --rm --user "$(id -u):$(id -g)" \
            -e PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 \
            playwright npm ci --no-audit --no-fund
        return 0
    fi

    if [ -n "$PW_INSTALLED" ] && [ -n "$PW_PINNED" ] && [ "$PW_INSTALLED" != "$PW_PINNED" ]; then
        warn "playwright $PW_INSTALLED is installed but package.json pins $PW_PINNED"
        warn "the run uses $PW_INSTALLED; to match CI:  scripts/dev.sh npm ci"
    fi
}

e2e_run() {
    require_running
    e2e_deps
    "${COMPOSE[@]}" run --rm --user "$(id -u):$(id -g)" "$@"
}

# The browser suite. Playwright runs in its own container rather than on the
# host: there is no node here, and the official image already carries the
# browsers. It reaches the site by service name over the bench network, which
# works because WP_HOME follows the host the request carried.
cmd_e2e() {
    say "End-to-end"
    warn "the connected specs need an account: scripts/dev.sh connect <api-key>"
    e2e_run playwright npx playwright test "$@"
}

# Watch the run, live. The browser is inside the container and there is no
# display there to show it on, but UI mode is a server: bound to 0.0.0.0 and
# published, it is opened from a browser here.
cmd_e2e_ui() {
    local port="${TD_E2E_UI_PORT:-8080}"
    say "UI mode - http://localhost:$port"
    printf '    Open that in a browser. Ctrl-C here stops it.\n\n'
    e2e_run -p "127.0.0.1:$port:$port" \
        playwright npx playwright test --ui-host 0.0.0.0 --ui-port "$port" "$@"
}

cmd_e2e_report() {
    local port="${TD_E2E_REPORT_PORT:-9323}"
    [ -d "$ROOT/playwright-report" ] || die "No report yet. Run scripts/dev.sh e2e first."
    say "Report - http://localhost:$port"
    printf '    Ctrl-C here stops it.\n\n'
    e2e_run -p "127.0.0.1:$port:$port" \
        playwright npx playwright show-report playwright-report --host 0.0.0.0 --port "$port"
}

cmd_doctor() {
    if command -v docker >/dev/null 2>&1; then ok "docker installed"; else warn "docker missing"; fi
    if docker info >/dev/null 2>&1; then ok "docker running"; else warn "docker not running"; fi

    if [ -f "$ROOT/vendor/autoload.php" ]; then
        ok "composer dependencies installed"
    else
        warn "composer dependencies missing, run: scripts/dev.sh up"
    fi

    if [ -d "$ROOT/node_modules/@playwright" ]; then
        ok "playwright installed"
    else
        warn "playwright missing, installed on the first: scripts/dev.sh e2e"
    fi

    if running; then
        ok "bench running at $SITE_URL"
        local key
        key="$(wp option pluck td_helpdesk_settings td_helpdesk_api_key 2>/dev/null | tr -d '\r' || true)"
        if [ -n "$key" ]; then
            ok "an api key is stored"
        else
            warn "no api key, run: scripts/dev.sh connect <api-key>"
        fi
    else
        warn "bench not running, start it with: scripts/dev.sh up"
    fi

    "${COMPOSE[@]}" ps 2>/dev/null | tail -n +2 | sed 's/^/    /' || true
}

case "${1:-up}" in
    up)          shift; cmd_up "$@" ;;
    install)     shift; require_running; install_deps ;;
    down)        shift; cmd_down "$@" ;;
    reset)       shift; cmd_reset "$@" ;;
    logs)        shift; cmd_logs "$@" ;;
    shell)       shift; cmd_shell "$@" ;;
    cli)         shift; cmd_cli "$@" ;;
    connect)     shift; cmd_connect "$@" ;;
    test)        shift; cmd_test "$@" ;;
    phpcs)       shift; cmd_phpcs "$@" ;;
    npm)         shift; cmd_npm "$@" ;;
    pot)         shift; cmd_pot "$@" ;;
    e2e)         shift; cmd_e2e "$@" ;;
    e2e-ui)      shift; cmd_e2e_ui "$@" ;;
    e2e-report)  shift; cmd_e2e_report ;;
    doctor)      shift; cmd_doctor "$@" ;;
    # Read from the header comment itself rather than a line range, so editing
    # the help cannot silently start printing the code below it.
    -h|--help|help) awk 'NR>2 && /^#/ { sub(/^# ?/, ""); print; next } NR>2 { exit }' "${BASH_SOURCE[0]}" ;;
    *)
        # Guessing a name is normal; making someone read the whole help to find
        # out they were one letter off is not.
        near="$(printf 'up\ndown\nreset\nlogs\nshell\ncli\nconnect\ntest\nphpcs\nnpm\npot\ne2e\ninstall\ndoctor\n' \
            | awk -v w="$1" 'index($0, substr(w,1,3)) == 1 || index(w, $0) == 1 {print; exit}')"
        if [ -n "$near" ]; then
            die "unknown command '$1'. Did you mean scripts/dev.sh $near?"
        fi
        die "unknown command '$1'. Try scripts/dev.sh help"
        ;;
esac
