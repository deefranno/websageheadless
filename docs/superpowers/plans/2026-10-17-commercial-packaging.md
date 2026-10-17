# WebSage Headless Phase 6 (Commercial Packaging) Plan

**Goal:** A buyer receives two archives and documentation that take them from zero to a running, rebranded site, with correct licences and a repeatable release build.

**Spec:** [master prompt](../../../websage-headless-master-prompt.md) Phase 6, ADR-006 (licensing), ADR-021 (Lighthouse decision). Branch `feat/commercial-packaging`.

**Owner decisions (2026-10-17):** version `1.0.0-rc.1`; copyright "WebSage Creative"; security reports to the owner's email; frontend commercial licence drafted and marked for legal review.

**Exit criteria:** a fresh install following only `docs/INSTALL.md` succeeds on a clean machine.

## Task 1: Versions, changelog, licences

`1.0.0-rc.1` in every package, the plugin header, `WSH_VERSION` and a WordPress `readme.txt`; one script to bump them together. `CHANGELOG.md` (Keep a Changelog, SemVer). LICENSE files: plugin GPL-2.0-or-later (full text), contracts MIT, frontend commercial draft, root overview. `THIRD-PARTY-NOTICES` generated from production dependencies of each archive.

## Task 2: Release build

`pnpm package` builds and writes `dist/websage-headless-core-<version>.zip` (plugin: production vendor, compiled blocks, no tests or tooling) and `dist/websage-headless-starter-<version>.zip` (frontend + contracts + workspace config + buyer docs; no secrets, harness, internal plans or reports). An audit step fails the build if either archive contains forbidden files (env files, keys, test configs, `node_modules`, `.next`, internal docs) or a version mismatch. Run in CI.

## Task 3: Documentation

`INSTALL.md`, `DEVELOPER.md` (architecture, new-section recipe, CMB2 fields, extending the API, theming), `CLIENT-GUIDE.md` (plain language), `DEPLOYMENT.md` (Vercel and self-hosted standalone, cPanel/LiteSpeed notes, static export unsupported, ADR-014), `SECURITY.md`, README.

## Task 4: Clean-install verification

From the two archives only, in a new directory with a fresh WordPress: follow `INSTALL.md` literally, record every command and result, fix the docs where they were wrong or unclear. Docker/DDEV path and a deployed PageSpeed Insights run need a Docker host and a deployment; report them as owner steps if they cannot be run here.

## Task 5: Report and gate

`docs/reports/phase-6-verification.md`, DECISIONS and PROGRESS, CI green, stop.
