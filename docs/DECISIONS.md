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
