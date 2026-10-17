# Phase 6 (Commercial Packaging) verification report

Date: 2026-10-17. Branch `feat/commercial-packaging`. Release `1.0.0-rc.1`. Environment: Linux, Node 24.22.0, PHP 8.4.26 CLI, WordPress 7.1.3 (SQLite), no Docker.

## Exit criterion: a fresh install following only INSTALL.md

**Result: passed** for Path B (an existing WordPress), from the two release archives built at commit `3985957`. Path A (DDEV) could not be run here (no Docker); see limitations.

Setup that stands in for "a clean machine": a new empty directory; a pristine WordPress 7.1.3 with no WebSage plugin, plain permalinks and its own self-signed HTTPS certificate on port 9443; a shell whose `PATH` contains only Node.js 24.22, WP-CLI and system tools (no project pnpm); empty npm and pnpm caches and stores. Everything after that followed `docs/INSTALL.md` step by step. Browser steps used the real wp-admin screens through Playwright.

| INSTALL.md step                                                                             | Result                                                                                                                                                                                                |
| ------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1. Prerequisites                                                                            | Node v24.22.0, OpenSSL 3.5.7, pnpm absent                                                                                                                                                             |
| 2. `npm install --global pnpm@12.14.0`, unzip, `pnpm install`                               | pnpm 12.14.0; 1,488 packages from an empty store in 1 min 23 s                                                                                                                                        |
| 3. Generate four secrets                                                                    | 4 × 64 hex characters                                                                                                                                                                                 |
| 4.1 Plugins → Add Plugin → Upload Plugin → Activate                                         | Installed and activated from the zip, no error notices; menus Testimonials, Calls to action, Form messages, WebSage Settings                                                                          |
| 4.2 `wp config set` × 4                                                                     | Secrets and `WSH_FRONTEND_ORIGIN=http://localhost:3000`                                                                                                                                               |
| 4.3 `wp websage seed --yes`                                                                 | "Demo content ready: 20 created, 0 updated."                                                                                                                                                          |
| 4.4 Settings → Permalinks → Post name                                                       | Saved                                                                                                                                                                                                 |
| 5. `.env.local` from `.env.example`; `NODE_EXTRA_CA_CERTS=… pnpm --filter @websage/web dev` | Home, Services, About, Blog, a post and Contact all 200 with the expected headings; 0 errors in the server log                                                                                        |
| 6. Diagnostics                                                                              | All rows ✅ except "Last webhook delivery" (a failure recorded while seeding, before the website ran, as INSTALL.md now explains); **Send a test refresh**: "the website accepted the signed webhook" |
| 6. Edit a page                                                                              | New title on the website 0.6 s after **Update**                                                                                                                                                       |
| 6. Preview                                                                                  | wp-admin **Preview in new tab** opened the website in preview mode with the banner and the draft heading; a visitor still saw the published page                                                      |
| 6. Contact form                                                                             | Confirmation shown; message stored privately in WordPress                                                                                                                                             |
| 7. `pnpm build`, `pnpm --filter @websage/web start`                                         | Build OK; pages 200, unknown page and unknown post **404**, robots.txt and sitemap.xml 200                                                                                                            |

Transcript (secrets redacted): kept in the session scratchpad; key output above.

## What the clean install found (all fixed)

| Finding                                                                                                                                                | Fix                                                                                         |
| ------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------- |
| `corepack enable` failed: the only Corepack on the machine (0.24) cannot run pnpm 12                                                                   | INSTALL uses `npm install --global pnpm@12.14.0`                                            |
| `NODE_EXTRA_CA_CERTS` in `.env.local` has no effect: Node reads it before Next.js loads the file                                                       | Documented as a shell prefix (INSTALL, `.env.example`)                                      |
| Local website address had to be `https://`, so wp-admin Preview pointed at an address a local dev server cannot serve, and webhooks could not reach it | Plugin accepts `http://` for loopback hosts only (ADR-024), with tests for look-alike hosts |
| After that change the contract rejected every page (canonical URL had to be HTTPS)                                                                     | `CanonicalUrlSchema` with the same loopback rule, tested                                    |
| Preview tab title showed the published title (website metadata and plugin SEO both used the published post)                                            | Both use the draft; PHP and browser tests added                                             |
| `next start` warns it does not support `output: standalone`                                                                                            | `start` script runs the standalone server with `.env.local`                                 |
| WordPress 7.1 labels the button "Add Plugin", not "Add New"; the demo menu nests Services under About; an early webhook failure shows in Diagnostics   | INSTALL and readme.txt corrected                                                            |

## Release build

`pnpm package` (also in CI):

- `dist/websage-headless-core-1.0.0-rc.1.zip` (1.09 MB, 523 entries): plugin PHP, compiled blocks and their source, production `vendor/` (CMB2 2.13.4 only), `readme.txt`, GPL licence, third-party notices. No tests, tooling configuration or development dependencies.
- `dist/websage-headless-starter-1.0.0-rc.1.zip` (0.53 MB, 337 entries): the workspace from tracked files only, buyer docs, licences, `.env.example`, third-party notices for the frontend's 29 production packages. Excludes the master prompt, phase plans, reports, progress log and `FOUNDATION.md`.
- The audit fails the build on env files, keys or certificates, harness data, `node_modules`, `.next`, test results, internal documents, development files in the plugin, or a version mismatch. It caught CMB2's `.env.local.example` on the first run (allowed after review: a template).
- The zip installs through wp-admin's uploader and upgrades in place ("Replace current with uploaded" tested).

## Other checks

```text
pnpm lint / typecheck / test               exit 0 (contracts 110, web 101, PHP unit 30)
composer check                             PHPCS clean, PHPStan level 8 OK
composer test:integration (both orders)    73/73 twice
pnpm test:integration (harness)            53/53 on two consecutive runs
pnpm version:check                         1.0.0-rc.1 in all 8 places
```

## Documentation delivered

`README.md`, `docs/INSTALL.md`, `docs/DEVELOPER.md` (architecture, new-section recipe, CMB2 settings, API extension, theming, releasing), `docs/CLIENT-GUIDE.md`, `docs/DEPLOYMENT.md` (Vercel, self-hosted standalone with systemd and nginx examples, cPanel/LiteSpeed), `docs/SECURITY.md` (model, checklist, disclosure to the owner's email), `docs/REBRANDING.md`, `CHANGELOG.md`, licence files (plugin GPL-2.0-or-later with full text, contracts MIT, frontend commercial draft, root overview).

## Known limitations and owner actions

- **DDEV path not run** (no Docker here). Run `INSTALL.md` Path A once on a Docker host before tagging 1.0.0.
- **Vercel and cPanel/LiteSpeed deployment not run**; the docs follow the platforms' documented behaviour and are marked accordingly.
- **PageSpeed Insights on a deployed demo** (ADR-021) needs a deployment.
- **Commercial licence** for the frontend is a draft marked for legal review; the security policy promises acknowledgement in 3 working days and assessment in 10 — confirm these are commitments you want to make.
- Dev-mode only: an unknown URL shows the not-found page with `noindex` but HTTP 200 under `next dev`; production returns 404 (verified).
- Multiple self-hosted instances need a shared cache handler (documented).
