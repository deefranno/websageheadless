# WebSage Headless Phase 3 (CMS Integration) Plan

**Goal:** Everything a client edits in wp-admin (settings, menus, page sections, posts, SEO, reusable content) reaches the frontend through validated `websage/v1` endpoints, refreshes within seconds of publishing, and can be previewed before publishing.

**Spec:** [Architecture](../../ARCHITECTURE.md), [decisions](../../DECISIONS.md), [master prompt](../../../websage-headless-master-prompt.md). Branch `feat/cms-integration`.

**Exit criteria (master prompt):** editing any content in WordPress updates the frontend within seconds without a redeploy; preview shows unpublished changes; endpoint tests pass. Plus Phase 2 carry-overs: ADR-011 real-404 check, CORS closed on the namespace, headless hardening.

**Out of scope (Phase 4):** visual renderers for the non-hero sections, mobile menu, contact submission handling, blog UI, metadata/sitemap/JSON-LD, seed command. Phase 3 delivers their data contracts and endpoints; the registry already skips unrendered types safely.

## Task 1: Contracts

All section schemas from the block catalogue (text-image, feature-grid, cta, testimonials, faq, contact, core-content) as a discriminated union with the unknown-type envelope; `PostSummary`, `Post`, posts list, routes list, preview, revalidation event. Shared fixtures for each endpoint. Tests: valid fixtures, malformed known types rejected, unknown types reduced to the envelope, limits.

## Task 2: Plugin content model

- `Settings` repository: single `wsh_settings` option, `schema_version`, defaults materialized server-side, idempotent migrations.
- CMB2 options page `wsh-settings` (tabs: Branding, Header, Footer, Contact, SEO, Integration) with explicit sanitization and escaping callbacks; validators reject invalid values.
- CMB2 metaboxes: SEO (pages, posts), testimonial details.
- CPTs `ws_testimonial`, `ws_cta`; menu locations `primary`, `footer`.
- Blocks: the remaining catalogue blocks with editor UI, `block.json`, deprecation scaffolding, InnerBlocks parent/child rules; curated core blocks normalized to `core-content`; synced patterns resolved with cycle detection; allowed-block list and page templates.

## Task 3: Read endpoints

`/site` from real settings and menus; `/pages/{slug}` with full sections and SEO; `/posts`, `/posts/{slug}`, `/routes`; `/preview/{id}` behind the server secret plus a signed grant. Published-only mappers; error envelope; CORS closed on the namespace. Integration tests for every endpoint, the auth matrix and the limits.

## Task 4: Freshness and preview

- Plugin: reference index (pages using CTAs, testimonials and patterns); signed webhook dispatcher (HMAC over timestamp, event ID and body) on publish, update, trash, slug change, menu and settings changes; retries at 10, 60 and 300 seconds via cron, with last outcome stored privately.
- Plugin: "Preview in frontend" for drafts and revisions: capability and nonce checks, 5-minute HMAC token.
- Frontend: `POST /api/revalidate` (signature, ±300 s window, tag grammar, `revalidateTag(tag, { expire: 0 })`); `GET /api/preview` (token check, Draft Mode, scoped encrypted grant cookie, safe redirect); `POST /api/preview/exit`; preview fetches bypass caches.
- Frontend `proxy.ts`: real 404 for paths absent from the cached routes list (ADR-011 follow-up).

## Task 5: Diagnostics and hardening

Diagnostics admin page (frontend reachable, webhook test, secrets configured yes/no, CMB2 version and path, PHP/WP versions, last delivery). CMS hardening (configurable): noindex the CMS front end, disable the core sitemap, redirect public front-end requests to the frontend, disable XML-RPC and REST user enumeration.

## Task 6: Verification and gate

Playwright: publish change → frontend updates within seconds; draft → preview shows the unpublished change; preview never cached. Update DECISIONS/PROGRESS, write the Phase 3 report, CI green on the PR, stop at the gate.
