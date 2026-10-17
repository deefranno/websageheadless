#!/usr/bin/env node
/**
 * Release build: writes the two archives buyers receive.
 *
 *   pnpm package                 # needs a clean git tree
 *   pnpm package --allow-dirty   # local trial with uncommitted changes
 *
 *   dist/websage-headless-core-<version>.zip     WordPress plugin, ready to upload
 *   dist/websage-headless-starter-<version>.zip  the kit's source workspace
 *
 * The starter is built from files tracked in git only, so ignored files
 * (env files, keys, harness data, build output) can never be included.
 * Both archives are audited after they are written; any forbidden file or
 * version mismatch fails the build.
 */
import { execFileSync } from 'node:child_process';
import {
  cpSync,
  existsSync,
  mkdirSync,
  readFileSync,
  rmSync,
  writeFileSync,
} from 'node:fs';
import { dirname, join } from 'node:path';

const root = join(import.meta.dirname, '..');
const dist = join(root, 'dist');
const stage = join(dist, '.stage');
const allowDirty = process.argv.includes('--allow-dirty');

const run = (cmd, args, opts = {}) =>
  execFileSync(cmd, args, {
    cwd: root,
    stdio: ['ignore', 'pipe', 'inherit'],
    encoding: 'utf8',
    ...opts,
  });

const version = run('node', ['scripts/version.mjs']).trim();

/* Buyer-facing docs; internal history (plans, reports, progress) stays out. */
const STARTER_EXCLUDE = [
  /^websage-headless-master-prompt\.md$/,
  /^docs\/superpowers\//,
  /^docs\/reports\//,
  /^docs\/PROGRESS\.md$/,
  /^docs\/FOUNDATION\.md$/,
];

/* Never allowed in either archive. */
const FORBIDDEN = [
  /(^|\/)\.env(\.[^/]*)?$(?<!\.example)/,
  /\.(pem|key|crt|p12)$/,
  /(^|\/)e2e\.env$/,
  /(^|\/)node_modules\//,
  /(^|\/)\.next\//,
  /(^|\/)\.wordpress\//,
  /(^|\/)\.tools\//,
  /(^|\/)(test-results|playwright-report|lighthouse-reports|coverage)\//,
  /(^|\/)\.phpunit\.cache\//,
  /websage-headless-master-prompt\.md$/,
  /docs\/(superpowers|reports)\//,
  /docs\/PROGRESS\.md$/,
];

/* Additionally forbidden in the plugin zip: development-only files. */
const PLUGIN_FORBIDDEN = [
  /^websage-headless-core\/tests\//,
  /^websage-headless-core\/(phpunit|phpcs|phpstan)[^/]*$/,
  /^websage-headless-core\/package\.json$/,
  /^websage-headless-core\/vendor\/(bin|phpunit|squizlabs|wp-coding-standards|phpstan|szepeviktor|wp-phpunit|yoast|php-stubs|dealerdirect|phpcsstandards|sirbrillig|automattic)\//,
];

function ensureCleanTree() {
  const status = run('git', ['status', '--porcelain']).trim();
  if (status && !allowDirty) {
    console.error(
      'Working tree has uncommitted changes; commit them or pass --allow-dirty.',
    );
    console.error(status);
    process.exit(1);
  }
}

function zip(dir, name, archive) {
  rmSync(archive, { force: true });
  run('zip', ['-qrX', archive, name], { cwd: dir });
}

function listZip(archive) {
  return run('unzip', ['-Z1', archive]).split('\n').filter(Boolean);
}

function audit(archive, extra = []) {
  const files = listZip(archive);
  const bad = files.filter((f) =>
    [...FORBIDDEN, ...extra].some((re) => re.test(f)),
  );
  if (bad.length) {
    console.error(
      `${archive} contains forbidden files:\n  ${bad.join('\n  ')}`,
    );
    process.exit(1);
  }
  return files;
}

function thirdPartyNotices(title, entries) {
  const lines = [
    `Third-party software in ${title} ${version}`,
    '',
    'Each package below keeps its own licence. Full licence texts are in the',
    "packages' own files (for npm packages, in node_modules/<package> after",
    'installation).',
    '',
  ];
  for (const { name, version: v, license, homepage } of entries.sort((a, b) =>
    a.name.localeCompare(b.name),
  )) {
    lines.push(`${name} ${v} — ${license}${homepage ? ` — ${homepage}` : ''}`);
  }
  return `${lines.join('\n')}\n`;
}

/* ---------- Plugin ---------- */

