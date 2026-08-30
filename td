#!/usr/bin/env bash
#
# The ThriveDesk bench.
#
#   ./td up              boot everything and install WordPress
#   ./td down            stop it            ./td reset    wipe the database and start over
#   ./td cli <args>      wp-cli             ./td logs     apache + PHP + debug.log
#   ./td test [args]     the PHPUnit suite  ./td shell    a shell in the WordPress container
#   ./td e2e [args]      the end-to-end suite, in a browser, against this bench
#   ./td e2e-ui          watch it run, live, in Playwright's UI mode
#   ./td e2e-report      the last run's report and traces
#   ./td connect <key>   point the bench at a ThriveDesk account
#   ./td secure          make the URL a real https:// one (a no-op on OrbStack)
#   ./td trust           trust the local certificate authority (not needed on OrbStack)
#   ./td install         composer install for the plugin
#   ./td doctor          check what is missing
#
# Everything is idempotent. Re-run anything.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Two names, because two runtimes answer differently. Under OrbStack the .local
# name resolves to the container itself — no hosts file, no sudo, and port 443
# belongs to the container rather than to whatever already owns it on the host.
# Everywhere else the .test name needs an /etc/hosts entry and a published port.
DOMAIN_LOCAL="thrivedesk.local"
DOMAIN="thrivedesk.test"

# Pinned to the @playwright/test version in package.json. A mismatch is the
# classic "browser not found" failure: the image ships one browser build and the
# client asks for another by revision.
PLAYWRIGHT_IMAGE="mcr.microsoft.com/playwright:v1.62.1-noble"

# The bench's own accounts. Nothing here is a secret; the site is not reachable
# from anywhere but this machine.
ADMIN_USER="admin"
ADMIN_PASS="password"
CUSTOMER_USER="customer"
CUSTOMER_PASS="password"

COMPOSE=(docker compose -f "$ROOT/docker/compose.yml")

# Anything else you want in the bench goes in docker/compose.override.yml, which
# is not tracked. Compose only merges an override automatically when it discovers
# the files itself; we name them, so we have to add it ourselves.
[ -f "$ROOT/docker/compose.override.yml" ] && COMPOSE+=(-f "$ROOT/docker/compose.override.yml")

# OrbStack does not put docker on PATH for non-interactive shells.
[ -d "$HOME/.orbstack/bin" ] && export PATH="$HOME/.orbstack/bin:$PATH"

say()  { printf '\n\033[1m==> %s\033[0m\n' "$1"; }
ok()   { printf '    \033[32m✓\033[0m %s\n' "$1"; }
warn() { printf '    \033[33m!\033[0m %s\n' "$1"; }
die()  { printf '\n\033[31mx %s\033[0m\n\n' "$1" >&2; exit 1; }

require_docker() {
    command -v docker >/dev/null 2>&1 || die "Docker is not installed.

    macOS:  brew install --cask orbstack
    Linux:  https://docs.docker.com/engine/install/"
    docker info >/dev/null 2>&1 || die "Docker is installed but not running. Start OrbStack (or Docker Desktop)."
}

# No pipe here, deliberately. `docker info | grep -q orbstack` looks obvious and
# is wrong under `set -o pipefail`: grep -q exits at the first match, docker info
# takes SIGPIPE, and the pipeline reports failure for a search that succeeded.
# Memoised because docker info is not fast and this is asked repeatedly.
ORBSTACK=""
is_orbstack() {
    if [ -z "$ORBSTACK" ]; then
        case "$(docker info 2>/dev/null)" in
            *OrbStack*|*orbstack*) ORBSTACK=yes ;;
            *)                     ORBSTACK=no ;;
        esac
    fi
    [ "$ORBSTACK" = yes ]
}

