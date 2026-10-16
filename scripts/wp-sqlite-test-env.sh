#!/usr/bin/env bash
# Supplementary local harness: a disposable WordPress core using the official
# SQLite Database Integration drop-in, for running the plugin's integration
# suite on machines without Docker. MySQL via DDEV and CI remains the
# authoritative environment; results from this harness are reported separately.
#
# Usage: scripts/wp-sqlite-test-env.sh            # prepare .wordpress/sqlite-core
#        eval "$(scripts/wp-sqlite-test-env.sh --env)"  # export test variables
set -euo pipefail

WP_VERSION="7.1.3"
SQLITE_VERSION="3.1.0"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="$ROOT/.wordpress/sqlite-core"

if [[ "${1:-}" == "--env" ]]; then
  echo "export WP_CORE_DIR='$TARGET'"
  exit 0
fi

mkdir -p "$ROOT/.wordpress/downloads"
cd "$ROOT/.wordpress/downloads"

if [[ ! -f "wordpress-$WP_VERSION.tar.gz" ]]; then
  curl -fsSLO "https://wordpress.org/wordpress-$WP_VERSION.tar.gz"
  curl -fsSL "https://wordpress.org/wordpress-$WP_VERSION.tar.gz.sha1" -o "wordpress-$WP_VERSION.tar.gz.sha1"
fi
echo "$(cat "wordpress-$WP_VERSION.tar.gz.sha1")  wordpress-$WP_VERSION.tar.gz" | sha1sum -c -

if [[ ! -f "sqlite-database-integration-$SQLITE_VERSION.zip" ]]; then
  curl -fsSLo "sqlite-database-integration-$SQLITE_VERSION.zip" \
    "https://downloads.wordpress.org/plugin/sqlite-database-integration.$SQLITE_VERSION.zip"
fi

rm -rf "$TARGET"
mkdir -p "$TARGET"
tar -xzf "wordpress-$WP_VERSION.tar.gz" -C "$TARGET" --strip-components=1
python3 -I -m zipfile -e "sqlite-database-integration-$SQLITE_VERSION.zip" "$TARGET/wp-content/plugins/"
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$TARGET/wp-content/plugins/sqlite-database-integration#" \
    -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
    "$TARGET/wp-content/plugins/sqlite-database-integration/db.copy" > "$TARGET/wp-content/db.php"
mkdir -p "$TARGET/wp-content/uploads"
echo "Prepared WordPress $WP_VERSION with SQLite integration $SQLITE_VERSION at $TARGET"
