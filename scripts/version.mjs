#!/usr/bin/env node
/**
 * Sets or checks the release version everywhere it appears.
 *
 *   node scripts/version.mjs 1.2.0   # set
 *   node scripts/version.mjs         # check all files agree (exit 1 if not)
 *
 * Block versions (blocks/*\/block.json) are content schema versions and are
 * deliberately not touched.
 */
import { readFile, writeFile } from 'node:fs/promises';
import { join } from 'node:path';

const root = join(import.meta.dirname, '..');
const SEMVER = /^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/;

/** [file, pattern with one capture group around the version]. */
const TARGETS = [
  ['package.json', /("version":\s*")([^"]+)(")/],
  ['apps/web/package.json', /("version":\s*")([^"]+)(")/],
  ['packages/contracts/package.json', /("version":\s*")([^"]+)(")/],
  ['packages/wp-plugin/package.json', /("version":\s*")([^"]+)(")/],
  ['packages/wp-plugin/websage-headless-core.php', /(\* Version:\s+)(\S+)()/],
  [
    'packages/wp-plugin/websage-headless-core.php',
    /(define\( 'WSH_VERSION', ')([^']+)(')/,
  ],
  [
    'packages/wp-plugin/tests/phpstan-constants.php',
    /(define\( 'WSH_VERSION', ')([^']+)(')/,
  ],
  ['packages/wp-plugin/readme.txt', /(Stable tag:\s+)(\S+)()/],
];

const next = process.argv[2];
if (next !== undefined && !SEMVER.test(next)) {
  console.error(`Not a semantic version: ${next}`);
  process.exit(2);
}

const found = new Map();
for (const [file, pattern] of TARGETS) {
  const path = join(root, file);
  const text = await readFile(path, 'utf8');
  const match = pattern.exec(text);
  if (!match) throw new Error(`No version found in ${file}`);
  found.set(`${file} ${pattern.source.slice(0, 24)}`, match[2]);
  if (next) await writeFile(path, text.replace(pattern, `$1${next}$3`));
}

if (next) {
  console.log(`Version set to ${next} in ${TARGETS.length} places.`);
} else {
  const versions = new Set(found.values());
  if (versions.size !== 1) {
    for (const [where, version] of found) console.error(`${version}\t${where}`);
    console.error('Versions disagree.');
    process.exit(1);
  }
  console.log([...versions][0]);
}
