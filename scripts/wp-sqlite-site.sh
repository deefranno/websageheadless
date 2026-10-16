#!/usr/bin/env bash
# Supplementary Docker-less WordPress site for local round-trip checks:
# WordPress (SQLite drop-in) on `php -S`, behind a local HTTPS proxy with a
# throwaway self-signed certificate. DDEV remains the supported environment.
#
#   scripts/wp-sqlite-site.sh start   # fresh site at https://localhost:8443
#   scripts/wp-sqlite-site.sh stop
#
# Also fronts the Next.js server (expected on http://127.0.0.1:3000) with
# HTTPS at https://localhost:3443, so WordPress can deliver signed webhooks.
#
# Writes .wordpress/sqlite-site/e2e.env with URLs, admin credentials, fresh
# shared secrets and NODE_EXTRA_CA_CERTS for the frontend. Everything lives
# under .wordpress/.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SITE="$ROOT/.wordpress/sqlite-site"
CORE="$ROOT/.wordpress/sqlite-core"
HTTP_PORT=8081
HTTPS_PORT=8443
WEB_PORT=3000
WEB_HTTPS_PORT=3443
URL="https://localhost:$HTTPS_PORT"
WEB_URL="https://localhost:$WEB_HTTPS_PORT"

wp() { php "$ROOT/.tools/wp-cli/wp-cli.phar" --path="$SITE" "$@"; }

stop() {
  for pidfile in "$SITE"/*.pid; do
    [[ -f "$pidfile" ]] && kill "$(cat "$pidfile")" 2>/dev/null || true
    rm -f "$pidfile"
  done
}

case "${1:-}" in
  stop) stop; exit 0 ;;
  start) ;;
  *) echo "usage: $0 start|stop" >&2; exit 2 ;;
esac

[[ -d "$CORE" ]] || "$ROOT/scripts/wp-sqlite-test-env.sh"
[[ -f "$ROOT/.tools/wp-cli/wp-cli.phar" ]] || { echo "WP-CLI missing at .tools/wp-cli/wp-cli.phar" >&2; exit 1; }

stop
rm -rf "$SITE"
cp -a "$CORE" "$SITE"
sed -i "s#$CORE#$SITE#" "$SITE/wp-content/db.php"
ln -s "$ROOT/packages/wp-plugin" "$SITE/wp-content/plugins/websage-headless-core"

openssl req -x509 -newkey rsa:2048 -nodes -days 2 -subj "/CN=localhost" \
  -addext "subjectAltName=DNS:localhost" \
  -keyout "$SITE/tls.key" -out "$SITE/tls.crt" 2>/dev/null

ADMIN_PASSWORD="$(openssl rand -hex 16)"
SERVER_API_SECRET="$(openssl rand -hex 32)"
PREVIEW_SECRET="$(openssl rand -hex 32)"
REVALIDATION_SECRET="$(openssl rand -hex 32)"
wp config create --dbname=unused --dbuser=unused --dbpass=unused --skip-check --quiet --extra-php <<PHP
if ( isset( \$_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === \$_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
	\$_SERVER['HTTPS'] = 'on';
}
define( 'WSH_FRONTEND_ORIGIN', '$WEB_URL' );
define( 'WSH_SERVER_API_SECRET', '$SERVER_API_SECRET' );
define( 'WSH_PREVIEW_SECRET', '$PREVIEW_SECRET' );
define( 'WSH_REVALIDATION_SECRET', '$REVALIDATION_SECRET' );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
PHP
wp core install --url="$URL" --title="WebSage Demo" --admin_user=admin \
  --admin_password="$ADMIN_PASSWORD" --admin_email=admin@example.com --skip-email --quiet
wp option update permalink_structure '/%postname%/' --quiet  # rewrite structure breaks on paths with spaces

# Trust this harness's own certificate for localhost requests (webhooks);
# TLS verification stays on.
mkdir -p "$SITE/wp-content/mu-plugins"
cat > "$SITE/wp-content/mu-plugins/harness-tls.php" <<PHP
<?php
add_filter( 'http_request_args', static function ( \$args, \$url ) {
	if ( 'localhost' === wp_parse_url( \$url, PHP_URL_HOST ) ) {
		\$args['sslcertificates'] = '$SITE/tls.crt';
	}
	return \$args;
}, 10, 2 );
PHP

cat > "$SITE/router.php" <<'PHP'
<?php
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
// Real files and directories (wp-admin/ -> its index.php) go to the built-in server.
if ( '/' !== $path && ( is_file( __DIR__ . $path ) || is_dir( __DIR__ . $path ) ) ) {
	return false;
}
require __DIR__ . '/index.php';
PHP

# Fully detached so callers piping this script's output are not held open.
PHP_CLI_SERVER_WORKERS=4 setsid php -S "127.0.0.1:$HTTP_PORT" -t "$SITE" "$SITE/router.php" </dev/null >"$SITE/php.log" 2>&1 &
echo $! >"$SITE/php.pid"
setsid node "$ROOT/scripts/tls-proxy.mjs" "$HTTPS_PORT" "$HTTP_PORT" "$SITE/tls.crt" "$SITE/tls.key" </dev/null >"$SITE/proxy.log" 2>&1 &
echo $! >"$SITE/proxy.pid"
setsid node "$ROOT/scripts/tls-proxy.mjs" "$WEB_HTTPS_PORT" "$WEB_PORT" "$SITE/tls.crt" "$SITE/tls.key" </dev/null >"$SITE/web-proxy.log" 2>&1 &
echo $! >"$SITE/web-proxy.pid"
for _ in $(seq 1 40); do
  curl -fs --cacert "$SITE/tls.crt" -o /dev/null "$URL/wp-json/" && break
  sleep 0.25
done

wp plugin activate websage-headless-core --quiet
home_id="$(wp post create --post_type=page --post_status=publish --post_title=Home --post_name=home --porcelain \
  --post_content='<!-- wp:websage/hero {"heading":"Welcome home"} /-->')"
wp option update show_on_front page --quiet
wp option update page_on_front "$home_id" --quiet

cat >"$SITE/e2e.env" <<ENV
WSH_E2E_WP_URL='$URL'
WSH_E2E_WEB_URL='$WEB_URL'
WSH_E2E_WP_USER='admin'
WSH_E2E_WP_PASSWORD='$ADMIN_PASSWORD'
NODE_EXTRA_CA_CERTS='$SITE/tls.crt'
WP_API_ORIGIN='$URL'
FRONTEND_ORIGIN='$WEB_URL'
WSH_SERVER_API_SECRET='$SERVER_API_SECRET'
WSH_PREVIEW_SECRET='$PREVIEW_SECRET'
WSH_REVALIDATION_SECRET='$REVALIDATION_SECRET'
ENV
echo "WordPress running at $URL (credentials in $SITE/e2e.env)"
