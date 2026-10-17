# WebSage Headless Phase 4 (Website Features) Plan

**Goal:** A complete, accessible demo website: every section renders, visitors can navigate on any screen size, read the blog, and send the contact form; search engines get correct metadata, sitemap and structured data; developers can load the demo with one command.

**Spec:** [Architecture](../../ARCHITECTURE.md), [decisions](../../DECISIONS.md), [master prompt](../../../websage-headless-master-prompt.md). Branch `feat/website-features`.

**Exit criteria (master prompt):** Playwright covers navigation, page rendering, blog list/detail, contact submission (success, validation error, rate limit), 404 and the mobile menu.

## Task 1: Contracts

`ContactInputSchema` (name 1–120, email ≤ 254, message 1–5,000, `website` honeypot ≤ 200, `consent: true`, `idempotencyKey` UUID), browser variant with optional `turnstileToken`, result and field-error envelopes. Shared fixtures and tests.

## Task 2: Section components and navigation

Text-image, feature grid, CTA, testimonials, FAQ (native `details`/`summary`), contact (client island) in the registry. Header with nested primary menu (keyboard accessible), mobile menu client island (focus handling, Escape, `aria-expanded`), footer menu, back-to-top link. Component tests with Testing Library.

## Task 3: Contact form end to end

- Plugin `POST /websage/v1/contact`: bearer secret, 16 KiB limit, unknown properties rejected, shared validation rules, honeypot (accepted without storage), atomic per-hash rate limit (5 per 15 minutes, custom table), idempotency keys (same payload → same acceptance; different payload → 409), private `ws_submission` storage before mail, `wp_mail` to the configured recipient with bounded retries, daily retention cleanup, 24-hour hash expiry.
- Next.js `POST /api/contact`: same-origin check, size limit, Zod validation, client hash = HMAC(trusted client IP + UTC date) with the rate-limit secret, explicit proxy-trust configuration that fails closed, optional server-side Turnstile, response mapping (200, 409, 422 field codes, 429 Retry-After, 503).
- Accessible form: labels, inline errors, live-region status, retained input on failure, success message from the block.

## Task 4: Blog

Index with URL-driven pagination and category filter, empty states; post detail with featured image, categories and date.

## Task 5: SEO

`generateMetadata` for pages, posts and the blog (title, description, canonical, Open Graph, Twitter, robots) with site-wide fallbacks; `sitemap.ts` from `/routes` (indexable entries); `robots.ts`; JSON-LD Organization, WebSite, BreadcrumbList, Article with `<` escaping.

## Task 6: Demo seed

`wp websage seed`: pages (Home, About, Services, Contact), posts with categories, testimonials, a global CTA, menus, settings and front page. Stable seed IDs in meta; running twice creates no duplicates. Integration test plus a real WP-CLI run in the harness.

## Task 7: Verification and gate

Playwright on the seeded harness: navigation, every section, blog list/detail/pagination, contact success / validation / rate limit, 404, mobile menu. Root checks, PHP checks, CI green on the PR, DECISIONS/PROGRESS/report, stop at the gate.
