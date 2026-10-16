# Decision log

Date: 2026-10-16. Phase 1 architecture and proposed defaults approved by owner on 2026-10-16; final license text remains subject to legal review. Versions below are documentary pins only; no dependencies have been installed or compatibility claimed.

## ADR-001 — Initialization versions

Context: the prompt requires current stable versions verified rather than recalled.

Decision: use these exact baseline pins when Foundation is approved, rechecking for intervening security patches before installation. Commit package and Composer lockfiles in Phase 2. Use exact versions, no latest tags or floating caret ranges for direct runtime dependencies.

| Dependency | Baseline pin | Official evidence inspected |
|---|---|---|
| Next.js | 16.4.2 | https://github.com/vercel/next.js/releases/tag/v16.4.2 (official latest release redirect) |
| React / react-dom | 19.3.0 | https://react.dev/versions (latest stable release entry) |
| Node LTS | 24.22.0 | https://nodejs.org/en/download (Latest LTS; 26.12.0 is Current) |
| Tailwind CSS | 4.3.3 | https://github.com/tailwindlabs/tailwindcss/releases/tag/v4.3.3 (official latest release redirect) |
| WordPress | 7.1.3 | https://wordpress.org/download/ (current stable download) |
| PHP | minimum 8.1; test runtime 8.3 | Master prompt requirement; exact runtime image selected in Foundation |

Alternatives: remembered versions, prereleases, floating latest; rejected for reproducibility. Consequences: baseline versions need actual install/build and plugin/editor compatibility testing. Other tooling (pnpm, Zod, CMB2, @wordpress/scripts, sanitizer, testing tools) receives verified exact pins and lockfiles in Foundation; no unverified version numbers are asserted here. WordPress activation minimum 7.1.3 is proposed for approval.

## ADR-002 — Ownership and integration boundary

Context: clients must manage all content without React edits and only one plugin installation.
Decision: proposed monorepo structure in ARCHITECTURE.md, native blocks for page sections, bundled CMB2 for settings/meta, native menus, reusable CPTs, custom websage/v1 REST, shared Zod contracts with cross-language fixtures.
Alternatives: separate repos and core REST-only adapter; see architecture trade-offs. Consequences: PHP serializer and TS schema changes ship together; fixture parity is required. Gutenberg deprecations also need server-side migration for unedited stored content.

## ADR-003 — Bundled dependencies and conflicts

Context: WordPress sites can load multiple bundled CMB2 copies. The upstream loader negotiates versions; this is source inspection, not a verified runtime claim.
Decision: Composer-bundle CMB2 and preserve its upstream bootstrap/version negotiation. Do not scope CMB2. Initial plugin runtime has no other third-party PHP dependency; defer PHP-Scoper until one is introduced and evaluated. Ship production vendor and compiled assets.
Alternatives: standalone CMB2 plugin violates the one-plugin requirement; scoping CMB2 would defeat coexistence. Consequences: mandatory two-version integration test in both plugin load orders, including reported version/source path. Dependency inventory and license notices in distribution.
Evidence: https://github.com/CMB2/CMB2/blob/develop/init.php ; inspect the exact installed release source again in Foundation.

## ADR-004 — Cache and preview boundaries

Context: freshness must not leak drafts, and stale-while-revalidate alone does not guarantee immediate freshness.
Decision: targeted immediate webhook expiration, 300-second public fallback TTL, server-only preview with five-minute signed grant and matching cookie lifetime. Reusable-content dependency index invalidates parent pages. Preview bypasses all content caches.
Alternatives: redeploy on publish, public draft endpoint, unbounded draft session; rejected. Consequences: explicit slug/trash/reference tests and reliable cron retries required. Repeated valid webhook deliveries are harmless/idempotent, not advertised as deduplicated.
Evidence: https://nextjs.org/docs/app/api-reference/functions/revalidateTag ; https://developer.wordpress.org/block-editor/reference-guides/block-api/block-deprecation/

## ADR-005 — Security, privacy and deployment proposals

Context: no paid service may be required, and managed and standalone deployments must both work.
Decision: WordPress atomic abuse counters and private submissions; configurable retention default proposed at 30 days; optional Turnstile disabled by default. Closed custom REST CORS; independent server-only secrets. Proposed static-compatible public CSP and dynamic preview nonces. Standalone single-instance baseline; multi-instance requires separately approved cache/invalidation infrastructure.
Alternatives: mandatory hosted rate limiter, every-request nonce forcing public dynamic rendering, unmanaged multi-instance cache; rejected for baseline scope and reliability. Consequences: host proxy trust must be explicit; conditional cPanel support; actual performance measured later. sanitize-html is a proposed HTML-sanitization library, not an additional framework/service.

## ADR-006 — Proposed licensing

Context: commercial kit includes a WordPress derivative and independently distributed frontend.
Decision proposed: plugin and bundled editor GPL-2.0-or-later; frontend separate commercial terms; contracts MIT. Final terms need owner/legal confirmation. Do not create license enforcement or updater in v1.
Alternatives: GPL entire kit simplifies reuse but changes frontend business terms; commercial-only plugin conflicts with the stated licensing requirement. Consequences: distribution must preserve GPL rights and dependency notices; architecture is not legal advice or final license text.

## ADR-007 — Workspace isolation

Context: project has no tracked implementation and git rev-parse resolves to /home/duanef/Pictures. Parent repository contains unrelated staged work.
Decision: create only Phase 1 documents here; propose dedicated repository before implementation. Do not stage or commit in the parent repository during this phase.
Alternatives: commit into parent or initialize nested Git without review. Consequences: no Phase 1 commit created; recommended future message is `docs: define WebSage Headless phase one architecture`. Repository isolation must be resolved before Phase 2 commits.

