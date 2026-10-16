# WebSage Headless Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans for native execution, or superpowers:subagent-driven-development if the owner selects delegation. Execute task-by-task; track checkbox steps below.

**Goal:** Deliver the Phase 2 monorepo with one tested hero block flowing from Gutenberg through WordPress REST and shared Zod validation to a Next.js Server Component.

**Architecture:** WordPress owns content and serializes a narrow public API. A contracts workspace owns runtime validation; a server-only frontend adapter validates upstream data before rendering. Foundation implements the hero slice and placeholder routes; the rest of the approved architecture remains scheduled for later phases.

**Tech Stack:** pnpm workspaces, Next.js App Router, React, strict TypeScript, Tailwind, Zod, PHP/Composer, bundled CMB2, @wordpress/scripts, DDEV, Vitest/RTL, Playwright, PHPUnit, PHPCS/WPCS, PHPStan, GitHub Actions.

**Spec:** [Approved architecture](../../ARCHITECTURE.md), [decisions](../../DECISIONS.md), and [master prompt](../../../websage-headless-master-prompt.md).

## Global Constraints

- Scope is Phase 2 only. Stop at the phase gate; no settings UI, full block catalogue, contact processing, webhook or draft preview implementation yet.
- PHP minimum 8.1; integration runtime PHP 8.3; WordPress minimum/baseline 7.1.3, subject to verification of availability.
- Documentary baseline pins: Next.js 16.4.2, React/react-dom 19.3.0, Node LTS 24.22.0, Tailwind 4.3.3. Verify registry availability and upstream security guidance before installation. Report contradictory source evidence before changing approved baselines.
- TypeScript `strict: true`, `noUncheckedIndexedAccess: true`; components use design-token variables.
- Plugin namespace `WebSage\HeadlessCore`, hooks/options `wsh_`, text domain `websage-headless`.
- Only WordPress plus WebSage Headless Core installed; CMB2 bundled using Composer. No paid dependencies, ACF, WPGraphQL or extra framework/service.
- Read files before modification; do not delete/overwrite pre-existing user files. Updates to the required decision/progress logs follow the approved phase workflow.
- No secrets in tracked files or privileged NEXT_PUBLIC variables. No unsanitized rich HTML rendering.
- Use Conventional Commits only in an isolated project repository; never stage the parent Pictures repository.
- No implementation success claim without fresh command output; CI green requires a real remote run.

## Review Focus

1. A configured CMS goes offline: show an error, never silently replace content with fixture data (Task 4).
2. Unknown future sections versus malformed known hero sections: skip only unknown types, reject invalid heroes (Task 2).
3. Older stored dynamic blocks are not reopened in the editor: migrate attributes during server parsing (Task 3).
4. Multiple plugins bundle CMB2: newest version wins in both load orders with correct source diagnostics (Task 3).
5. A fresh install has no published front page or a draft-only page: public endpoint must not disclose drafts or claim a successful configured site (Tasks 3 and 6).

## Task 1: Isolated, reproducible workspace

**Files:** Create root `package.json`, `pnpm-workspace.yaml`, `pnpm-lock.yaml`, `.node-version`, `.npmrc`, `.gitignore`, `tsconfig.base.json`, `eslint.config.mjs`, `.prettierrc.json`, `.env.example`; package manifests for `apps/web`, `packages/contracts`, `packages/wp-plugin`; `docs/FOUNDATION.md`.

**Interfaces:** Root commands `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build` orchestrate workspaces. Package names `@websage/web`, `@websage/contracts`, `@websage/wp-plugin`. Separate `pnpm test:integration` runs the actual WP/browser suite. No successful no-op test scripts.

- [ ] Inspect project `.git` and `git rev-parse --show-toplevel`. Use the using-git-worktrees skill to establish an isolated checkout. The project `.git` is currently a protected directory and Git resolves to `/home/duanef/Pictures`; do not remove/replace that directory or write parent Git metadata. If native isolation cannot create a dedicated checkout, request the specific necessary filesystem permission and document the blocker.
- [ ] Check official registry versions, peer requirements and security notes for approved baselines; record exact selected tooling versions in DECISIONS. Check tool availability and install task-local Node/pnpm/Composer when possible; request escalation for network or protected writes as required. Docker/DDEV installation is a system prerequisite, not permission to alter the host daemon.
- [ ] Create workspace configuration, lockfiles and shared lint/format/type settings. Exclude dependency/build/cache directories, secrets and DDEV local artifacts from Git. Environment example uses dummy values with descriptions.
- [ ] Verify `node --version`, `pnpm --version`, `pnpm install --frozen-lockfile`, `pnpm exec prettier --check` against authored config files. Record exact outputs; do not run builds under the currently installed Node 20 while claiming Node 24 verification.
- [ ] Commit only new project-owned files: `chore: initialize isolated pnpm workspace`.

