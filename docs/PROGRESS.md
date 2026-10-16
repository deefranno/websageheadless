# Progress

## Phase 1 — Architecture, approved

Completed artifacts:
- ARCHITECTURE.md: intent/options, Mermaid overview, repository layout, data model, block/schema catalogue, CMB2 field map, complete endpoint shapes/examples, component structure, caching/preview, security threats, deployment, extension seams, licensing proposal and phased tasks.
- DECISIONS.md: initialization pins with official sources and ADR-style context/decision/alternatives/consequences.

Inspection: only websage-headless-master-prompt.md existed in the project; no application, package manifests, tests or local environment. Git resolves to the parent Pictures repository with unrelated staged/untracked work. No existing files were overwritten; the master prompt is unchanged. No commit created in the parent repository.

Verification performed:
- `pwd`, `rg --files -g 'AGENTS.md' -g 'websage-headless-master-prompt.md' -g '!node_modules' -g '!vendor'`, and `ls -la`: located the prompt and inspected the initially empty project structure; no project AGENTS.md found.
- `git status --short` and `git rev-parse --show-toplevel`: identified parent repository and unrelated work; did not stage it.
- `rg --files --hidden .agents .codex`: no readable instruction files found there.
- Official web pages inspected for Next.js, React, Node, Tailwind, WordPress, CMB2 loading, block deprecations and revalidateTag; URLs and version observations recorded in DECISIONS.md.
- Python documentation check: all fenced JSON examples parse; all three documents exist and contain required architecture sections; no unresolved placeholders. This checks document structure only, not implementation correctness.

Not run: pnpm lint/typecheck/test/build, PHP tooling, DDEV, plugin activation, CI, Playwright, Lighthouse, accessibility tests or clean installation. There is no implementation yet; no functionality or performance claim is made. Mermaid syntax has not been rendered with a Mermaid engine.

Owner approved Phase 1 on 2026-10-16. The proposed architecture defaults are accepted, with final legal terms still requiring review before packaging.

Phase 2 planning: written implementation plan at `docs/superpowers/plans/2026-10-16-foundation.md`. Awaiting required plan review and execution-method selection before implementation. Recommend native execution. Read-only prerequisites show Node 20.19.2, npm 9.2.0, PHP 8.4.26; pnpm/Composer/Docker/DDEV not on PATH. Project .git remains protected and Git resolves to the parent Pictures repository; isolation must be resolved through permitted setup. No implementation has begun.

Known limitations/open decisions: license terms/legal review; font allowlist; retention; CSP/caching trade-off; standalone scaling; minimum supported WordPress; parent Git repository. Exact baseline pins are documented, not installed or tested; verify compatibility/security again at Foundation initialization. Security/support contact required before commercial packaging.

## Phase 2 — Foundation, implemented; awaiting gate review

Done: dedicated repository on `feat/foundation`; pnpm workspace with exact pins (ADR-008); contracts with shared fixtures; WebSage Headless Core plugin (activation checks, missing-vendor notice, bundled CMB2 2.13.4, `websage/hero` dynamic block with deprecation and server migration, public `GET /websage/v1/site` and `/pages/{slug}`, error envelope, uninstall); Next.js frontend (server-only validated API client, Cache Components data cache, sanitizer, section registry, hero, token-driven theme, layout and route states, standalone output, security headers); DDEV config and commands; CI workflow; Docker-less SQLite harness; Playwright editor round trip. Details and command output: `docs/reports/phase-2-verification.md`. Setup: `docs/FOUNDATION.md`.

Verified here: `pnpm lint`, `typecheck`, `test`, `build` all pass; PHP unit 30/30; WordPress integration 22/22 in both CMB2 load orders (SQLite); fresh activation and editor → frontend round trip (SQLite harness, production build).

CI green: GitHub Actions run 38954792820 (commit 2e9378c), all four jobs passed, including WordPress integration on PHP 8.1/8.3 against MariaDB.

Not verified: DDEV environment and its commands (no Docker on this machine).

ADR-011 approved 2026-10-16: soft 404 with noindex now; Phase 3 adds a proxy existence check for real 404 status.

Next: owner review of the Phase 2 gate; run `ddev websage-install`/`websage-test` on a Docker host; then Phase 3.

## Phase 3 — CMS Integration, implemented; awaiting gate review

Plan: `docs/superpowers/plans/2026-10-16-cms-integration.md`. Branch `feat/cms-integration`.

Done: contracts for every section, posts, routes (with noindex flag), preview and webhook events; versioned settings with a CMB2 options page (six tabs, explicit validation, contrast rollback), SEO and testimonial metaboxes; testimonial, CTA and private submission post types; menus as link trees; all catalogue blocks with editor UI, core-content normalization, synced patterns and library references; real `/site`, `/pages`, `/posts`, `/posts/{slug}`, `/routes` and authenticated `/preview/{id}`; signed webhooks with retries; nonce-protected preview links with 5-minute tokens; diagnostics page; CMS hardening; CORS closed on the namespace. Frontend: revalidation handler, Draft Mode preview with sealed grant cookie, proxy.ts real 404s, posts/routes/preview fetchers, core-content renderer, minimal post view.

Verified here (details in `docs/reports/phase-3-verification.md`): root lint/typecheck/test/build pass; PHP integration 53/53 in both CMB2 orders; Playwright 7/7 twice on the SQLite harness covering freshness, settings, preview, forged preview, diagnostics, editor round trip and real 404s.

Not verified yet: CI on the branch (needs a push); DDEV.

Next: push `feat/cms-integration`, confirm CI, owner review of the Phase 3 gate, then Phase 4 (section components, contact form, blog, SEO metadata, sitemap, seed).

## Phase 4 — Website Features, implemented; awaiting gate review

Plan: `docs/superpowers/plans/2026-10-16-website-features.md`. Branch `feat/website-features`.

Done: contact contracts; every section component (text-image, feature grid, CTA, testimonials, FAQ, contact) with one h1 per page; desktop navigation with keyboard submenus, mobile menu, footer contact details and back-to-top; contact form end to end (Next.js handler with proxy-trust that fails closed, WordPress endpoint with atomic rate limit, idempotency, honeypot, private storage, mail with retries, retention); blog index with pagination and categories, full post page; metadata, sitemap, robots, JSON-LD; `wp websage seed`.

Verified here (details in `docs/reports/phase-4-verification.md`): root lint/typecheck/test pass; contracts 109, web 101, PHP unit 30; PHP integration 64/64 in both CMB2 orders; Playwright 21/21 on two consecutive runs against the seeded harness; real WP-CLI seed re-run "0 created, 20 updated".

Not verified yet: CI on the branch (needs a push and a PR); DDEV.

Next: push `feat/website-features`, open the PR, confirm CI, owner review of the Phase 4 gate, then Phase 5 (testing and hardening: axe, Lighthouse, security review, rebranding test).
