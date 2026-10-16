# WebSage Headless — Claude Code Master Prompt (v2)

## ROLE

Act as a senior full-stack architect with deep expertise in Next.js (App Router), TypeScript, WordPress plugin development, and application security. You are building a commercial product, not a client site.

## PRODUCT

**WebSage Headless** is a commercially distributable starter kit by WebSage Creative: a Next.js frontend with WordPress as a headless CMS.

- **Buyers:** freelance developers and agencies who deploy it repeatedly for their own clients.
- **End users:** nontechnical clients who manage all content in wp-admin without touching React code.
- **Quality bar:** modular, secure, documented, testable, and rebrandable in under an hour by a competent developer.

## OPERATING RULES (apply to every phase)

1. **Phase gates.** Stop at the end of every phase, report results, and wait for my approval before starting the next one.
2. **Inspect before changing.** Read existing files before modifying them. Never delete or overwrite files you did not create without asking.
3. **No unverified claims.** Do not call anything "working" unless you ran it. Report exactly which commands you ran and their results. If something could not be tested, say so.
4. **Decision log.** Maintain `docs/DECISIONS.md` (ADR-style: context, decision, alternatives, consequences) and `docs/PROGRESS.md` (what's done, what's next, known issues). Update both at the end of each phase.
5. **Ask when blocked.** If a requirement is ambiguous or two requirements conflict, stop and ask with a recommended option. Do not silently pick one.
6. **Small, reviewable commits** using Conventional Commits (`feat:`, `fix:`, `chore:`, `docs:`, `test:`).
7. **Verify current versions.** At initialization, check and pin the current stable versions of Next.js, React, Node LTS, Tailwind, and WordPress. Record them in `docs/DECISIONS.md`. Do not rely on remembered version numbers or APIs; check documentation when unsure.
8. **No paid or external plugin dependencies.** The kit must run with WordPress plus WebSage Headless Core only. No ACF (Free or Pro), no premium plugins, no paid services.

## TECHNOLOGY

**Frontend**
- Next.js App Router, React, TypeScript (`strict: true`, `noUncheckedIndexedAccess: true`)
- Tailwind CSS driven by CSS variables (design tokens)
- Server Components by default; Client Components only where interaction requires it
- `next/font` for fonts, `next/image` for images
- Zod (or equivalent) for runtime validation of every WordPress API response

**Backend**
- WordPress (latest stable), PHP **8.1 minimum**, tested on 8.3
- Native Gutenberg blocks (`block.json`, `@wordpress/scripts`) for page sections
- CMB2 for settings and structured post meta, **bundled inside the plugin via Composer** (buyers never install it separately)
- WordPress REST API with a custom namespace: `websage/v1`
- Custom plugin: **WebSage Headless Core**

**Tooling**
- pnpm workspaces monorepo
- ESLint + Prettier (frontend); PHPCS with WordPress Coding Standards + PHPStan (plugin)
- Vitest + React Testing Library (unit/component), Playwright (end-to-end), PHPUnit (plugin)
- DDEV for the local WordPress environment (must work on Linux; do not assume Docker Desktop or LocalWP)
- GitHub Actions CI: lint, typecheck, tests, production build

Do not introduce WPGraphQL. Do not introduce any additional framework or service without recording the justification in `docs/DECISIONS.md` and getting approval.

## CONTENT ARCHITECTURE — READ CAREFULLY

Each kind of content has one designated tool. Do not mix them without recording a justification in `docs/DECISIONS.md`.

| Content | Tool | Notes |
|---|---|---|
| Page sections (Hero, Text/Image, Feature grid, CTA, Testimonials, FAQ, Contact) | Native Gutenberg blocks | Clients build and reorder pages visually in the editor |
| Global settings, branding, header, footer, social, contact details | CMB2 options page | One plugin-owned admin page with tabs |
| Structured post meta (SEO fields, testimonial author/role/company, etc.) | CMB2 metaboxes | Repeatable groups allowed |
| Navigation | Native menus (`register_nav_menus`) | Exposed through a public custom endpoint; core `wp/v2/menus` requires auth |
| Reusable content (testimonials library, global CTAs) | Custom post types + Synced Patterns where suitable | |

### Gutenberg blocks