## Task 2: Contracts for the hero slice

**Files:** Create `packages/contracts/src/primitives.ts`, `sections.ts`, `pages.ts`, `site.ts`, `errors.ts`, `index.ts`; `fixtures/page-hero.json`, `fixtures/site-default.json`; `tests/contracts.test.ts`; package-specific `tsconfig.json` and `vitest.config.ts`.

**Interfaces:** Export `HeroSectionSchema`, `PageResponseSchema`, `SiteResponseSchema`, `ApiErrorSchema`, `parseSection(input: unknown): HeroSection | UnknownSection`, plus inferred types. `UnknownSection` retains only type/schemaVersion/key; raw unknown attributes never render. Page success envelope is `{data:{path,title,modified,sections,seo}}`; site envelope exactly follows architecture. Only hero and unknown envelopes are supported in Phase 2; remaining known types are implemented in Phase 3.

- [ ] Write contract tests: valid architecture hero/site fixtures pass; hero without heading fails; theme outside default/muted/brand fails; unrecognized hero properties fail; javascript/protocol-relative/credential URLs fail; future type with a valid outer envelope is unknown; invalid hero never falls through to unknown. Check text/HTML limits, image dimensions and ≤100 sections.
- [ ] Run `pnpm --filter @websage/contracts test`; confirm failure because schemas are absent.
- [ ] Implement strict Zod schemas, safe URL primitives, output types and known-type-first parsing. Relative links must begin with a single slash; absolute links/media use HTTPS. Keep HTML validation structural; sanitization belongs to Task 4. Use the architecture's exact nullability and field names.
- [ ] Run contract tests and package typecheck; confirm passing. Shared JSON files are also consumed by PHP serializer assertions.
- [ ] Commit `feat: define validated hero and site contracts`.

## Task 3: Plugin bootstrap, bundled CMB2 and hero REST slice

**Files:** Create `packages/wp-plugin/websage-headless-core.php`, `composer.json`, `composer.lock`, `uninstall.php`, `src/Plugin.php`, `src/Support/Requirements.php`, `src/Blocks/HeroBlock.php`, `src/Blocks/HeroMigration.php`, `src/Content/PageSerializer.php`, `src/Rest/PagesController.php`, `src/Rest/SiteController.php`, `src/Rest/Errors.php`, `blocks/hero/{block.json,index.js,edit.js,deprecated.js}`; `tests/bootstrap.php`, `tests/integration/{ActivationTest,HeroEndpointTest,Cmb2CoexistenceTest}.php`; `phpunit.xml.dist`, `phpcs.xml.dist`, `phpstan.neon.dist`.

**Interfaces:** `Requirements::assertSupported(): void`; `Plugin::register(): void`; `HeroMigration::normalize(array $attributes): array`; `PageSerializer::serialize(WP_Post $page): array`; public `GET /websage/v1/pages/(?P<slug>.+)` and `GET /websage/v1/site`. Serializer returns exactly Task 2 fixtures, with dynamically resolved URL/timestamp. Site endpoint returns explicit plugin defaults in this phase, not fabricated CMS-configurable settings. Failures use approved error envelope and status.

