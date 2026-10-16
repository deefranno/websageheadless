# Phase 2 (Foundation) verification report

Date: 2026-10-16. Branch `feat/foundation` in the dedicated repository at this directory (ADR-007 resolved: Git now resolves here, not to the parent Pictures repository). Environment: Linux, Node 24.22.0 and pnpm 12.14.0 (project-local in `.tools/`), PHP 8.4.26 CLI, Composer 2.10.3. No Docker or DDEV on this machine.

## Exit criteria

| Criterion                                                            | Status      | Evidence                                                                                                                                                                                                                                                                                                                            |
| -------------------------------------------------------------------- | ----------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `pnpm lint` passes                                                   | ✅          | exit 0: ESLint clean, Prettier clean, PHPCS (WPCS 3.4.1 + PHPCompatibility 8.1+) clean                                                                                                                                                                                                                                              |
| `pnpm typecheck` passes                                              | ✅          | exit 0: tsc (contracts, web), PHPStan level 8 "No errors"                                                                                                                                                                                                                                                                           |
| `pnpm test` passes                                                   | ✅          | exit 0: contracts 14/14, web 51/51, PHP unit 30/30                                                                                                                                                                                                                                                                                  |
| `pnpm build` passes                                                  | ✅          | exit 0: contracts, block assets, Next.js standalone build, with no CMS configured                                                                                                                                                                                                                                                   |
| Plugin activates cleanly on a fresh site, no other plugins           | ⚠️ partial  | Verified on a fresh WordPress 7.1.3 **SQLite** site via WP-CLI: `Plugin 'websage-headless-core' activated`; active plugins: only websage-headless-core (plus the SQLite `db.php` drop-in the harness needs). **Not run on DDEV/MySQL** (no Docker here).                                                                            |
| CI green                                                             | ✅          | GitHub Actions run 38954792820 on commit 2e9378c: lint/typecheck/test/build, WordPress integration on PHP 8.1 and 8.3 against MariaDB 11.4 (both CMB2 load orders), and the Playwright editor round trip all passed. The first run (38954202593) failed at `pnpm install` because pnpm 12 requires `allowBuilds`; fixed in 2e9378c. |
| Hero round trip (editor → `parse_blocks()` → endpoint → Zod → React) | ✅ (SQLite) | Playwright: hero inserted and published in the real WordPress 7.1.3 block editor, REST payload parsed by `PageResponseSchema`, heading and body rendered by the production Next.js server. 2/2 passed, two consecutive runs.                                                                                                        |
| CMB2 two-version load-order test                                     | ✅ (SQLite) | Integration suite run with `WSH_CMB2_ORDER=legacy-first` and `bundled-first`: CMB2 2.11.0 (test-only) and 2.13.4 (bundled) both loaded, 2.13.4 wins from the bundled path in both orders.                                                                                                                                           |

CI is green, so the plugin has passed its WordPress tests against MariaDB on PHP 8.1 and 8.3. Still outstanding against the exit criteria: fresh activation through the DDEV environment (`ddev websage-install`), which needs a Docker host.

## Commands run and results

```text
pnpm lint / typecheck / test / build                     all exit 0
composer check (packages/wp-plugin)                      PHPCS clean, PHPStan OK, PHPUnit OK (30 tests, 38 assertions)
WP_CORE_DIR=.wordpress/sqlite-core composer test:integration
                                                         OK (22 tests, 117 assertions) × 2 load orders
scripts/wp-sqlite-site.sh start; wp plugin activate      activated; only plugin active
curl https://localhost:8443/wp-json/websage/v1/...       /site 200; /pages/home 503 not_configured (fresh site);
                                                         unknown route → error envelope; Cache-Control: no-store
Next standalone (production) against that site           / 200 renders WordPress hero; /about 200 server-rendered;
                                                         draft /secret, /no-such-page, /blog/x → not-found UI + noindex, HTTP 200
Next standalone with WSH_FIXTURE_MODE=true               refused: ConfigError logged; browser receives only an opaque digest
Security headers on production responses                 CSP (frame-ancestors 'none'), HSTS, nosniff, Referrer-Policy,
                                                         Permissions-Policy, X-Frame-Options present
pnpm test:integration (Playwright, Chromium)             2 passed
```

## Test coverage of the plan's review focus

1. CMS offline never shows fixture content: `fetchers.test.ts` (refused connection → error, no fixtures). Production fixture mode refused (unit test plus live standalone run).
2. Unknown vs malformed sections: contracts tests and `registry.test.tsx`. Unknown types are skipped in production and flagged in development; a hero without a heading fails page validation.
3. Unedited legacy blocks: PHP `HeroMigration` unit tests, and an integration test reading a stored `schemaVersion: 0` block through the endpoint. Editor deprecation mirrors it.
4. CMB2 coexistence: both load orders, as above.
5. Drafts and missing front page: draft, pending, private, future and password-protected pages, plus children of private parents, all give `404 not_found`. A missing or draft front page gives `503 not_configured`.

## Issues found and fixed during verification

- Confirmed 404s thrown inside `'use cache'` were logged as server errors and got no `noindex` tag. Fixed: absence is a cached `null` (commit d4da84b).
- PHP link normalization rewrote CMS media URLs into frontend paths that would 404. Fixed, with an integration test (fc8c24d).
- The root ESLint config imported a path that doesn't exist in the installed eslint-config-next (5e39ac8).

## Known limitations and open items

- **Soft 404s** (ADR-011, approved 2026-10-16): missing pages return 200 with `noindex`; Phase 3 adds a `proxy.ts` existence check.
- Process note: the plan asked for tests to fail first. Contract and PHP unit tests pre-existed; most web and integration tests were written alongside the implementation, so a red-then-green sequence was not observed for them.
- DDEV commands (`websage-install`, `websage-test`) and the CI workflow are untested. The MariaDB image version and the DDEV `create_docroot` behaviour need a real run.
- Google Fonts are downloaded at build time by `next/font` (self-hosted in output); offline builds fail.
- The WordPress-hosted front end is not yet redirected or noindexed (headless hardening is Phase 3); CORS on the namespace is not yet closed (Phase 3).
- Lighthouse, axe and the rebranding test are later phases.
