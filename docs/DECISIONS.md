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

## ADR-011 — Missing pages: noindex soft 404 at render, real 404 from proxy (approved 2026-10-16; follow-up shipped in Phase 3)

Context: the spec asks for `loading.tsx` on every route and a not-found state. Streaming commits HTTP 200 before the CMS lookup finishes, and Next.js 16 serves a prerendered fallback shell for catch-all routes. Tested: removing all Suspense boundaries and setting `instant = false` still returned 200 for `[...slug]`.
Decision (approved by owner 2026-10-16): keep streaming and loading states. Missing pages render the not-found UI client-side with `<meta name="robots" content="noindex">`, which Next.js injects; verified in the production build and by Playwright.
Alternatives: (a) Phase 3 `proxy.ts` existence check against the cached `/routes` list to return a real 404 before rendering, scheduled as a Phase 3 follow-up; (b) drop catch-all routing for explicit routes, which is not viable for CMS-defined paths. Consequences: crawlers see 200 plus noindex; the not-found message needs JavaScript.

## ADR-012 — Smaller Foundation choices

- Block `schemaVersion` has no default in `block.json`. A missing value means version 1, because WordPress omits default-valued attributes from post content. Future versions must store an explicit number.
- Public CSP uses `'unsafe-inline'` for scripts (static-compatible baseline per ADR-005). Nonce policy is deferred to preview routes.
- Hero heading level: the first section renders the page's only `h1`; later heroes use `h2`.
- SEO description defaults to the WordPress tagline until the Phase 3 SEO metabox.
- PHPCS prefix `wsh_` (four characters, satisfying WPCS) matches the approved hook/option prefix. Exception messages are excluded from output-escaping sniffs because they are log-only.
- WP-CLI `rewrite structure` fails on paths containing spaces (it spawns an unquoted subprocess); scripts set `permalink_structure` directly.

## ADR-013 — Settings screen and storage (Phase 3)