# Caddy is the only thing that binds host ports, so it is the only collision we
# can have — and :443 is exactly the port another local stack wants.
#
# Two sources, because neither sees the other's ports. lsof sees host processes
# but NOT containers under OrbStack, which publishes from inside its VM: another
# bench can hold :443 while lsof reports it free, and compose then fails at
# container start with "port is already allocated" — after the database and
# WordPress are already up. `docker ps` sees exactly those, and nothing else.
port_free() {
    local ip="${BIND_IP:-127.0.0.1}"

    if [ "$ip" = "0.0.0.0" ]; then
        lsof -nP -iTCP:"$1" -sTCP:LISTEN >/dev/null 2>&1 && return 1
    else
        lsof -nP -iTCP@"$ip":"$1" -sTCP:LISTEN >/dev/null 2>&1 && return 1
    fi

    # Our own caddy holding the port is not a conflict; re-running ./td up must
    # land on the same URL rather than walking to the next free pair.
    local ours mine
    ours="$("${COMPOSE[@]}" ps -q caddy 2>/dev/null || true)"
    while IFS= read -r line; do
        [ -n "$line" ] || continue
        mine="${line%% *}"
        [ "$mine" = "${ours:0:12}" ] && continue
        case "${line#* }" in
            *":$1->"*) return 1 ;;
        esac
    done <<EOF
$(docker ps --format '{{.ID}} {{.Ports}}' 2>/dev/null)
EOF
    return 0
}

# `compose ps --format {{.Publishers}}` prints a Go struct, not host:port, so it
# cannot be pattern-matched for a port. `compose port` answers the question.
port_is_ours() {
    [ "$( "${COMPOSE[@]}" port caddy 443 2>/dev/null | sed 's/.*://' )" = "$1" ]
}

# The port is chosen once and then remembered, because WordPress stores its own
# address: a bench that picked a new port on every restart would rewrite every
# URL in the database each time. Compose reads this file too, so a bare
# `docker compose up` lands on the same ports as ./td.
ENV_FILE="$ROOT/docker/.env"

remember_ports() {
    cat > "$ENV_FILE" <<ENV
# Written by ./td. Delete this file to have the address and ports chosen again.
TD_BIND_IP=${BIND_IP:-127.0.0.1}
TD_HTTP_PORT=$HTTP_PORT
TD_HTTPS_PORT=$HTTPS_PORT
TD_SITE_URL=$(site_url)
ENV
}

pick_ports() {
    BIND_IP="${TD_BIND_IP:-127.0.0.1}"
    export TD_BIND_IP="$BIND_IP"

    # An explicit request wins, free or not.
    if [ -n "${TD_HTTP_PORT:-}" ] || [ -n "${TD_HTTPS_PORT:-}" ]; then
        HTTP_PORT="${TD_HTTP_PORT:-80}"
        HTTPS_PORT="${TD_HTTPS_PORT:-443}"
        remember_ports
        return 0
    fi

    if [ -f "$ENV_FILE" ]; then
        # shellcheck disable=SC1090
        . "$ENV_FILE"
        HTTP_PORT="$TD_HTTP_PORT"
        HTTPS_PORT="$TD_HTTPS_PORT"
        if port_free "$HTTPS_PORT" || port_is_ours "$HTTPS_PORT"; then
            return 0
        fi
        warn "the remembered port $HTTPS_PORT is now taken by something else"
    fi

    # Under OrbStack the site is reached by name through the container, and the
    # published ports are a fallback nobody uses — so there is no reason to take
    # 80/443 from another bench that does need them.
    if ! is_orbstack; then
        HTTP_PORT=80
        HTTPS_PORT=443
        if port_free "$HTTP_PORT" && port_free "$HTTPS_PORT"; then remember_ports; return 0; fi
    fi

    local h=8080 s=8443 n=0
    while [ "$n" -lt 20 ]; do
        if port_free "$h" && port_free "$s"; then
            HTTP_PORT="$h"; HTTPS_PORT="$s"
            # Under OrbStack the published port is a fallback nobody uses, so
            # saying 80 was taken would be noise about something that does not matter.
            is_orbstack || warn "80/443 are taken (by $(lsof -nP -iTCP:443 -sTCP:LISTEN 2>/dev/null | awk 'NR==2{print $1}')) — using $h/$s"
            remember_ports
            return 0
        fi
        h=$(( h + 1 )); s=$(( s + 1 )); n=$(( n + 1 ))
    done
    die "No free port pair found between 8080/8443 and 8100/8463."
}

