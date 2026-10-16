# Phase 4 (Website Features) verification report

Date: 2026-10-16. Branch `feat/website-features`. Environment: Linux, Node 24.22.0, pnpm 12.14.0, PHP 8.4.26 CLI, WordPress 7.1.3 on the SQLite harness (no Docker here). CI covers MariaDB on PHP 8.1 and 8.3 once a PR is open.

## Exit criteria: Playwright coverage

All on the seeded demo, production Next.js build behind HTTPS, 21/21 passing on two consecutive runs.

| Required           | Tests                                                                                                                                                                                                                    |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Navigation         | main nav to every top-level page with `aria-current`; keyboard submenu (Enter opens, Escape closes and returns focus); skip link; back-to-top                                                                            |
| Page rendering     | every section type on /services, home and /about (one h1, regions named by headings, FAQ disclosure opens); title, description, canonical, Open Graph; JSON-LD Organization, WebSite, BreadcrumbList; sitemap and robots |
| Blog list/detail   | 9 per page, "Page 1 of 2", older posts, category filter; post detail with date, sections, title, Article JSON-LD, back link; missing post 404                                                                            |
| Contact submission | success (confirmation, form cleared); validation error (summary, `aria-invalid`, linked descriptions); rate limit (sixth message in 15 minutes refused, input kept)                                                      |
| 404                | unknown page and unknown post return HTTP 404 with the not-found UI and noindex                                                                                                                                          |
| Mobile menu        | 375 px: toggle state, focus moves into the panel, Escape returns focus, navigation closes it; no horizontal scroll at 320 px on four pages                                                                               |

Also still passing from Phase 3: editor round trip, freshness, settings, preview, forged preview, diagnostics.

## Other checks run

```text
pnpm lint / typecheck / test                     exit 0 (contracts 109, web 101, PHP unit 30)
pnpm --filter @websage/web build                 exit 0
composer check                                   PHPCS clean, PHPStan level 8 OK
composer test:integration (both CMB2 orders)     64/64 twice
wp websage seed --yes (second run, live site)    "0 created, 20 updated"
stored submissions after two e2e runs            12 (2 × (1 success + 5 rate-limit attempts)); the refused 6th never stored
```

## Defects found and fixed during this phase

- ESLint `react-hooks/set-state-in-effect` in both menus (state reset on navigation moved into derived state); caught after an early commit was amended.
- Test-side: Next.js 16 keeps the previous route's DOM mounted but hidden after client navigation; tests query visible roles instead of raw CSS.
- CSP would have blocked Turnstile when enabled; Cloudflare is now allowed only when a site key is configured.

## Known limitations

- Rate limiting uses a fixed 15-minute window, so a client can send up to 10 messages across a window boundary.
- The demo seed contains no images; image rendering is covered by component tests.
- WordPress's sample "Hello world!" post is left in place by design (the seeder never touches other content).
- Accessibility audit (axe), Lighthouse and the rebranding test are Phase 5.
- DDEV/MySQL unverified locally; CI will run MariaDB once the PR is open.