## ADR-008 — Foundation tooling pins (Phase 2)

Context: ADR-001 deferred exact tooling versions to Foundation. Registry checks on 2026-10-16.
Decision: exact pins, committed lockfiles (`pnpm-lock.yaml`, `packages/wp-plugin/composer.lock`).

| Area | Pins |
|---|---|
| JS runtime/tooling | Node 24.22.0, pnpm 12.14.0, TypeScript 5.9.3, ESLint 9.39.1 + eslint-config-next 16.4.2, Prettier 3.9.10, Vitest 5.0.5, Playwright 1.64.1 |
| Frontend | Next.js 16.4.2, React 19.3.0, Tailwind 4.3.3, Zod 4.6.7, sanitize-html 2.18.1 |
| Block build | @wordpress/scripts 36.1.0, @wordpress/block-editor 18.1.0 (stable `LinkControl` export, confirmed present in WordPress 7.1.3's `wp.blockEditor`) |
| PHP runtime | CMB2 2.13.4 (only production dependency) |
| PHP tooling | Composer 2.10.3, PHPUnit 9.6.38, yoast/phpunit-polyfills 4.0.0, wp-phpunit 7.1.1 (latest published; WordPress 7.1.3 runtime), PHPStan 2.3.1 + szepeviktor/phpstan-wordpress 2.0.4 + wordpress-stubs 7.1.2, WPCS 3.4.1, PHPCompatibilityWP 2.1.8 |

Alternatives: ESLint 10 / TypeScript 7 conflict with framework parser peer ranges; PHPUnit 10+ require PHP 8.2+ or are unsupported by the WordPress test library, which would break the PHP 8.1 CI leg. Consequences: revisit majors when peers allow. CI actions pinned to commit SHAs (checkout v7.0.1, setup-node v7.1.0, pnpm/action-setup v6.1.0, setup-php 2.40.0).

## ADR-009 — Next.js Cache Components for the data cache

Context: Next.js 16 documents `unstable_cache` as replaced by `'use cache'`. The plan requires that only validated data enters the persistent cache and that builds do not need the CMS.
Decision: enable `cacheComponents`. `getSite`/`getPage` are `'use cache'` functions with `cacheTag('site' | 'page:{path}')` and `cacheLife({ stale: 60, revalidate: 300, expire: 86400 })`. The inner `fetch` is `no-store`, and validation runs inside the cached function. Thrown errors are not cached, so outages and contract violations never persist. A confirmed 404 is cached as `null` under the page tag. Components call `connection()` before reading, so CMS access is request-time behind Suspense.
Alternatives: fetch-level caching (caches raw responses before validation); `unstable_cache` (superseded). Consequences: Phase 3 webhooks call `revalidateTag(tag, { expire: 0 })`. Errors from `'use cache'` scopes are logged by Next.js, which is why expected absence is a value, not an exception.

## ADR-010 — Docker-less WordPress harness (test tooling only)

Context: this workstation has no Docker; the plan forbids claiming activation or round-trip results without running them.
Decision: `scripts/wp-sqlite-test-env.sh` and `scripts/wp-sqlite-site.sh` run real WordPress 7.1.3 (SHA-1 verified download) with the official SQLite Database Integration 3.1.0 drop-in, `php -S` and a local TLS proxy. Used for local evidence and the CI Playwright job. Never shipped; not a runtime dependency.
Alternatives: report everything as blocked until Docker is installed. Consequences: SQLite results are reported separately from MySQL. The CI `wordpress` job (MariaDB 11.4, PHP 8.1 and 8.3) and DDEV remain authoritative. Note: the SQLite plugin release was 8 days old at adoption; acceptable for test tooling only.

## ADR-011 — Missing pages are soft 404s with noindex (proposed, needs owner approval)

Context: the spec asks for `loading.tsx` on every route and a not-found state. Streaming commits HTTP 200 before the CMS lookup finishes, and Next.js 16 serves a prerendered fallback shell for catch-all routes. Tested: removing all Suspense boundaries and setting `instant = false` still returned 200 for `[...slug]`.
Decision (proposed): keep streaming and loading states. Missing pages render the not-found UI client-side with `<meta name="robots" content="noindex">`, which Next.js injects; verified in the production build and by Playwright.
Alternatives: (a) Phase 3 `proxy.ts` existence check against the cached `/routes` list to return a real 404 before rendering, recommended as a follow-up; (b) drop catch-all routing for explicit routes, which is not viable for CMS-defined paths. Consequences: crawlers see 200 plus noindex; the not-found message needs JavaScript.

## ADR-012 — Smaller Foundation choices

- Block `schemaVersion` has no default in `block.json`. A missing value means version 1, because WordPress omits default-valued attributes from post content. Future versions must store an explicit number.
- Public CSP uses `'unsafe-inline'` for scripts (static-compatible baseline per ADR-005). Nonce policy is deferred to preview routes.
- Hero heading level: the first section renders the page's only `h1`; later heroes use `h2`.
- SEO description defaults to the WordPress tagline until the Phase 3 SEO metabox.
- PHPCS prefix `wsh_` (four characters, satisfying WPCS) matches the approved hook/option prefix. Exception messages are excluded from output-escaping sniffs because they are log-only.
- WP-CLI `rewrite structure` fails on paths containing spaces (it spawns an unquoted subprocess); scripts set `permalink_structure` directly.