site_url() {
    # Under OrbStack the container's own name is the address, so the port never
    # enters into it.
    if is_orbstack; then printf 'https://%s' "$DOMAIN_LOCAL"; return 0; fi

    if [ "${HTTPS_PORT:-443}" = "443" ]; then printf 'https://%s' "$DOMAIN"
    else printf 'https://%s:%s' "$DOMAIN" "$HTTPS_PORT"; fi
}

# Run as www-data, the user that owns the WordPress files. WordPress decides
# whether it may touch the filesystem by comparing the owner of its own files
# against the owner of a file it just created; as root those differ, so it falls
# back to FTP, WP_Filesystem() returns false, and code that trusted it fails
# somewhere far away with a nonsense error.
wp() { "${COMPOSE[@]}" exec -T --user www-data -e HOME=/tmp wordpress wp "$@"; }

# Composer runs inside the container, as you. Inside, because that is where PHP
# lives — nobody should need a matching PHP on the host to boot a bench. As you,
# because vendor/ lands in your working copy, and a root-owned vendor/ is a
# directory you cannot delete without sudo.
install_deps() {
    if [ -f "$ROOT/vendor/autoload.php" ]; then
        ok "composer dependencies present"
        return 0
    fi
    printf '    installing composer dependencies…\n'
    "${COMPOSE[@]}" exec -T --user "$(id -u):$(id -g)" \
        -e COMPOSER_HOME=/tmp/composer-cache \
        -w /var/www/html/wp-content/plugins/thrivedesk \
        wordpress composer install --no-interaction --quiet \
        || die "composer install failed. Try:  ./td shell"
    ok "composer dependencies installed"
}

