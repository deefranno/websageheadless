# Phase 5 (Testing and Hardening) verification report

Date: 2026-10-16. Branch `feat/testing-hardening`. Environment: Linux, Node 24.22.0, pnpm 12.14.0, PHP 8.4.26 CLI, WordPress 7.1.3 on the SQLite harness (no Docker here), production Next.js 16.4.2 build behind the HTTPS proxies. CI adds MariaDB on PHP 8.1 and 8.3.

## Summary

| Area                               | Result                                                                                                                                         |
| ---------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Full test suite                    | All passing: contracts 109, web 101, PHP unit 30, PHP integration 72 in each CMB2 load order, Playwright 53                                    |
| PHPCS / PHPStan                    | Clean (WPCS 3.4.1; PHPStan level 8)                                                                                                            |
| Accessibility (axe, WCAG 2.2 A/AA) | 0 violations on 9 pages × 2 widths and 5 interactive states, plus the rebranded site                                                           |
| Lighthouse (mobile)                | Accessibility 100, Best Practices 100, SEO 91, Performance 71–93 depending on run (target ≥ 90 **not met** for Performance and SEO; see below) |
| Security review                    | 11 threat rows reviewed; 2 vulnerabilities found and fixed (user enumeration), tests added for every row                                       |
| Rebranding test                    | Passes: logo, 8 colours and 2 fonts changed in WordPress restyle every page with zero component edits                                          |
| Defects found and fixed            | 7 (below)                                                                                                                                      |

## Defects found and fixed

| #   | Found by          | Defect                                                                                                                                                                                 | Fix                                                                                             |
| --- | ----------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| 1   | Security review   | Anonymous user listing via `/wp/v2/USERS`: WordPress matches REST routes case-insensitively; the block matched lowercase only                                                          | Case-insensitive match; test covers four spellings and `?rest_route=` (verified over HTTP: 401) |
| 2   | Security review   | oEmbed responses exposed author display name and author archive URL                                                                                                                    | `author_name`/`author_url` removed when user-enumeration protection is on                       |
| 3   | Rebranding test   | Logo and default sharing image chosen in WebSage Settings never reached the site: the custom sanitizer bypassed CMB2's file handling, so `logo_id`/`seo_og_image_id` were never stored | Sanitizer stores the attachment ID resolved from the validated URL (never the posted ID)        |
| 4   | Rebranding test   | Every save of WebSage Settings reported "Keep form messages for (days) must be a whole number": CMB2 skips defaults when a custom `escape_cb` is set, so unset fields rendered empty   | Escape callbacks apply the field default                                                        |
| 5   | Rebranding review | Feature-grid links used primary text on the page background, a pair the contrast guard does not check (a light brand colour would make them unreadable)                                | Links inherit the section text colour                                                           |
| 6   | Lighthouse        | Contact form JavaScript and Zod (35 KiB gzip) shipped on every page                                                                                                                    | Client-side `next/dynamic` boundary; still server-rendered                                      |
| 7   | Lighthouse / CSP  | Zod probed `new Function()` and the CSP reported a violation on the contact page                                                                                                       | Zod `jitless` configured before schemas load in the browser                                     |

Also: seeded feature links now have descriptive text instead of three identical "Learn more" links (Lighthouse link-text; clearer for screen-reader link lists).

Each code fix has a test that fails without it (checked for 1–4 by running the new tests against the unfixed code).

## Accessibility

`apps/web/tests/e2e/accessibility.spec.ts`, `@axe-core/playwright` 4.13.0, tags wcag2a/aa, wcag21a/aa, wcag22a/aa, no rules disabled.

- Pages at 1280 px and 375 px: home, about, services, contact, blog index, blog category, blog page 2, a post, 404.
- States: desktop submenu open, mobile menu open, FAQ answer open, contact form with errors, contact form after sending.
- Every keyboard tab stop (first 12) has a visible outline; `prefers-reduced-motion` disables transitions and smooth scroll.
- Sanity check: axe reports `image-alt` and `color-contrast` for deliberately broken markup, so the clean result is not a misconfiguration. The only "needs review" item is the decorative, `aria-hidden` ▾ in the About menu button.

Not covered by automation: screen-reader testing with real assistive technology, zoom to 400 %, and Windows high-contrast mode. Recommended before launch on client sites.

## Lighthouse

