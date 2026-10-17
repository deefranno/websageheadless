# Changelog

All notable changes to WebSage Headless. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html): the plugin, the website and the contracts package share one version number.

Compatibility rule: a MAJOR version changes the API contract or settings in a way that needs both halves updated together. MINOR and PATCH releases of the plugin keep working with the same MAJOR version of the website (newer section types are ignored by older websites).

## [1.0.0-rc.1] - 2026-10-17

First release candidate.

### Added

- **WordPress plugin (WebSage Headless Core):** page-section blocks (hero, text and image, feature grid, call to action and saved call to action, testimonials, FAQ, contact form); WebSage Settings (branding, header, footer, contact, SEO, integration) with validation and a colour-contrast guard; SEO and testimonial metaboxes; testimonial and call-to-action libraries; main and footer menus; read-only API at `/wp-json/websage/v1`; signed refresh webhooks with retries; preview links for drafts; contact form endpoint with rate limiting, idempotency, honeypot, private storage and email with retries; Diagnostics screen; CMS hardening; `wp websage seed` demo content. Bundles CMB2 2.13.4.
- **Website (Next.js 16.4, React 19.3):** every section, navigation with keyboard submenus and a mobile menu, blog with pagination and categories, contact form, previews, real 404s, metadata, sitemap, robots, structured data; themeable from WordPress (logo, colours, fonts) with no code changes; security headers.
- **Contracts:** shared Zod schemas and fixtures checked by both PHP and TypeScript tests.
- **Tooling:** pnpm workspace, CI (lint, typecheck, tests, build, WordPress integration on PHP 8.1 and 8.3, browser tests), DDEV configuration, Docker-less test harness, Lighthouse script, release packaging (`pnpm package`).
- **Documentation:** install, developer, client, deployment, security and rebranding guides.

### Security

Found and fixed in the pre-release review:

- User enumeration through mixed-case REST routes (`/wp/v2/USERS`) and through oEmbed author fields.

### Fixed during release preparation

- Logos and sharing images chosen in WebSage Settings were never saved for the website.
- Local development: `http://localhost` is accepted as the website address (previews and webhooks reach a local dev server); every other host still needs HTTPS.
- Previews now show the draft's title and description in the browser tab.
- `pnpm --filter @websage/web start` runs the standalone production build with `.env.local`.

### Known limitations

- Lighthouse (mobile, local machine): Accessibility 100, Best Practices 100, SEO 91, Performance 71–93. To be re-measured with PageSpeed Insights on a deployed demo.
- The data cache is per Node.js process; self-hosted sites should run one instance or configure a shared cache handler.
- Static export is not supported.
- The frontend's commercial licence is a draft pending legal review.

[1.0.0-rc.1]: https://github.com/deefranno/websageheadless/releases/tag/v1.0.0-rc.1