# Everything WordPress-side, in one round trip.
#
# Each `wp` call is a docker exec, and each exec costs about half a second. A
# dozen of them makes a no-op `./td up` slow enough that people start skipping
# it — and a bench you skip steps on is a bench that is quietly wrong. So the
# steps all stay, and the cost goes instead: one shell inside the container,
# reporting what it did.
#
# WooCommerce is not a dependency of this plugin and must never become one. It is
# here because the WooCommerce integration — the largest surface in the codebase,
# and the one the inbound listener mutates — cannot be run or reviewed without it.
provision() {
    "${COMPOSE[@]}" exec -T --user www-data -e HOME=/tmp \
        -e TD_URL="$TD_SITE_URL" \
        -e TD_ADMIN_USER="$ADMIN_USER" -e TD_ADMIN_PASS="$ADMIN_PASS" \
        -e TD_CUSTOMER_USER="$CUSTOMER_USER" -e TD_CUSTOMER_PASS="$CUSTOMER_PASS" \
        -e TD_WITH_WOO="${TD_WITH_WOO:-1}" \
        wordpress sh -s <<'SH'
set -u
report() { printf 'TD:%s\n' "$1"; }

if wp core is-installed >/dev/null 2>&1; then
    report 'core=present'
elif wp core install --url="$TD_URL" --title='ThriveDesk Bench' \
        --admin_user="$TD_ADMIN_USER" --admin_password="$TD_ADMIN_PASS" \
        --admin_email="admin@thrivedesk.local" --skip-email >/dev/null 2>&1; then
    report 'core=installed'
else
    report 'core=failed'
fi

# WordPress stores its own address, and ours can move between runs.
wp option update home "$TD_URL"    >/dev/null 2>&1 || true
wp option update siteurl "$TD_URL" >/dev/null 2>&1 || true

if wp plugin activate thrivedesk >/dev/null 2>&1; then
    report 'plugin=active'
else
    report 'plugin=failed'
fi

if [ "$TD_WITH_WOO" = "1" ]; then
    if wp plugin is-installed woocommerce >/dev/null 2>&1; then
        wp plugin activate woocommerce >/dev/null 2>&1 && report 'woo=active' || report 'woo=failed'
    elif wp plugin install woocommerce --activate >/dev/null 2>&1; then
        report 'woo=installed'
    else
        report 'woo=unavailable'
    fi
fi

# The e2e suite signs in as a customer and reads their own tickets, so the bench
# needs a non-admin account that exists before the suite runs.
if wp user get "$TD_CUSTOMER_USER" >/dev/null 2>&1; then
    report 'customer=present'
elif wp user create "$TD_CUSTOMER_USER" "$TD_CUSTOMER_USER@thrivedesk.local" \
        --role=customer --user_pass="$TD_CUSTOMER_PASS" >/dev/null 2>&1 \
     || wp user create "$TD_CUSTOMER_USER" "$TD_CUSTOMER_USER@thrivedesk.local" \
        --role=subscriber --user_pass="$TD_CUSTOMER_PASS" >/dev/null 2>&1; then
    report 'customer=created'
else
    report 'customer=failed'
fi

# Activating the plugin publishes the portal page and records its id. If someone
# trashed it, put one back — the portal specs have nothing to look at otherwise.
PAGE_ID="$(wp option get thrivedesk_portal_page_id 2>/dev/null || true)"
if [ -z "$PAGE_ID" ] || ! wp post get "$PAGE_ID" --field=ID >/dev/null 2>&1; then
    PAGE_ID="$(wp post create --post_type=page --post_status=publish \
        --post_title='ThriveDesk Support Portal' \
        --post_content='[thrivedesk_portal]' --porcelain 2>/dev/null || true)"
    [ -n "$PAGE_ID" ] && wp option update thrivedesk_portal_page_id "$PAGE_ID" >/dev/null 2>&1
    report 'portal=created'
else
    report 'portal=present'
fi
[ -n "$PAGE_ID" ] && report "portalpath=/$(wp post get "$PAGE_ID" --field=post_name 2>/dev/null)/"

# Rewrite rules are registered by plugins at activation but only written when
# something flushes them. Until that happens every pretty URL and /wp-json/ route
# a plugin just registered answers 404 — with nothing to suggest why.
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
            TD:core=present)      ok "wordpress already installed" ;;
            TD:core=installed)    ok "wordpress installed" ;;
            TD:core=failed)       die "wp core install failed — see ./td logs" ;;
            TD:plugin=active)     ok "thrivedesk active" ;;
            TD:plugin=failed)     warn "could not activate thrivedesk — ./td logs" ;;
            TD:woo=active)        ok "woocommerce active" ;;
            TD:woo=installed)     ok "woocommerce installed" ;;
            TD:woo=unavailable)   warn "could not install woocommerce — needs network to wordpress.org" ;;
            TD:woo=failed)        warn "woocommerce would not activate — ./td logs" ;;
            TD:customer=present)  ok "customer account present" ;;
            TD:customer=created)  ok "customer account created" ;;
            TD:customer=failed)   warn "could not create the customer account — e2e portal specs need it" ;;
            TD:portal=created)    ok "portal page created" ;;
            TD:portal=present)    ok "portal page present" ;;
            TD:portalpath=*)      PORTAL_PATH="${line#TD:portalpath=}" ;;
            TD:permalinks=set)    ok "permalinks set to /%postname%/" ;;
            TD:rewrite=flushed)   ok "rewrite rules flushed" ;;
            TD:rewrite=failed)    warn "could not flush rewrite rules — pretty URLs and /wp-json/ may 404" ;;
        esac
    done
}

