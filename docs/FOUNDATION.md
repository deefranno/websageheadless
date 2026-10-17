# Foundation: local setup and commands

Phase 2 developer notes. The full install guide (`INSTALL.md`) arrives in Phase 6.

## Prerequisites

| Tool                 | Version                    | Notes                                                                     |
| -------------------- | -------------------------- | ------------------------------------------------------------------------- |
| Node.js              | 24.22.0 (`.node-version`)  | `engine-strict` refuses other majors                                      |
| pnpm                 | 12.14.0 (`packageManager`) |                                                                           |
| PHP CLI              | 8.1+ (8.3 recommended)     | extensions: mbstring, xml, curl; `pdo_sqlite` for the Docker-less harness |
| Composer             | 2.10.x                     |                                                                           |
| Docker Engine + DDEV | current                    | the supported local WordPress; Docker Desktop is not required on Linux    |

## Repository layout

```text
apps/web/               Next.js frontend (@websage/web)
packages/contracts/     Zod schemas + shared JSON fixtures (@websage/contracts)
packages/wp-plugin/     WebSage Headless Core plugin (@websage/wp-plugin)
scripts/                local harness and helper scripts
.ddev/                  DDEV project and custom commands
.github/workflows/      CI
```

## Everyday commands (repository root)

```bash
pnpm install
composer install --working-dir=packages/wp-plugin

pnpm lint        # ESLint + Prettier + PHPCS (WordPress Coding Standards, PHP 8.1 compatibility)
pnpm typecheck   # tsc for TS packages, PHPStan level 8 for the plugin
pnpm test        # Vitest (contracts, web) + PHPUnit unit suite (no WordPress needed)
pnpm build       # contracts, block assets (@wordpress/scripts), Next.js standalone build
```

`pnpm build` does not need WordPress to be reachable: every CMS read happens at request time.

## Local WordPress with DDEV

```bash
pnpm --filter @websage/wp-plugin build   # block assets must exist before install
ddev start
ddev websage-install    # WordPress 7.1.3, only WebSage Headless Core active; prints the admin password once
ddev websage-test       # plugin integration suite on a separate wordpress_test database
```

`websage-install` exits without changes if WordPress is already installed. The plugin is symlinked from `packages/wp-plugin`, so edits apply immediately.

Frontend against DDEV (`apps/web/.env.local`, see `.env.example`):

```bash
WP_API_ORIGIN=https://websage-headless.ddev.site
WORDPRESS_MEDIA_HOST=websage-headless.ddev.site
NODE_EXTRA_CA_CERTS="$(mkcert -CAROOT)/rootCA.pem"   # trust DDEV's local certificate
```

Load the demo site with `ddev wp websage seed --yes` (pages, posts, testimonials, CTA, menus, settings and front page; safe to run again), then `pnpm --filter @websage/web dev` and open http://localhost:3000.

Without WordPress, `WSH_FIXTURE_MODE=true pnpm --filter @websage/web dev` renders the shared fixtures. Fixture mode is refused in production builds.

## Docker-less harness (supplementary)

For machines without Docker, and for the CI round-trip job:

```bash
scripts/wp-sqlite-test-env.sh            # WordPress 7.1.3 + SQLite drop-in in .wordpress/sqlite-core
WP_CORE_DIR="$PWD/.wordpress/sqlite-core" composer --working-dir=packages/wp-plugin test:integration

scripts/wp-sqlite-site.sh start          # disposable site at https://localhost:8443 (self-signed TLS)
# Also: HTTPS front for Next.js at https://localhost:3443 -> http://127.0.0.1:3000,
# fresh shared secrets, WebSage Headless Core activated and the demo seeded.
# URLs, credentials, secrets and NODE_EXTRA_CA_CERTS are in .wordpress/sqlite-site/e2e.env
set -a; . .wordpress/sqlite-site/e2e.env; set +a
pnpm --filter @websage/web build && cp -r apps/web/.next/static apps/web/.next/standalone/apps/web/.next/
PORT=3000 node apps/web/.next/standalone/apps/web/server.js &   # keep the default HOSTNAME
pnpm test:integration
scripts/wp-sqlite-site.sh stop
```

The harness needs WP-CLI at `.tools/wp-cli/wp-cli.phar` (see the CI workflow for a checksum-verified download). It uses SQLite, not MySQL; DDEV and the CI `wordpress` job remain the authoritative database environment.

## Integration and browser tests

`pnpm test:integration` runs Playwright against a running frontend and WordPress:

```bash
set -a; . .wordpress/sqlite-site/e2e.env; set +a     # or your DDEV values
WSH_E2E_WEB_URL=http://127.0.0.1:3000 pnpm test:integration
```

The suite publishes pages through the real block editor. Point it only at disposable sites.

## Configuration

WordPress reads `WSH_FRONTEND_ORIGIN` (wp-config constant or server environment) for canonical URLs; it must be an HTTPS origin, otherwise page endpoints return `503 not_configured`. Phase 3 adds the wp-admin settings page.

## Known Phase 2 limitations

- Unknown paths get a real 404 from proxy.ts; a page that disappears between the route check and rendering still gets the noindex soft 404 (ADR-011, ADR-015).
- `robots.txt` is generated at build time: set `FRONTEND_ORIGIN` when building.
- The demo seed contains no images.
- Static export is not supported (preview, contact and revalidation need a server).