- Namespace `websage/*` (e.g. `websage/hero`, `websage/faq`, `websage/faq-item`).
- Registered via `block.json`, built with `@wordpress/scripts`, editor UI from `@wordpress/components` (`RichText`, `MediaUpload`, `InspectorControls`, `LinkControl`).
- **Lists inside sections** (FAQ items, features, testimonial references) use **InnerBlocks with child blocks**, which gives clients add/remove/reorder without any repeater field.
- Blocks are **dynamic with no PHP frontend render** required: the editor saves attributes; the plugin parses content server-side with `parse_blocks()` and returns a typed `sections[]` array. Block attributes are the data contract and must match the Zod schemas in `packages/contracts`.
- Restrict the allowed block list on pages to `websage/*` blocks plus a curated core set (paragraph, heading, list, image, quote) so clients can't insert blocks the frontend can't render.
- Provide block templates for default page types so new pages start with a sensible structure.
- Include block deprecations from the start so attribute changes in future releases don't break existing content.

### CMB2

- Required via Composer (`cmb2/cmb2`) and loaded from the plugin's `vendor/` directory. CMB2 resolves multiple bundled copies by loading the newest, so it coexists with other plugins that bundle it. Verify this behaviour in a test.
- All fields defined in code under `src/Fields/`, one class per metabox/options page. No database-stored field configuration.
- Every field declares an explicit `sanitization_cb` and `escape_cb` appropriate to its type. Do not rely on defaults for URL, email, colour, HTML, or numeric fields.
- Do **not** use CMB2's own REST exposure (`show_in_rest`). All data reaches the frontend only through the `websage/v1` endpoints, so the response shape stays under our control.
- Nested repeatable groups are not supported by CMB2. If a structure seems to need one, model it as a CPT or child blocks instead.
- Apply light admin styling to CMB2 screens so they look consistent with the block editor; no heavy UI framework.

## REPOSITORY STRUCTURE (proposed; confirm in Phase 1)

```
websage-headless/
├── apps/web/                  # Next.js frontend
├── packages/wp-plugin/        # WebSage Headless Core (installable zip built from here)
├── packages/contracts/        # Shared API types + Zod schemas (single source of truth)
├── demo/                      # Demo content + seed/import tooling
├── docs/                      # Developer, client, deployment docs, DECISIONS.md, PROGRESS.md
├── .ddev/                     # Local WordPress environment
└── .github/workflows/         # CI
```

## WORDPRESS PLUGIN — WEBSAGE HEADLESS CORE

Responsibilities:

1. Global settings and branding (site name, logo, brand colour tokens, fonts, social links, contact details).
2. Header and navigation configuration.
3. Footer configuration.
4. Reusable content sections as native `websage/*` Gutenberg blocks.
5. Page templates and page content.
6. Contact form submission handling and storage.
7. Public read endpoints under `websage/v1`.
8. SEO metadata per post/page with site-wide defaults.
9. Integration diagnostics admin page: frontend URL reachable, revalidation webhook test, secrets configured, loaded CMB2 version and source path, PHP/WP versions.
10. Revalidation webhook dispatch to the frontend on content changes.
11. Headless hardening of the WordPress install itself (see SEO section).

Plugin standards:
- PHP namespace `WebSage\HeadlessCore`, prefix `wsh_` for hooks/options, text domain `websage-headless` with i18n-ready strings.
- Composer autoloading (PSR-4), no global-namespace functions except bootstrap.
- The distributable zip ships `vendor/` built with `composer install --no-dev --optimize-autoloader`, plus compiled block assets. Evaluate PHP-Scoper (or similar) for prefixing third-party dependencies other than CMB2 to avoid conflicts with other plugins; record the decision.
- `uninstall.php` that removes plugin options and (opt-in) submissions.
- Activation checks for PHP/WP minimum versions with a clear failure message.
- Settings stored and versioned so future releases can migrate them.

## API CONTRACT

Define the full contract in Phase 1. At minimum:

| Endpoint | Method | Auth | Purpose |
|---|---|---|---|
| `/websage/v1/site` | GET | Public | Settings, branding tokens, header, footer, menus |
| `/websage/v1/pages/{slug}` | GET | Public | Page content, sections, SEO |
| `/websage/v1/posts` | GET | Public | Paginated blog list (category filter) |
| `/websage/v1/posts/{slug}` | GET | Public | Single post + SEO |
| `/websage/v1/routes` | GET | Public | All published URLs + `modified` dates (sitemap, static params) |
| `/websage/v1/preview/{id}` | GET | Server-only secret | Draft/revision content for preview |
| `/websage/v1/contact` | POST | Server-only secret | Contact submissions (called only by Next.js) |