cmd_up() {
    require_docker
    pick_ports
    export TD_HTTP_PORT="$HTTP_PORT" TD_HTTPS_PORT="$HTTPS_PORT"
    TD_SITE_URL="$(site_url)"; export TD_SITE_URL

    # Keep the recorded values honest. They are only rewritten when a port is
    # re-picked, so a stale file could tell `docker compose up` a different site
    # URL than ./td just used — and WordPress bakes that URL into every link.
    remember_ports

    say "Starting the bench"
    "${COMPOSE[@]}" up -d --build

    say "Waiting for WordPress"
    local i=0
    until "${COMPOSE[@]}" exec -T wordpress wp --allow-root core version >/dev/null 2>&1; do
        i=$(( i + 1 ))
        [ "$i" -gt 60 ] && die "WordPress did not come up. Look at:  ./td logs"
        sleep 2
    done
    ok "wordpress $(wp core version 2>/dev/null | tr -d '\r')"

    # Anything that ran as root before could have left root-owned directories in
    # the volume, and www-data then cannot write its own uploads.
    "${COMPOSE[@]}" exec -T --user root wordpress sh -c \
        'mkdir -p /var/www/html/wp-content/uploads && chown -R www-data:www-data /var/www/html/wp-content/uploads' \
        >/dev/null 2>&1 || true

    say "Installing dependencies"
    install_deps

    say "Setting up WordPress"
    provision | report_provision

    say "Ready"
    printf '    %s/wp-admin    %s / %s\n' "$TD_SITE_URL" "$ADMIN_USER" "$ADMIN_PASS"
    printf '    %s%s\n' "$TD_SITE_URL" "$PORTAL_PATH"
    printf '\n    The plugin is not connected to a ThriveDesk account yet:\n'
    printf '      ./td connect <api-key>\n\n'
}

cmd_down()  { require_docker; "${COMPOSE[@]}" stop; }

# Apache and PHP write to the container's stdout; WordPress writes fatals to
# wp-content/debug.log, which never reaches it. Showing only the first would
# leave the white-screen case — the one reason anybody runs this — out.
cmd_logs() {
    require_docker
    "${COMPOSE[@]}" exec -T --user www-data wordpress \
        sh -c 'touch /var/www/html/wp-content/debug.log' >/dev/null 2>&1 || true

    "${COMPOSE[@]}" exec -T wordpress tail -F -n 50 /var/www/html/wp-content/debug.log 2>/dev/null \
        | sed 's/^/debug.log  | /' &
    local tail_pid=$!
    trap 'kill "$tail_pid" 2>/dev/null' EXIT INT TERM

    "${COMPOSE[@]}" logs -f --tail=50 wordpress caddy
}

cmd_shell() { require_docker; "${COMPOSE[@]}" exec wordpress bash; }
cmd_cli()   { require_docker; wp "$@"; }

cmd_reset() {
    require_docker
    printf 'This wipes the database and the WordPress volume. Your code is untouched. [y/N] '
    read -r a; [ "$a" = "y" ] || { echo "cancelled"; return 0; }
    "${COMPOSE[@]}" down -v
    cmd_up
}

# Point the bench at a real ThriveDesk workspace. The plugin is useless without
# one — every outbound call authenticates with this key — and there is no way to
# fake it, so this is a one-liner rather than part of `up`.
cmd_connect() {
    require_docker
    local key="${1:-}"
    [ -n "$key" ] || die "usage: ./td connect <api-key>

    Get one from https://app.thrivedesk.com — Settings, then API."

    # Through the environment, not as an argument: `wp eval` takes exactly one
    # positional and rejects a second, and a key on the command line would sit
    # in shell history besides.
    say "Connecting"
    "${COMPOSE[@]}" exec -T --user www-data -e HOME=/tmp -e TD_API_KEY="$key" wordpress \
        wp eval '
            $s = get_option( "td_helpdesk_settings", array() );
            $s = is_array( $s ) ? $s : array();
            $s["td_helpdesk_api_key"] = getenv( "TD_API_KEY" );
            update_option( "td_helpdesk_settings", $s );
        ' >/dev/null 2>&1 || die "could not store the key — ./td logs"
    ok "api key stored"
    printf '\n    Open %s/wp-admin/admin.php?page=thrivedesk and press Verify\n' "$(site_url)"
    printf '    to pick an assistant and an inbox.\n\n'
}