`pnpm --filter @websage/web lighthouse` (Lighthouse 13.5.0, mobile, simulated throttling, Playwright's Chromium, median of 3 runs per page). Final run on the committed build:

| Page                          | Performance | Accessibility | Best Practices | SEO |
| ----------------------------- | ----------- | ------------- | -------------- | --- |
| /                             | 84          | 100           | 100            | 91  |
| /services                     | 86          | 100           | 100            | 91  |
| /about                        | 86          | 100           | 100            | 91  |
| /contact                      | 80          | 100           | 100            | 91  |
| /blog                         | 83          | 100           | 100            | 91  |
| /blog/our-autumn-availability | 71          | 100           | 100            | 91  |

Across the session's runs on this machine, Performance ranged 71–93 per page and the machine's benchmarkIndex ranged 648–1350 (other load on the machine). The numbers are not comparable to PageSpeed Insights on Google's hardware; measure the deployed site there.

What limits Performance: simulated LCP 2.1–3.4 s while the observed (unthrottled) LCP is 0.17–0.62 s and FCP is ~0.8 s simulated. Pages stream their content after a prerendered shell (Cache Components); React inserts streamed content with inline scripts, so Lighthouse's simulator orders the largest paint after ~185 KiB (gzip) of framework JavaScript. Total Blocking Time 400–880 ms simulated is React/Next.js hydration. CLS ≤ 0.041.

What limits SEO (91): `meta-description` fails because Next.js streams metadata into `<body>` for browsers. Next.js serves metadata in `<head>` to crawlers it recognises as HTML-limited (Bingbot, Twitterbot, facebookexternalhit, …) and documents that streamed metadata is read correctly by JavaScript-rendering crawlers such as Googlebot. Lighthouse 13 sends a plain Android Chrome user agent, so it sees the streamed variant. I could not confirm whether PageSpeed Insights' user agent contains `Chrome-Lighthouse` (which Next.js treats as HTML-limited).

Tried and rejected: `htmlLimitedBots: /.*/` (metadata in `<head>` for everyone) raised SEO to 100 and Performance to ~90, but in Next.js 16.4.2 it made client-side RSC prefetch requests hang indefinitely on every page (verified in the browser; each holds a server connection open). See ADR-021.

## Security review

| Threat (ARCHITECTURE.md)     | Verification                                                                                                                                                                                                                          | Result                    |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------- |
| Draft/PII disclosure         | Unpublished/protected pages indistinguishable from absent; preview needs server secret + grant; submissions have no REST route or URL; responses carry no author data; anonymous core REST denied for users and library types         | Pass                      |
| Stored/reflected XSS         | Sanitizer allowlist tests, script contents dropped, JSON-LD `<` escaping, token CSS re-validated; new: submission screens escape visitor input                                                                                        | Pass                      |
| Forged webhook/preview       | HMAC over timestamp.event.body, ±300 s, tampering/expiry/unsafe path tests; new HTTP check: preview entry with a forged token never redirects off-site and is `no-store`                                                              | Pass                      |
| CSRF/privilege escalation    | New: diagnostics actions refuse subscriber and editor with valid nonces, and an administrator with a forged nonce; preview links refuse other authors and nonces for other posts; same-origin checks on contact and preview exit      | Pass                      |
| Spam/email abuse             | Rate limit, idempotency, honeypot, control-character rejection, visitor address never in mail headers, proxy trust fails closed                                                                                                       | Pass                      |
| SSRF/open redirects          | API client refuses redirects and non-HTTPS origins; revalidation URL HTTPS only; new: image optimizer refuses non-CMS hosts (400)                                                                                                     | Pass                      |
| Origin duplicate indexing    | noindex, core sitemap off, front-end redirect (including different port), never to itself                                                                                                                                             | Pass                      |
| User enumeration/XML-RPC     | **Fixed #1 and #2**; author probes redirected to the website (or 404 with the redirect off); XML-RPC off                                                                                                                              | Pass after fix            |
| Supply chain                 | CMB2 coexistence in both load orders; `pnpm audit --prod`: 0; `composer audit`: 0; `pnpm audit` (all): 8 advisories (2 high, 5 moderate, 1 low), all in build-time tooling (`@wordpress/scripts`, `eslint-config-next`), none shipped | Accepted, see limitations |
| Availability/cache poisoning | Size, depth and section limits, timeouts, strict schemas, 413/422 paths                                                                                                                                                               | Pass                      |
| Sensitive logs               | Reviewed every log call: frontend logs API paths only (no query, headers, bodies or IPs); plugin logs request IDs and codes, submission IDs only                                                                                      | Pass (by inspection)      |

Frontend HTTP checks (`security.spec.ts`): CSP with `frame-ancestors 'none'`, `object-src 'none'`, `base-uri 'self'` and no `unsafe-eval`; HSTS; nosniff; Referrer-Policy; Permissions-Policy; no `X-Powered-By`; no server secret in any page HTML or client script; GET refused on API routes; cross-site POSTs refused.

## Rebranding test

`apps/web/tests/e2e/rebranding.spec.ts`; checklist in `docs/REBRANDING.md`. Through the real WebSage Settings screen (the media modal and colour picker inputs are set directly): new logo, all eight colours, both fonts to system. On seven pages plus the contact error state and mobile menu: background, text, fonts and logo are the new ones, **no visible element uses any of the old colours** (the same check finds 156 elements on the default brand, so it detects leftovers), and axe reports no violations with the new palette. Settings are restored afterwards.

## Commands run

```text
pnpm lint / typecheck / test                       exit 0 (contracts 109, web 101, PHP unit 30)
composer check                                     PHPCS clean, PHPStan level 8 OK
composer test:integration (both CMB2 orders)       72/72 twice (SQLite)
pnpm --filter @websage/web test:integration        53/53 on a fresh seeded harness
pnpm --filter @websage/web lighthouse              table above
pnpm audit --prod / composer audit                 no advisories
```

## Known limitations

- Lighthouse Performance and SEO are below the ≥ 90 target on this machine (above). Needs a decision: accept and measure the deployed demo on PageSpeed Insights, or invest in reducing the hydration JavaScript and streaming cost (no straightforward fix found within Next.js 16.4.2 defaults).
- Build-time dependency advisories (8) remain; fixing them requires major-version overrides inside `@wordpress/scripts` and `eslint-config-next`. None is in shipped code. Revisit when upstream releases.
- The rebranding test sets the WordPress colour-picker and media-modal inputs directly rather than driving those core widgets.
- Fixed-window rate limiting allows up to 10 messages around a window boundary (ADR-018).
- Not verified here: DDEV (no Docker), real screen readers, MariaDB locally (CI covers it).
