# Phase 3 (CMS Integration) verification report

Date: 2026-10-16. Branch `feat/cms-integration`. Environment: Linux, Node 24.22.0, pnpm 12.14.0, PHP 8.4.26 CLI, WordPress 7.1.3. Local WordPress runs on the SQLite harness (no Docker here); CI covers MariaDB once the branch is pushed.

## Exit criteria

| Criterion                                                                                | Status              | Evidence                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| ---------------------------------------------------------------------------------------- | ------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Editing any content in WordPress updates the frontend within seconds, without a redeploy | ✅ (SQLite harness) | Playwright: a page edited and updated in the block editor shows the new heading on the production frontend within the 10 s polling window. Manual check: new heading served about 0.1 s after `wp post update` returned (signed webhook delivered, HTTP 200). Settings saved on the WebSage Settings screen appear in the site header. Library (CTA) changes, menus, renames and trashing emit the correct tags (PHP integration tests). |
| Preview shows unpublished changes                                                        | ✅ (SQLite harness) | Playwright: an unsaved change previewed from the editor shows the draft heading with the preview banner and `Cache-Control: no-store`; a separate visitor sees the published heading; Exit preview returns to published content; a forged token is refused (401).                                                                                                                                                                        |
| Endpoint tests pass                                                                      | ✅                  | PHP integration 53/53 in both CMB2 load orders; contracts 62/62 (every endpoint fixture); web 73/73.                                                                                                                                                                                                                                                                                                                                     |
| ADR-011 follow-up: real 404 status                                                       | ✅                  | Unknown, draft and reserved paths return HTTP 404 through the TLS proxy; Playwright checks status, not-found UI and noindex.                                                                                                                                                                                                                                                                                                             |
| CI green on the branch                                                                   | ⏳ not yet          | Branch not pushed; this machine has no GitHub credentials.                                                                                                                                                                                                                                                                                                                                                                               |

## Commands run

```text
pnpm lint / typecheck / test / build                         all exit 0
composer check                                               PHPCS clean, PHPStan level 8 OK, PHPUnit unit 30/30
composer test:integration (SQLite, both CMB2 orders)         53/53 twice
pnpm test:integration (Playwright, Chromium, harness)        7/7 on two consecutive runs
live curl checks on the harness                              see below
```

Live checks against the running harness (WordPress at https://localhost:8443, website at https://localhost:3443):

- Webhook: last delivery `ok: true, status 200` after each publish or update.
- Front-end hardening: CMS pages 301 to the website; wp-admin and login unaffected; `/wp-json/wp/v2/users` and library routes 401 for anonymous visitors; `?author=1` 404; core sitemap 404; XML-RPC 403.
- CORS: no `Access-Control-Allow-Origin` on `websage/v1` for a foreign Origin; core `wp/v2` unchanged.

## Defects found and fixed during verification

| Found by   | Defect                                                                                                                                                     | Fix                                                                                                            |
| ---------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| Playwright | Admin preview link HTML-escaped by `wp_nonce_url()`; the editor sent no post ID or nonce, so Preview could never work                                      | Raw URL with `wp_create_nonce()`; PHP test asserts no `&amp;` and a valid nonce                                |
| Live curl  | Front-end redirect skipped when CMS and website share a host on different ports                                                                            | Compare scheme, host and port                                                                                  |
| Live curl  | XML-RPC `system.*` methods still answered when disabled                                                                                                    | Refuse XML-RPC requests with 403                                                                               |
| Harness    | proxy.ts rewrites returned 500 behind a TLS proxy when Next was bound to 127.0.0.1                                                                         | Same-origin rewrite; documented the HOSTNAME requirement (ADR-014)                                             |
| PHP tests  | Trashing recorded the `__trashed` slug instead of the real old path; remembered paths leaked between events; an empty slug produced an invalid `post:` tag | First path wins and is consumed once; slug-less content has no path; tags validated against the shared grammar |
| PHP tests  | Unknown future block versions were rejected instead of passed through as envelopes; deeply nested unsupported blocks escaped the depth limit               | Version-check known types only; depth guard on every block                                                     |
| PHP tests  | `<script>` contents survived kses as visible text                                                                                                          | Script and style elements removed with their contents before kses                                              |

## Known limitations

- Visual components exist only for hero and core-content; the other section types render nothing in production until Phase 4 (development shows a warning).
- Blog index is a placeholder; posts have a minimal view (needed for post preview).
- The not-found message is rendered client-side; crawlers get the 404 status and noindex.
- Preview pages use the static-compatible CSP; a nonce CSP for preview routes is still open (ADR-005).
- DDEV/MySQL is unverified on this machine; CI covers MariaDB on PHP 8.1 and 8.3 once pushed.
- The harness serves WordPress with `php -S` (four workers) and SQLite; it is test tooling, not a deployment target.