# The PHPUnit suite, inside the container, against the bench's own MariaDB.
#
# No SVN export and no separate test-library download: this repository already
# has wp-phpunit as a dev dependency, and tests/bootstrap.php finds it in
# vendor/. All it needs from us is a config naming the database.
cmd_test() {
    require_docker
    install_deps

    "${COMPOSE[@]}" exec -T --user www-data wordpress sh -c 'cat > /tmp/wp-tests-config.php <<PHP
<?php
define( "ABSPATH", "/var/www/html/" );
define( "WP_DEFAULT_THEME", "default" );
define( "DB_NAME", "wordpress_test" );
define( "DB_USER", "wordpress" );
define( "DB_PASSWORD", "wordpress" );
define( "DB_HOST", "mariadb" );
define( "DB_CHARSET", "utf8" );
define( "DB_COLLATE", "" );
\$table_prefix = "wptests_";
define( "WP_TESTS_DOMAIN", "thrivedesk.test" );
define( "WP_TESTS_EMAIL", "admin@thrivedesk.test" );
define( "WP_TESTS_TITLE", "ThriveDesk Tests" );
define( "WP_PHP_BINARY", "php" );
PHP'

    say "PHPUnit"
    # Same reason as wp() above: as root, WP_Filesystem() is unavailable and
    # tests that touch files fail for reasons that have nothing to do with them.
    "${COMPOSE[@]}" exec -T --user www-data -e HOME=/tmp \
        -e WP_PHPUNIT__TESTS_CONFIG=/tmp/wp-tests-config.php \
        -w /var/www/html/wp-content/plugins/thrivedesk \
        wordpress vendor/bin/phpunit "$@"
}

cmd_phpcs() {
    require_docker
    install_deps
    say "PHPCS"
    "${COMPOSE[@]}" exec -T --user "$(id -u):$(id -g)" \
        -w /var/www/html/wp-content/plugins/thrivedesk \
        wordpress vendor/bin/phpcs "$@"
    say "PHPCS — security"
    "${COMPOSE[@]}" exec -T --user "$(id -u):$(id -g)" \
        -e COMPOSER_HOME=/tmp/composer-cache \
        -w /var/www/html/wp-content/plugins/thrivedesk \
        wordpress composer phpcs-security
    say "PHPCS — i18n"
    "${COMPOSE[@]}" exec -T --user "$(id -u):$(id -g)" \
        -e COMPOSER_HOME=/tmp/composer-cache \
        -w /var/www/html/wp-content/plugins/thrivedesk \
        wordpress composer phpcs-i18n
}

# The end-to-end suite.
#
# Playwright runs in its own container rather than on the host: there is no node
# here, and the official image already carries the browsers.
#
# Two things make the bench reachable from inside it. The container joins the
# bench's own network, and `--add-host` points the site's name at caddy's address
# on it — so the tests use the same URL a person does, which matters because
# WP_HOME is that URL and WordPress redirects anything else. Caddy's own authority
# is mounted in as NODE_EXTRA_CA_CERTS for the REST calls; Chromium does not read
# that variable, and gets `ignoreHTTPSErrors` from playwright.config.ts instead.
#
# Sets: E2E_NET, E2E_HOST, E2E_IP, E2E_CERTS, E2E_URL, E2E_TTY.
e2e_env() {
    require_docker

    [ -d "$ROOT/node_modules/@playwright" ] || die "Playwright is not installed. Run:  ./td e2e-install"

    E2E_URL="$(site_url)"
    E2E_HOST="${E2E_URL#https://}"
    E2E_HOST="${E2E_HOST%%:*}"

    local caddy_id
    caddy_id="$("${COMPOSE[@]}" ps -q caddy 2>/dev/null)" \
        || die "The bench is not running. Run ./td up first."
    [ -n "$caddy_id" ] || die "The bench is not running. Run ./td up first."

    E2E_NET="$(docker inspect "$caddy_id" --format '{{range $k,$v := .NetworkSettings.Networks}}{{$k}}{{end}}')"
    E2E_IP="$(docker inspect "$caddy_id" --format '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}')"

    [ -n "$E2E_NET" ] && [ -n "$E2E_IP" ] || die "Could not find caddy on the bench network. Run ./td up first."

    E2E_CERTS="$(mktemp -d -t thrivedesk-e2e-ca)"
    "${COMPOSE[@]}" cp caddy:/data/caddy/pki/authorities/local/root.crt "$E2E_CERTS/root.crt" >/dev/null 2>&1 \
        || die "Caddy has not created its authority yet. Run ./td up first."

    # -t only when there is a terminal to attach: a CI run, or an agent driving
    # this, has no tty and docker refuses outright rather than degrading. A string
    # rather than an array — bash 3.2 ships on macOS and treats an empty array as
    # unset under `set -u`.
    E2E_TTY=""
    if [ -t 0 ] && [ -t 1 ]; then E2E_TTY="-t"; fi
}

