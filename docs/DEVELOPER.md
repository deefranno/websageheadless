# Developer guide

How the kit is built and how to extend it. Install first: [INSTALL.md](INSTALL.md). Deep reference: [ARCHITECTURE.md](ARCHITECTURE.md) (data model, API contract, threat model) and [DECISIONS.md](DECISIONS.md) (why things are the way they are).

## Architecture in one page

```text
Editor (wp-admin) ──► WordPress + WebSage Headless Core ──signed webhook──► Next.js /api/revalidate
                          │  /wp-json/websage/v1 (read-only JSON)                 │ revalidateTag
                          ▼                                                       ▼
                     Next.js server (fetch, Zod-validate, cache) ──HTML──► Visitor's browser
```

- **WordPress** owns content. Page sections are native Gutenberg blocks (`websage/*`); settings and structured meta use CMB2 (bundled); navigation uses WordPress menus.
- **The plugin** turns blocks into typed JSON at `/wp-json/websage/v1` (`/site`, `/pages/{path}`, `/posts`, `/posts/{slug}`, `/routes`), plus server-authenticated `/preview/{id}` and `/contact`.
- **`@websage/contracts`** holds the Zod schemas and JSON fixtures for every response. PHP and TypeScript tests both check the same fixtures, so the two sides cannot drift.
- **The website** (`apps/web`) fetches only on the server, validates every response, caches with tags (`site`, `posts`, `routes`, `page:/path`, `post:slug`; 5-minute fallback) and renders with React Server Components. Browsers never talk to WordPress.
- **Freshness:** on publish, update, trash, menu or settings change, the plugin sends an HMAC-signed webhook; the website expires exactly the affected tags.

## Repository layout

```text
apps/web/                  Next.js website
  app/                     routes (App Router), API handlers under app/api
  components/sections/     one component per section type
  lib/api/                 the only code that calls WordPress (server-only)
  lib/sections/registry.tsx  section type → component
  config/                  fonts and default tokens
packages/contracts/        Zod schemas (src/) and shared fixtures (fixtures/)
packages/wp-plugin/        WebSage Headless Core
  blocks/                  block editor source (block.json + index.js per block)
  src/                     PHP (namespace WebSage\HeadlessCore)
  tests/                   PHPUnit unit and integration suites
scripts/                   release build, versioning, local HTTPS harness
.ddev/                     DDEV configuration and commands
```

## Everyday commands (repository root)

```bash
pnpm dev:web            # alias for: pnpm --filter @websage/web dev
pnpm lint               # ESLint, Prettier, PHPCS (WordPress Coding Standards)
pnpm typecheck          # TypeScript and PHPStan level 8
pnpm test               # Vitest (contracts, web) and PHPUnit unit tests
pnpm build              # contracts, blocks, Next.js production build
pnpm test:integration   # Playwright against a running site (disposable sites only)
pnpm package            # release archives in dist/ (see Releasing)
```

PHP tooling needs Composer: `composer install --working-dir=packages/wp-plugin`. WordPress integration tests: `ddev websage-test`, or with the Docker-less harness: `scripts/wp-sqlite-test-env.sh` then `WP_CORE_DIR="$PWD/.wordpress/sqlite-core" composer --working-dir=packages/wp-plugin test:integration`.

Without WordPress at all, `WSH_FIXTURE_MODE=true pnpm --filter @websage/web dev` renders the shared fixtures (refused in production).

## Recipe: add a new section type

Example: a "Stats" section with a heading and up to six number/label pairs. Seven small changes; nothing else needs to know about it.

