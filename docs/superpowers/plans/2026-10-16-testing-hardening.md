# WebSage Headless Phase 5 (Testing and Hardening) Plan

**Goal:** Evidence that the kit is accessible, fast, secure and rebrandable, with verified issues fixed and remaining limitations written down.

**Spec:** [Architecture](../../ARCHITECTURE.md) (threat list, design system), [decisions](../../DECISIONS.md), [master prompt](../../../websage-headless-master-prompt.md). Branch `feat/testing-hardening`.

**Exit criteria (master prompt):** a written report of what was tested, results, and known limitations.

## Task 1: Baseline

Root lint, typecheck, test and build; `composer check` (PHPCS, PHPStan level 8); PHP integration in both CMB2 load orders; Playwright on the seeded harness. Dependency audit: `pnpm audit` and `composer audit`. Record anything already failing before changing code.

## Task 2: Accessibility (axe in Playwright)

`@axe-core/playwright` with WCAG 2.0/2.1/2.2 A and AA rules on every demo page and state: home, about (FAQ open), services, contact (empty, with errors, sent), blog index, category, post, 404, desktop submenu open, mobile menu open, at desktop and 375px widths. Zero violations, with no rules disabled unless recorded with a reason. Also check focus visibility and `prefers-reduced-motion`.

## Task 3: Lighthouse

Script that runs Lighthouse (mobile preset, Playwright's Chromium) against the production build on the harness for home, services, blog, a post and contact, saving JSON and printing the four category scores. Target ≥ 90 each. Fix verified issues; report actual scores and the measuring conditions (local machine, simulated throttling).

## Task 4: Security review

Walk every row of the threat list: map it to the test that verifies it, add the missing tests, fix anything confirmed. Includes response headers (CSP, HSTS, nosniff, Referrer-Policy, Permissions-Policy, frame-ancestors), anonymous REST enumeration of drafts/submissions/users, oversized and malformed bodies, mail header injection, redirect exclusions, secrets absent from the client bundle, and log redaction.

## Task 5: Rebranding test

Change logo, colours and fonts in WebSage Settings and verify, in Playwright, that header, footer, every section, the form and error states restyle with zero component edits. Write the checklist (`docs/REBRANDING.md`) a buyer follows, including the optional token file.

## Task 6: Report and gate

`docs/reports/phase-5-verification.md` (what was tested, commands, results, limitations), DECISIONS and PROGRESS updated, CI green on the PR, stop at the gate.