# Run one command inside the Playwright image, on the bench network.
# $1 is a port to publish, or "" for none. The rest is the command.
e2e_run() {
    local publish="$1"; shift

    docker run --rm -i ${E2E_TTY:+-t} \
        --network "$E2E_NET" \
        --add-host "$E2E_HOST:$E2E_IP" \
        ${publish:+-p "$publish"} \
        -v "$ROOT":/repo \
        -v "$E2E_CERTS":/certs:ro \
        -w /repo \
        -e WP_BASE_URL="$E2E_URL" \
        -e WP_ADMIN_USER="$ADMIN_USER" -e WP_ADMIN_PASSWORD="$ADMIN_PASS" \
        -e WP_CUSTOMER_USER="$CUSTOMER_USER" -e WP_CUSTOMER_PASSWORD="$CUSTOMER_PASS" \
        -e WP_PORTAL_PATH="${TD_PORTAL_PATH:-/thrivedesk-support-portal/}" \
        -e NODE_EXTRA_CA_CERTS=/certs/root.crt \
        -e CI="${CI:-}" \
        "$PLAYWRIGHT_IMAGE" \
        "$@"
}

# The end-to-end suite, headless. Pass anything Playwright takes:
#   ./td e2e e2e/settings-page.e2e.ts
#   ./td e2e --grep "portal"
cmd_e2e() {
    e2e_env
    say "End-to-end — $E2E_URL"
    warn "the portal specs skip themselves unless the bench is connected (./td connect)"
    e2e_run "" npx playwright test "$@"
    rm -rf "$E2E_CERTS"
}

# Watch the run, live, in Playwright's UI mode.
#
# The browser itself is inside the container and there is no display there to
# show it on — but UI mode is a *server*. Bound to 0.0.0.0 and published, it is
# opened from a browser here: the test list, every action as it happens, the DOM
# at each step, and a time-travel scrub of the whole run.
cmd_e2e_ui() {
    e2e_env
    local port="${TD_E2E_UI_PORT:-8080}"
    say "UI mode — http://localhost:$port"
    printf '    Open that in a browser. Ctrl-C here stops it.\n\n'
    e2e_run "127.0.0.1:$port:$port" \
        npx playwright test --ui-host 0.0.0.0 --ui-port "$port" "$@"
    rm -rf "$E2E_CERTS"
}

cmd_e2e_report() {
    e2e_env
    local port="${TD_E2E_REPORT_PORT:-9323}"
    [ -d "$ROOT/playwright-report" ] || die "No report yet. Run ./td e2e first."
    say "Report — http://localhost:$port"
    printf '    Ctrl-C here stops it.\n\n'
    e2e_run "127.0.0.1:$port:$port" \
        npx playwright show-report playwright-report --host 0.0.0.0 --port "$port"
    rm -rf "$E2E_CERTS"
}

# The browsers come with the image; only the node packages are needed here, and
# they are the ones package.json already pins.
cmd_e2e_install() {
    require_docker
    say "Installing node dependencies"
    docker run --rm -v "$ROOT":/repo -w /repo \
        -e PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 \
        -e npm_config_cache=/tmp/.npm -e HOME=/tmp \
        node:20-alpine npm install --no-audit --no-fund
}

cmd_trust() {
    require_docker
    local crt
    crt="$(mktemp -t thrivedesk-caddy-ca)"
    "${COMPOSE[@]}" cp caddy:/data/caddy/pki/authorities/local/root.crt "$crt" 2>/dev/null \
        || die "Caddy has not created its authority yet. Run ./td up first."
    say "Trusting Caddy's local authority (sudo)"
    sudo security add-trusted-cert -d -r trustRoot \
        -k /Library/Keychains/System.keychain "$crt"
    rm -f "$crt"
    ok "trusted — the certificate is valid now"
}