Requirements:
- Responses contain only what the frontend needs; never leak author emails, internal IDs beyond what's needed, or user data.
- Every response is validated by Zod schemas in `packages/contracts`; a schema mismatch must fail loudly in development and degrade safely in production.
- Version the namespace so breaking changes can ship as `websage/v2`.

## FRONTEND

Components (all content-driven from WordPress where appropriate):
Header, Navigation, Mobile menu, Footer, Hero, Text/Image, Feature grid, CTA, Testimonials, FAQ (accessible disclosure pattern), Contact form, Blog listing (paginated), Blog detail, 404, Back-to-top.

Requirements:
- A **section registry** that maps section type → component, so adding a new section requires one block/field definition plus one component and one registry entry.
- Unknown section types render nothing in production and a visible warning in development.
- Loading (`loading.tsx`), error (`error.tsx`), empty, and not-found states for every route.
- Single centralized API client (server-only module) with typed fetchers, timeouts, and cache tags.
- **HTML sanitization:** any rich text from WordPress (post content, WYSIWYG fields) must be sanitized server-side with an allowlist before rendering. No unsanitized `dangerouslySetInnerHTML`.
- WordPress-relative links inside content are rewritten to frontend routes.

## CONTENT FRESHNESS AND PREVIEW

- **On-demand revalidation:** the plugin sends a signed webhook (HMAC with a shared secret) to a Next.js route handler on publish, update, trash, menu change, and settings change. The handler verifies the signature and calls `revalidateTag` for the affected tags only.
- A time-based fallback revalidation interval for resilience.
- **Draft preview:** a "Preview" button in wp-admin opens the Next.js site in Draft Mode, authenticated by a short-lived signed token. Nontechnical clients must be able to preview unpublished changes. Preview responses are never cached.

## DESIGN SYSTEM

- Sora for headings, Montserrat for body, loaded via `next/font` and overridable from config.
- Brand colours as CSS variables, populated at server render from WordPress branding settings, with defaults in code.
- Defined type scale, spacing scale, radius, and shadow tokens; Tailwind config reads tokens, components never hard-code colours.
- Responsive from 320px up. WCAG 2.2 AA: contrast, focus states, keyboard navigation, reduced-motion respect, skip link.
- Minimal motion. No decorative animation libraries.
- **Rebranding test:** changing logo, colours, and fonts in WordPress (plus optional token file) must restyle the entire site with zero component edits. Document this as a checklist and verify it.

## SECURITY

- WordPress capability checks on every admin action; nonces on all admin forms.
- `permission_callback` on every REST route (no `__return_true` on write routes).
- Sanitize on input, escape on output, `$wpdb->prepare()` for any custom query.
- **Contact form:** browser → Next.js route handler → WordPress. The browser never talks to WordPress directly. Includes server-side validation (shared Zod schema), honeypot, rate limiting (per hashed IP, enforced in WordPress, optional edge layer in Next.js), optional Cloudflare Turnstile, email notification via `wp_mail`, and storage in a private `ws_submission` CPT visible only to admins.
- Because all WordPress calls are server-side, **CORS should be closed by default**. Only open it if a documented browser-to-WordPress need exists.
- Secrets (revalidation secret, preview secret, server API secret) live only in server environment variables. Nothing privileged uses a `NEXT_PUBLIC_` prefix. Provide `.env.example` with descriptions.
- Security headers in Next.js: CSP (nonce-based where feasible), HSTS, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `frame-ancestors`.
- `next/image` `remotePatterns` restricted to the configured WordPress media host.
- Errors shown to users are generic; details go to server logs only. WordPress debug output never reaches the frontend.
- Disable XML-RPC and user enumeration via REST on the CMS host (configurable).

## SEO AND PERFORMANCE

- All public content server-rendered.
- `generateMetadata` per route: title, description, canonical, Open Graph, Twitter cards, with site-wide fallbacks.
- `sitemap.ts` and `robots.ts` generated from `/websage/v1/routes`.
- JSON-LD: Organization, WebSite, BreadcrumbList, Article (blog posts).
- **Headless hardening:** the WordPress origin must not compete in search. The plugin sets `noindex` on the CMS frontend, disables the core WP sitemap, and redirects public front-end requests on the CMS domain to the Next.js domain (wp-admin, wp-json, and media excluded).
- Performance target: Lighthouse mobile ≥ 90 for Performance, Accessibility, Best Practices, and SEO on the demo site. Report actual scores.

## DEPLOYMENT