function buildPlugin() {
  run('pnpm', ['--filter', '@websage/wp-plugin', 'build'], {
    stdio: 'inherit',
  });
  const src = join(root, 'packages/wp-plugin');
  const out = join(stage, 'plugin', 'websage-headless-core');
  mkdirSync(out, { recursive: true });
  for (const entry of [
    'websage-headless-core.php',
    'uninstall.php',
    'readme.txt',
    'LICENSE',
    'src',
    'build',
    'blocks',
    'composer.json',
    'composer.lock',
  ]) {
    cpSync(join(src, entry), join(out, entry), { recursive: true });
  }
  run(
    'composer',
    [
      'install',
      '--no-dev',
      '--no-interaction',
      '--no-progress',
      '--optimize-autoloader',
      `--working-dir=${out}`,
    ],
    { stdio: 'inherit' },
  );

  const cmb2 = JSON.parse(
    readFileSync(join(out, 'vendor/composer/installed.json'), 'utf8'),
  );
  const packages = (cmb2.packages ?? cmb2).map((p) => ({
    name: p.name,
    version: p.version,
    license: (p.license ?? []).join(' OR '),
    homepage: p.homepage,
  }));
  writeFileSync(
    join(out, 'THIRD-PARTY-NOTICES.txt'),
    thirdPartyNotices('WebSage Headless Core', packages) +
      '\nBlock editor scripts use the @wordpress packages that WordPress itself\nprovides at runtime; none are bundled.\n',
  );

  const header = readFileSync(join(out, 'websage-headless-core.php'), 'utf8');
  if (
    !header.includes(`Version:           ${version}`) ||
    !header.includes(`'WSH_VERSION', '${version}'`)
  ) {
    throw new Error(
      'Plugin header version does not match the release version.',
    );
  }

  const archive = join(dist, `websage-headless-core-${version}.zip`);
  zip(join(stage, 'plugin'), 'websage-headless-core', archive);
  const files = audit(archive, PLUGIN_FORBIDDEN);
  for (const required of [
    'websage-headless-core/vendor/autoload.php',
    'websage-headless-core/vendor/cmb2/cmb2/init.php',
    'websage-headless-core/build/blocks-manifest.php',
    'websage-headless-core/LICENSE',
  ]) {
    if (!files.includes(required))
      throw new Error(`Plugin zip is missing ${required}`);
  }
  return { archive, count: files.length };
}

/* ---------- Starter ---------- */

function buildStarter() {
  const name = `websage-headless-starter-${version}`;
  const out = join(stage, 'starter', name);
  const tracked = run('git', ['ls-files', '-z']).split('\0').filter(Boolean);
  const files = tracked.filter(
    (f) =>
      !STARTER_EXCLUDE.some((re) => re.test(f)) && existsSync(join(root, f)),
  );
  for (const file of files) {
    mkdirSync(dirname(join(out, file)), { recursive: true });
    cpSync(join(root, file), join(out, file));
  }

  const npm = JSON.parse(
    run('pnpm', [
      'licenses',
      'list',
      '--prod',
      '--json',
      '--filter',
      '@websage/web',
    ]),
  );
  const entries = Object.values(npm)
    .flat()
    .flatMap((p) =>
      p.versions.map((v) => ({
        name: p.name,
        version: v,
        license: p.license,
        homepage: p.homepage,
      })),
    );
  writeFileSync(
    join(out, 'THIRD-PARTY-NOTICES.txt'),
    thirdPartyNotices(
      'the WebSage Headless frontend (production dependencies)',
      entries,
    ) +
      '\nThe WordPress plugin bundles CMB2 (GPL-2.0-or-later); see the plugin zip.\nDevelopment tools in pnpm-lock.yaml are not redistributed; pnpm installs them.\n',
  );

  const archive = join(dist, `${name}.zip`);
  zip(join(stage, 'starter'), name, archive);
  const listed = audit(archive);
  for (const required of [
    'README.md',
    'LICENSE',
    'docs/INSTALL.md',
    '.env.example',
    'apps/web/LICENSE',
    'packages/contracts/LICENSE',
    'packages/wp-plugin/LICENSE',
    'pnpm-lock.yaml',
  ]) {
    if (!listed.includes(`${name}/${required}`))
      throw new Error(`Starter is missing ${required}`);
  }
  return { archive, count: listed.length };
}

ensureCleanTree();
rmSync(stage, { recursive: true, force: true });
mkdirSync(stage, { recursive: true });
const plugin = buildPlugin();
const starter = buildStarter();
rmSync(stage, { recursive: true, force: true });

console.log(`\nRelease ${version}`);
console.log(
  `  ${plugin.archive.replace(`${root}/`, '')}  (${plugin.count} entries)`,
);
console.log(
  `  ${starter.archive.replace(`${root}/`, '')}  (${starter.count} entries)`,
);