**1. Block** — `packages/wp-plugin/blocks/stats/block.json`:

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "websage/stats",
  "version": "1.0.0",
  "title": "Stats",
  "category": "design",
  "icon": "chart-bar",
  "textdomain": "websage-headless",
  "attributes": {
    "schemaVersion": { "type": "integer" },
    "heading": { "type": "string", "default": "" },
    "items": { "type": "array", "default": [] },
    "theme": {
      "type": "string",
      "enum": ["default", "muted", "brand"],
      "default": "default"
    }
  },
  "supports": { "html": false, "reusable": false },
  "editorScript": "file:./index.js"
}
```

`blocks/stats/index.js`: an `Edit` component using the helpers in `blocks/shared/controls.js` (`PlainField`, `ThemePanel`), registered with `registerSection(metadata, Edit)` from `blocks/shared/register.js`. Blocks are dynamic: they save attributes only. Then `pnpm --filter @websage/wp-plugin build`.

**2. Allow it in the editor** — add `'websage/stats'` to `SECTION_BLOCKS` in `packages/wp-plugin/src/Blocks/Registry.php`.

**3. Serialize it** — in `packages/wp-plugin/src/Content/Sections.php`, add `'stats'` to the `$known` list and a match arm `'stats' => self::stats( $attributes, $key )`, then a method that returns the contract shape. Always go through the `Values` helpers (`Values::text`, `Values::rich_text`, `Values::link`, `Values::image`): they sanitize and cap lengths.

```php
private static function stats( array $attributes, string $key ): array {
	$items = array();
	foreach ( array_slice( (array) ( $attributes['items'] ?? array() ), 0, 6 ) as $item ) {
		$items[] = array(
			'value' => Values::text( $item['value'] ?? '', 'stats.value' ),
			'label' => Values::text( $item['label'] ?? '', 'stats.label' ),
		);
	}
	return array(
		'type'          => 'stats',
		'schemaVersion' => 1,
		'key'           => $key,
		'heading'       => Values::text( $attributes['heading'] ?? '', 'stats.heading' ),
		'items'         => $items,
		'theme'         => self::theme( $attributes ),
	);
}
```

**4. Contract** — in `packages/contracts/src/sections.ts`, add `StatsSectionSchema` (`z.strictObject` with `type: z.literal('stats')`, the shared `envelope`, fields with limits) and add it to `KnownSectionSchema`. Export its type. Add an example to `fixtures/page-sections.json`; the contract tests require every known type to appear there.

**5. PHP test** — add the block to the content in `SectionsTest::test_every_section_type_matches_the_shared_fixture` so the PHP output is compared with the same fixture.

**6. Component** — `apps/web/components/sections/Stats.tsx`, a Server Component using `Section` and `SectionHeading` from `./shared` (they handle the theme, the region label and the single `h1`). Use token classes only (`bg-primary`, `text-on-muted`, …), never literal colours. If it needs browser JavaScript, put the interactive part in a `'use client'` file behind a `next/dynamic` loader (see `ContactFormLoader.tsx`).

**7. Register it** — one line in `apps/web/lib/sections/registry.tsx`: `stats: Stats`.

Run `pnpm test`, `pnpm typecheck` and the integration suite. Older deployed frontends ignore unknown section types (they render nothing in production and a warning in development), so the plugin can be updated first.

## Add a CMB2 setting

1. **Field:** in `packages/wp-plugin/src/Fields/SettingsPage.php`, add it to the right tab method using the helpers (`self::text`, `self::checkbox`, `self::image`, or `self::field` with `sanitization_cb` and `escape_cb` from `Sanitize`). Validators reject bad input and keep the old value; they never silently convert.
2. **Default:** add the key to `Settings::defaults()` in `src/Settings/Settings.php`. Renaming or restructuring keys needs a migration step and a `SCHEMA_VERSION` bump.
3. **Expose:** add it to the `/site` response in `src/Rest/SiteController.php`, then to `SiteSchema` in `packages/contracts/src/site.ts` and `fixtures/site-default.json`.
4. **Use:** read it from `getSite()` in the website. Saving settings already sends a `site` refresh webhook.

Post meta (like SEO fields) follows the same pattern in a metabox class (see `SeoMetabox.php`, `TestimonialMetabox.php`).

## Extend the API

1. Register the route in `packages/wp-plugin/src/Rest/Routes.php` with a real `permission_callback` (public reads may use the controller's public check; anything that writes or reveals drafts must verify the server secret, as `ContactController` and `PreviewController` do).
2. Return contract-shaped data through the existing envelope; throw `ContentException` for errors (they become the shared error envelope).
3. Add the response schema and a fixture to `packages/contracts`, and an integration test that compares output with the fixture.
4. Add a typed fetcher in `apps/web/lib/api/fetchers.ts` with `'use cache'`, `cacheTag(...)` and `cacheLife`. If WordPress changes should refresh it, emit the tag from `src/Revalidation/Events.php`.

## Theming

Brand colours and fonts come from WordPress at request time and become CSS variables (`components/layout/SiteTheme.tsx`). Tailwind's theme maps to those variables in `apps/web/app/globals.css`, so components use names like `bg-primary` and `text-foreground`. Spacing, radius, shadow and type scale are in the `@theme` block of `globals.css`. Full checklist, including adding a font family: [REBRANDING.md](REBRANDING.md).

Only these text/background pairs are contrast-checked when settings are saved: text/background, text-on-primary/primary, text-on-muted/muted. Keep new components within those pairs.

## Security rules for contributors

- Everything that talks to WordPress lives in `apps/web/lib/api` and is server-only. Never use a `NEXT_PUBLIC_` prefix for secrets.
- Rich text is sanitized server-side (`RichText` component); never use `dangerouslySetInnerHTML` with CMS data elsewhere.
- PHP: capability checks and nonces on admin actions, `permission_callback` on every route, sanitize on input, escape on output, `$wpdb->prepare()` for custom queries.
- See [SECURITY.md](SECURITY.md) for the full model.

## HTTPS front for local testing

To exercise webhooks, preview and the full browser suite locally without DDEV, the kit includes a disposable harness: WordPress on SQLite behind HTTPS proxies.

```bash
# Needs PHP 8.1+ with pdo_sqlite, and WP-CLI at .tools/wp-cli/wp-cli.phar (see .github/workflows/ci.yml)
scripts/wp-sqlite-site.sh start          # https://localhost:8443 (WordPress), https://localhost:3443 -> :3000 (website)
set -a; . .wordpress/sqlite-site/e2e.env; set +a
pnpm build && cp -r apps/web/.next/static apps/web/.next/standalone/apps/web/.next/
PORT=3000 node apps/web/.next/standalone/apps/web/server.js &
pnpm test:integration
scripts/wp-sqlite-site.sh stop
```

The CI workflow runs exactly this. The suite publishes content through the real editor: use disposable sites only.

## Releasing

1. `pnpm version:set 1.2.0` updates every version string (packages, plugin header, `readme.txt`); `pnpm version:check` verifies they agree.
2. Update `CHANGELOG.md`.
3. Commit, then `pnpm package`. It builds and writes `dist/websage-headless-core-<version>.zip` and `dist/websage-headless-starter-<version>.zip`, and fails if either archive contains secrets, harness data, internal documents or development-only files.
4. Lighthouse spot check of a running production build: `pnpm --filter @websage/web lighthouse <origin>`.