cmd_secure() {
    # Under OrbStack there is nothing to do. It resolves the container's name
    # itself, terminates TLS with a certificate from its own root, and that root
    # went into the keychain when OrbStack was installed.
    if is_orbstack; then
        say "Already secure"
        ok "$DOMAIN_LOCAL resolves through OrbStack"
        ok "its certificate comes from a root your keychain already trusts"
        printf '\n    %s/wp-admin    %s / %s\n\n' "$(site_url)" "$ADMIN_USER" "$ADMIN_PASS"
        return 0
    fi

    warn "$DOMAIN needs an /etc/hosts entry pointing at 127.0.0.1"
    cmd_trust
    say "Done"
    printf '    %s/wp-admin    %s / %s\n\n' "$(site_url)" "$ADMIN_USER" "$ADMIN_PASS"
}

cmd_doctor() {
    command -v docker >/dev/null 2>&1 && ok "docker installed" || warn "docker missing"
    docker info >/dev/null 2>&1 && ok "docker running" || warn "docker not running"
    if is_orbstack; then
        ok "orbstack — the site answers on $(site_url) with no host port"
    else
        ok "site url: $(site_url)  (needs /etc/hosts + ./td trust)"
    fi
    [ -f "$ROOT/vendor/autoload.php" ]        && ok "composer dependencies installed" || warn "composer dependencies missing — ./td up"
    [ -d "$ROOT/node_modules/@playwright" ]   && ok "playwright installed"            || warn "playwright missing — ./td e2e-install"
    if "${COMPOSE[@]}" ps -q wordpress >/dev/null 2>&1 && [ -n "$("${COMPOSE[@]}" ps -q wordpress 2>/dev/null)" ]; then
        local key
        key="$(wp option pluck td_helpdesk_settings td_helpdesk_api_key 2>/dev/null | tr -d '\r')"
        [ -n "$key" ] && ok "connected to a ThriveDesk account" || warn "not connected — ./td connect <api-key>"
    fi
    "${COMPOSE[@]}" ps 2>/dev/null | tail -n +2 | sed 's/^/    /'
}

case "${1:-up}" in
    up)          shift; cmd_up "$@" ;;
    install)     shift; require_docker; install_deps ;;
    down)        shift; cmd_down "$@" ;;
    reset)       shift; cmd_reset "$@" ;;
    logs)        shift; cmd_logs "$@" ;;
    shell)       shift; cmd_shell "$@" ;;
    cli)         shift; cmd_cli "$@" ;;
    connect)     shift; cmd_connect "$@" ;;
    test)        shift; cmd_test "$@" ;;
    phpcs)       shift; cmd_phpcs "$@" ;;
    e2e)         shift; cmd_e2e "$@" ;;
    e2e-ui)      shift; cmd_e2e_ui "$@" ;;
    e2e-report)  shift; cmd_e2e_report ;;
    e2e-install) shift; cmd_e2e_install ;;
    trust)       shift; cmd_trust "$@" ;;
    secure)      shift; cmd_secure "$@" ;;
    doctor)      shift; cmd_doctor "$@" ;;
    # Read from the header comment itself rather than a line range, so
    # editing the help cannot silently start printing the code below it.
    -h|--help|help) awk 'NR>2 && /^#/ { sub(/^# ?/, ""); print; next } NR>2 { exit }' "${BASH_SOURCE[0]}" ;;
    *)
        # Guessing a name is normal; making someone read the whole help to find
        # out they were one letter off is not.
        near="$(printf 'up\ndown\nreset\nlogs\nshell\ncli\nconnect\ntest\nphpcs\ne2e\ntrust\nsecure\ninstall\ndoctor\n' \
            | awk -v w="$1" 'index($0, substr(w,1,3)) == 1 || index(w, $0) == 1 {print; exit}')"
        if [ -n "$near" ]; then
            die "unknown command '$1' — did you mean ./td $near?"
        fi
        die "unknown command '$1' — try ./td help"
        ;;
esac