The kit must support both:
1. **Vercel** (or similar managed Next.js host).
2. **Self-hosted Node** using `output: 'standalone'` behind a reverse proxy, including Node app hosting on cPanel/LiteSpeed-style environments where feasible.

Document environment variables, build commands, revalidation setup, and the WordPress-side configuration for each. Static export is not supported (it breaks revalidation and preview); state this explicitly in docs.

## DEMO CONTENT

Provide a repeatable way to load the demonstration site: a WP-CLI command (`wp websage seed`) that creates pages, sections, posts, menus, and settings. Running it twice must not create duplicates.

## LICENSING NOTE

The WordPress plugin is a derivative of WordPress and must be licensed GPL-2.0-or-later. The Next.js frontend can carry a separate commercial license. In Phase 1, propose the license structure and flag anything I need to confirm with legal advice. Keep architecture ready for future license-key validation and plugin update delivery, but do not implement them in v1.

## OUT OF SCOPE FOR v1 (design for, don't build)

WooCommerce, paid licensing/updates, visual page builder, multilingual, multisite, user accounts on the frontend. Phase 1 must show where each would plug in.

## DEVELOPMENT PHASES

### Phase 1 — Architecture (no implementation)
Inspect the directory. Deliver in `docs/ARCHITECTURE.md`:
- System overview diagram (Mermaid)
- Repository structure
- WordPress data model (CPTs, taxonomies, settings, fields, blocks)
- Block catalogue: every `websage/*` block with its attributes, child blocks, and matching Zod schema
- CMB2 field map: every options page and metabox with field types and sanitization
- Full API contract with example JSON payloads
- Component architecture and section registry design
- Caching, revalidation, and preview flow
- Security model and threat list
- Deployment model
- MVP plan with phases broken into tasks
- Open questions for me

**Stop and wait for approval.**

### Phase 2 — Foundation
Monorepo, tooling, CI, DDEV environment, plugin skeleton with activation checks and bundled CMB2, block build pipeline with one working sample block end to end (editor → `parse_blocks()` → endpoint → Zod → React component), contracts package, API client, design tokens, base layout, routes with placeholder data. **Exit criteria:** `pnpm lint`, `pnpm typecheck`, `pnpm test`, and `pnpm build` pass; plugin activates cleanly on a fresh DDEV site with no other plugins installed; CI green.

### Phase 3 — CMS Integration
Settings page, menus, section structures, all read endpoints, revalidation webhook, preview flow, diagnostics page. **Exit criteria:** editing any content in WordPress updates the frontend within seconds without a redeploy; preview shows unpublished changes; endpoint tests pass.

### Phase 4 — Website Features
All components, contact form end to end, blog, SEO metadata, sitemap, robots, JSON-LD, demo seed command. **Exit criteria:** Playwright suite covers navigation, page rendering, blog list/detail, contact submission (success, validation error, rate limit), 404, and mobile menu.

### Phase 5 — Testing and Hardening
Full test suite, PHPCS/PHPStan clean, security review against the threat list, accessibility audit (axe in Playwright), Lighthouse run, rebranding test. Fix verified issues only. **Exit criteria:** a written report of what was tested, results, and any known limitations.

### Phase 6 — Commercial Packaging
- `docs/INSTALL.md` — from zero to running site
- `docs/DEVELOPER.md` — architecture, adding a new block section (block + schema + component + registry entry, as a step-by-step recipe), adding CMB2 fields, extending the API, theming
- `docs/CLIENT-GUIDE.md` — plain-language wp-admin guide for nontechnical clients
- `docs/DEPLOYMENT.md` — Vercel and self-hosted paths
- `docs/SECURITY.md` — model, configuration, responsible disclosure contact
- `CHANGELOG.md` and semantic versioning
- Build script producing a distributable plugin `.zip` and a clean frontend starter (no dev secrets, no demo-only junk)
- `LICENSE` files per the approved structure

**Exit criteria:** a fresh install following only `INSTALL.md` succeeds on a clean machine.

## DEFINITION OF DONE (v1)

- A developer can install the kit, seed the demo, and have it running locally in under 30 minutes using only the docs.
- A nontechnical client can change branding, menus, page sections, posts, and SEO fields, preview, and publish, with changes live on the frontend without developer involvement.
- The WordPress side requires only WebSage Headless Core; no other plugin needs to be installed.
- All checks pass in CI, and every feature claimed in docs has a corresponding test or a documented manual verification.

Begin with Phase 1.