- [ ] Write WordPress integration tests: activation succeeds on minimum WordPress/PHP and rejects unsupported versions through the requirements seam; no plugin other than Core required; missing vendor gets an actionable safe admin error. Published hero page matches shared contract fixture fields; anonymous draft/absent resource is 404; missing home is a configuration error; nested path resolves; internal author/email/meta absent.
- [ ] Add migration test for a documented legacy fixture `{schemaVersion:0,title:'Legacy'}` mapping to schemaVersion 1 heading, using a deterministic default for other attributes; test current attributes remain unchanged. Add tests for invalid section depth/count/body length returning a safe error.
- [ ] Add isolated-process CMB2 integration tests with two actual release copies: load older/newer then newer/older, invoke normal WordPress hooks, assert newest CMB2 version and bootstrap source. Pin both test fixtures; do not bundle the old test copy in production vendor.
- [ ] Run PHPUnit in the WordPress environment once Task 5 provisions it, recording initial missing implementation failures. Pure PHP migration/requirements tests can run earlier; never substitute stubs for activation evidence.
- [ ] Implement PSR-4 bootstrap and activation checks, CMB2 upstream loader integration, block registration from compiled metadata, explicit published-only serializers and REST permission callbacks. Public read routes may use public access callbacks; no write routes exist yet. Sanitize rich text server-side in PHP too; never run arbitrary block rendering/shortcodes.
- [ ] Implement hero editor fields with native controls, media selector, heading/body/links/theme. Save null, attributes in comments. Include editor deprecation definition matching the migration fixture. Build using @wordpress/scripts and externalized WordPress dependencies; ship generated asset dependency metadata.
- [ ] Implement uninstall of plugin-owned options only; do not delete user pages/content or submissions in this phase.
- [ ] Run block build, PHPUnit, PHPCS and PHPStan; then activate on a fresh site. Verify real REST response against Zod via Task 6. Commit `feat: add bundled core plugin and hero endpoint`.

## Task 4: Server-rendered frontend and safe API adapter

**Files:** Create `apps/web/next.config.ts`, `tsconfig.json`, `postcss.config.mjs`, `vitest.config.ts`; `app/{layout.tsx,page.tsx,loading.tsx,error.tsx,not-found.tsx,globals.css}`, `app/[...slug]/page.tsx`, `app/blog/{page.tsx,[slug]/page.tsx}`; `components/layout/{Header,Footer}.tsx`, `components/sections/Hero.tsx`; `lib/api/{client,fetchers,errors}.ts`, `lib/security/sanitize-html.ts`, `lib/sections/registry.tsx`; `config/{fonts,tokens}.ts`; tests `api-client.test.ts`, `sanitize-html.test.ts`, `hero.test.tsx`, `registry.test.tsx`.

**Interfaces:** `getSite(): Promise<Site>` and `getPage(path: string): Promise<Page>` are server-only; lower-level `fetchApi<T>(path, schema): Promise<T>` accepts no browser-selected origin. Sanitizer `sanitizeRichText(html: string, cmsOrigin: string): string`. Registry `renderSection(section): ReactNode` renders hero, warns for unknown in development and returns null in production. Placeholder blog routes explicitly describe unavailable Phase 4 content and never fake published posts.

- [ ] Write API tests for validated response, 404 versus 500, malformed JSON/known section, aborted five-second timeout, >1 MiB response, cross-origin redirect, invalid path, missing required origin. Assert configured CMS failure never yields fixture content. Assert public fetch tags `site` and `page:{path}` with fallback 300 seconds; validated data only enters the persistent cache.
- [ ] Write sanitizer tests stripping scripts/event handlers/style/iframe/javascript URLs, retaining allowed text markup, rewriting same-CMS content links, preserving allowed media. Hero RTL test asserts heading/link text, image alt and safe output. Registry tests assert future sections skipped in production but invalid known data fails validation earlier.
- [ ] Run affected Vitest tests and confirm failures from missing implementation.
- [ ] Implement adapter with fixed HTTPS CMS origin, redirect:error, bounded streamed body, AbortController timeout, error mapping and Zod validation before persistent caching. No preview fetcher is exposed before Phase 3 authorization exists. Preserve descriptive errors only in development; redact upstream details from rendered production errors.
- [ ] Implement server layout, hero, skip link, semantic Header/Footer, CSS tokens and Tailwind mappings. Use next/font Sora/Montserrat, next/image with configured restricted remotePatterns. Blog and future route placeholders do not claim unfinished functions are working. Home and nested page routes use getPage; verified 404 alone calls notFound. Keep CMS reads at request time so production build does not require CMS availability.
- [ ] Provide explicit development fixture mode using shared JSON, disabled in production. Production missing config fails with generic setup/unavailable UI; no automatic fallback. .env.example explains both modes.
- [ ] Configure standalone output and baseline production security headers including CSP frame-ancestors; defer nonce preview policy until preview routes exist. Run unit/component tests, lint, typecheck and production build. Commit `feat: render validated WordPress hero content`.

## Task 5: Linux WordPress environment and CI

**Files:** Create `.ddev/config.yaml`, `.ddev/docker-compose.websage.yaml`, `.ddev/commands/web/{websage-install,websage-test}`; `scripts/{install-wp-tests.sh,verify-hero.mjs}`; `.github/workflows/ci.yml`; `apps/web/playwright.config.ts`, `apps/web/tests/e2e/hero.spec.ts`; extend `docs/FOUNDATION.md`.