Context: the spec asks for one plugin-owned admin page with tabs, a single `wsh_settings` option, explicit sanitization, and validators that reject rather than convert.
Decision: one CMB2 options page whose `option_key` is `wsh_settings`, so its admin slug is `wsh_settings` (CMB2 derives the page slug from the option key; the architecture's `wsh-settings` was not achievable without a second option). Tabs are a small progressive-enhancement script; without JavaScript all sections show. Invalid values keep the previously stored value and show a notice. Colour pairs whose WCAG contrast drops below 4.5:1 are rolled back after save. Settings use `{title}`/`{site}` placeholders (not `%title%`) to avoid printf-style strings in translations. CMB2 removes keys whose value is empty; `Settings::all()` restores defaults, so readers never see missing keys.
Consequences: CMB2 caches the option per request, so plugin code that writes the option during a CMB2 save goes through `cmb2_options()->set()`.

## ADR-014 — Next.js behind a TLS proxy (Phase 3)

Context: verified in the harness with Next.js 16.4.2 standalone. When the server is bound to a specific IP (`HOSTNAME=127.0.0.1`) and the reverse proxy sends `X-Forwarded-Proto: https`, proxy.ts rewrites are treated as external and fail with HTTP 500. With the default `HOSTNAME` (`0.0.0.0`) they work, and direct requests work in every configuration.
Decision: run the standalone server with the default HOSTNAME behind the reverse proxy, restricting access with the firewall or proxy instead of the bind address. Rewrites clone `request.nextUrl`. Redirects in preview routes use `FRONTEND_ORIGIN` when set, because `request.url` behind a proxy is the internal URL.
Consequences: Phase 6 deployment docs must state this. Re-test when Next.js is upgraded.

## ADR-015 — Cache tags, routes and the 404 check (Phase 3)

Decision: page and post data are tagged with their own tag and `site`, because settings (title template, canonical origin, branding) shape every page; settings and menu changes are rare, so invalidating everything then is acceptable. `/routes` now lists every public URL with a `noindex` flag (contract change), because proxy.ts must not 404 pages that exist but are hidden from search; the sitemap filters on the flag. proxy.ts keeps a per-instance route list (60 s TTL), refetches once on a miss (at most every 5 s) so new pages work immediately, and fails open on any error so outages never become 404s. Previews (Draft Mode or grant cookie) skip the check. Alternatives: an existence check per request (adds a CMS round trip to every page view); a shared cache between proxy and app (proxy may be deployed separately, per Next.js guidance).

## ADR-016 — Revalidation delivery and preview grants (Phase 3)

Decision: the plugin merges events per request and sends them at shutdown, signed per ADR-004, to `WSH_REVALIDATE_URL` (HTTPS only) or `{frontend}/api/revalidate`. Network errors, 5xx and 429 retry after 10, 60 and 300 s via WP-Cron with fresh signatures; other 4xx (bad signature, invalid event) are not retried. Tags are checked against the shared grammar before sending. The preview grant is the signed token itself, stored in an AES-256-GCM sealed HttpOnly SameSite=Lax cookie keyed by HKDF from `WSH_PREVIEW_SECRET`, and re-verified on every preview render. Drafts without a slug preview at `/preview-{id}` (or `/blog/preview-{id}`). The admin preview link is a raw URL with a nonce, because the editor uses it unescaped.
Consequences: settings and menus are not previewable (they have no drafts). Real cron is recommended in production for prompt retries; diagnostics warn when retries are overdue.

## ADR-017 — Hardening defaults (Phase 3)

Decision: on by default and individually switchable in the Integration tab: noindex and a 301 redirect of public CMS front-end requests to the website (wp-admin, login, REST, cron and media unaffected; never redirects to the same scheme, host and port); the core sitemap disabled; XML-RPC refused with 403; anonymous REST user listing and author archives blocked. Library post types are available over core REST only to users who can edit posts (the block editor needs them). Submissions have no REST route and no public URL.

## ADR-018 — Contact form flow (Phase 4)

Decision: browser → `POST /api/contact` (same-origin, 16 KiB, shared Zod schema) → `POST /websage/v1/contact` (server API secret). The client is identified by HMAC-SHA256(trusted client IP + UTC date) with `WSH_RATE_LIMIT_SECRET`; WordPress never sees the address. The trusted IP comes only from the header named in `WSH_CLIENT_IP_HEADER` (for `X-Forwarded-For`, the entry `WSH_TRUSTED_PROXY_HOPS` back from the end); unset in production → 503, never a guess. WordPress enforces 5 accepted attempts per 15-minute fixed window per client with one atomic `INSERT … ON DUPLICATE KEY UPDATE` on a plugin table (verified on SQLite and in CI on MariaDB). Validation errors do not count; honeypot hits do. Idempotency keys are checked before the limit, so a retried submission never consumes quota. Messages are stored privately before mail; mail failures retry after 5, 30 and 120 minutes. The visitor's address appears only in the mail body, never in headers. Turnstile is optional (server-side verification; CSP opened for Cloudflare only when a site key is set).
Alternatives: fixed-window vs sliding log (sliding needs more storage per client; fixed window is adequate at 5/15 min); trusting the left-most `X-Forwarded-For` (spoofable).

## ADR-019 — Demo seed (Phase 4)

Decision: `wp websage seed` upserts by `_wsh_seed_id`; a second run reports 0 created. It overwrites the two demo menus and demo settings keys (merged, other settings kept), sets the static front page, and never deletes content it did not create (so WordPress's sample post stays). No images are seeded, to keep the plugin zip free of demo media; image rendering is covered by component tests.

## ADR-020 — Metadata, sitemap and robots (Phase 4)

Decision: `generateMetadata`, `sitemap.xml` and page bodies read CMS data at request time (after `connection()`), from the tag-cached fetchers, so builds never need WordPress. `robots.txt` is static: `FRONTEND_ORIGIN` must be set at build time for its `Sitemap:` line. The sitemap lists only routes without `noindex`. JSON-LD escapes `<`. Articles use the organization as author, because the API never exposes WordPress authors.

## ADR-021 — Streamed metadata and Lighthouse measurement (Phase 5)

Context: Lighthouse 13 (mobile, plain Android Chrome user agent) flags a missing meta description because Next.js 16 streams `generateMetadata` output into `<body>` for browsers; HTML-limited crawlers (Bingbot, facebookexternalhit, …) get it in `<head>`. Next.js documents that JavaScript-rendering crawlers such as Googlebot read streamed metadata correctly.
Decision: keep the Next.js default (streaming). `htmlLimitedBots: /.*/` (blocking metadata for everyone) was measured: SEO 100 and Performance about +5, but in Next.js 16.4.2 with Cache Components and partial prefetching it made client RSC prefetch requests hang indefinitely on every page, holding server connections open. Lighthouse runs from `pnpm --filter @websage/web lighthouse` (median of 3) and is not part of CI, because scores depend heavily on the machine (benchmarkIndex 648–1350 during Phase 5).
Alternatives: blocking metadata (rejected above); build-time metadata (needs WordPress at build, contradicts ADR-020).
Consequences: Lighthouse SEO reports 91 locally; Performance 71–93 on this machine, below the ≥ 90 target. The owner decides whether to accept this and measure the deployed demo with PageSpeed Insights. Re-test `htmlLimitedBots` when Next.js is upgraded.

## ADR-022 — Client code splitting and Zod in the browser (Phase 5)

Decision: client-heavy sections load through a client-side `next/dynamic` boundary (`ContactFormLoader`), so their JavaScript downloads only on pages that render them while still being server-rendered. A server-side `next/dynamic` import of the section did not split the code under Turbopack (verified). Browser code imports `lib/zod-jitless.ts` before any schema module, so Zod never probes `new Function()` (the CSP has no `unsafe-eval`, and each probe was reported as a violation).
Consequences: new client sections with large dependencies should follow the same loader pattern; tests that render such sections await them (`findBy…`).

## ADR-023 — CMB2 callbacks own defaults and file IDs (Phase 5)

Context: CMB2 skips a field's default when `escape_cb` is set, and skips its file handling (which stores `{field}_id`) when `sanitization_cb` is set. Every settings field sets both.
Decision: the plugin's escape callbacks apply `val_or_default()`; the image sanitizer stores `{field}_id` itself, using the attachment ID resolved from the validated URL rather than the posted ID.
Consequences: any new CMB2 field with custom callbacks gets the same behaviour by using the `Sanitize` helpers.