**Interfaces:** `ddev websage-install` installs pinned WordPress in DDEV docroot and activates only WebSage Headless Core. `ddev websage-test` provisions a separate test database and runs PHPUnit. `pnpm test:integration` validates actual REST plus browser rendering. Root build compiles block assets and contracts before frontend.

- [ ] Configure project type WordPress, PHP 8.3, Linux-compatible database and plugin mount. Keep runtime WordPress files outside authored plugin source and out of Git. Installation command is explicit, checks existing wp-config/database before writing, and does not reset existing content.
- [ ] Configure a separate integration database and real WordPress test bootstrap. Document Docker engine/DDEV installation, HTTPS certificate trust and web-to-CMS host resolution. Do not use disabled TLS verification as a permanent workaround.
- [ ] Add CI Node jobs for frozen install, lint/format, typecheck, unit/component tests, block and production frontend builds; PHP job for Composer lock install, PHPCS/PHPStan, PHP 8.1/8.3 compatibility and WordPress integration with a database service. Use least-privilege read-only contents permissions and maintained pinned action revisions.
- [ ] Add browser integration fixture setup: create a uniquely named published hero page with real Gutenberg comment attributes, set temporary front-page config on the disposable site, load Next.js and assert rendered heading/link. Save through the real editor in at least one Playwright scenario; validate persisted attributes, REST schema and resulting frontend. Use temporary test accounts only in the disposable environment.
- [ ] Run `ddev start`, `ddev websage-install`, `ddev websage-test`, `pnpm test:integration`. Verify fresh activation and real hero flow. If Docker/DDEV unavailable, record blocked evidence and do not mark activation/round trip passed.
- [ ] Commit `ci: verify frontend and WordPress foundation`.

## Task 6: Review, verification and phase handoff

**Files:** Update `docs/DECISIONS.md`, `docs/PROGRESS.md`, `docs/FOUNDATION.md`; add `docs/reports/phase-2-verification.md`. Any fixes require reading the affected files and rerunning relevant checks.

**Interfaces:** Phase report states completed scope, actual commands/results, failures/blockers and next-phase gate. A CI file is not proof of a green CI run.

- [ ] Inspect final diff against this plan/spec. Use requesting-code-review skill as applicable; delegation only when explicitly selected or required by applicable skill. Review dependency locks, privileged environment handling, generated assets and public data mapping.
- [ ] Run `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build`; run PHP lint/PHPCS/PHPStan/PHPUnit, fresh DDEV activation and real editor-to-frontend integration. Include unpublished/absent page tests and both CMB2 load orders. Fix verified failures with regression evidence; no broad unrelated features.
- [ ] Start standalone production build and fetch homepage plus a missing route to verify response behavior; inspect security headers and server logs. Confirm production fixture mode is rejected.
- [ ] If a configured GitHub repository/remote exists, push only when authorized and inspect the actual Actions run. Without a remote/run, report CI unverified; do not create/publish an external repository merely to claim CI green.
- [ ] Record dependency pins, test counts, exact command results, remaining limitations and required host setup. Do not label Phase 2 complete if any stated exit criterion is unmet. Update logs and commit `docs: record foundation verification and phase gate`.
- [ ] Stop and present the Phase 2 results for approval before Phase 3.

## Observed execution prerequisites

Read-only checks on 2026-10-16 found Node v20.19.2, npm 9.2.0, PHP CLI 8.4.26 and Git. pnpm, Composer, Docker and DDEV were not on PATH. Node/PHP host versions are not the approved test baseline. The project .git is a read-only protected directory while Git resolves to the broader Pictures repository. Resolve access through normal approval mechanisms; no workaround may bypass filesystem restrictions. None of these observations proves the product cannot run; they identify setup and verification work for Foundation.

## Plan review

Self-review covered all Phase 2 deliverables and kept Phase 3–6 functionality out of scope. Task 3 tests depend on Task 5's real WordPress environment; execute Task 5 provisioning before Task 3 integration checks, then return to complete Task 3. Unit tests need no WordPress substitute. No task requires a new framework/service or paid dependency.

Execution recommendation: native implementation in this session, because these tasks share tightly coupled contract/serializer/fetcher interfaces and the setup has environment-specific constraints. Subagent-driven execution remains an owner-selectable alternative with additional review contexts.
