#!/usr/bin/env node
/**
 * Lighthouse mobile audit of a running production build.
 *
 *   pnpm --filter @websage/web lighthouse [origin] [path ...]
 *
 * Defaults to WSH_E2E_WEB_URL (or http://127.0.0.1:3000) and the demo pages.
 * Uses Playwright's Chromium, so `playwright install chromium` is enough.
 * Each page is audited LIGHTHOUSE_RUNS times (default 3) and the run with the
 * median performance score is kept, as Lighthouse recommends for variance.
 * Reports go to apps/web/lighthouse-reports/. Exits non-zero when any
 * category scores below LIGHTHOUSE_MIN (default 90).
 */
import { mkdir, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { chromium } from '@playwright/test';
import * as chromeLauncher from 'chrome-launcher';
import lighthouse from 'lighthouse';

const [
  origin = process.env.WSH_E2E_WEB_URL ?? 'http://127.0.0.1:3000',
  ...rest
] = process.argv.slice(2);
const paths = rest.length
  ? rest
  : [
      '/',
      '/services',
      '/about',
      '/contact',
      '/blog',
      '/blog/our-autumn-availability',
    ];
const minimum = Number(process.env.LIGHTHOUSE_MIN ?? 90);
const runs = Math.max(1, Number(process.env.LIGHTHOUSE_RUNS ?? 3));
const categories = ['performance', 'accessibility', 'best-practices', 'seo'];
const outDir = join(import.meta.dirname, '..', 'lighthouse-reports');

await mkdir(outDir, { recursive: true });
const chrome = await chromeLauncher.launch({
  chromePath: chromium.executablePath(),
  chromeFlags: [
    '--headless=new',
    '--ignore-certificate-errors',
    '--no-sandbox',
  ],
});

const rows = [];
let benchmark = 0;
let failed = false;
try {
  for (const path of paths) {
    const url = new URL(path, origin).toString();
    // Warm the server cache so the audit measures a cached page, as visitors see it.
    await fetch(url).catch(() => {});
    const results = [];
    for (let run = 0; run < runs; run++) {
      const result = await lighthouse(url, {
        port: chrome.port,
        output: 'json',
        logLevel: 'error',
        onlyCategories: categories,
        formFactor: 'mobile',
        screenEmulation: {
          mobile: true,
          width: 412,
          height: 823,
          deviceScaleFactor: 1.75,
        },
        throttlingMethod: 'simulate',
      });
      if (!result) throw new Error(`No Lighthouse result for ${url}`);
      results.push(result);
    }
    results.sort(
      (a, b) =>
        (a.lhr.categories.performance?.score ?? 0) -
        (b.lhr.categories.performance?.score ?? 0),
    );
    const { lhr, report } = results[Math.floor(results.length / 2)];
    const name =
      path === '/'
        ? 'home'
        : path.replace(/^\/|\/$/g, '').replace(/[^\w-]+/g, '_');
    await writeFile(join(outDir, `${name}.json`), report);
    const scores = Object.fromEntries(
      categories.map((id) => [
        id,
        Math.round((lhr.categories[id]?.score ?? 0) * 100),
      ]),
    );
    if (Object.values(scores).some((score) => score < minimum)) failed = true;
    rows.push({ path, ...scores });
    benchmark = lhr.environment.benchmarkIndex;
    for (const [id, audit] of Object.entries(lhr.audits)) {
      if (
        audit.score !== null &&
        audit.score < 0.9 &&
        audit.scoreDisplayMode !== 'informative'
      ) {
        console.log(`  ${path} ${id}: ${audit.displayValue ?? audit.score}`);
      }
    }
  }
} finally {
  await chrome.kill();
}

console.log(`Median of ${runs} run(s); machine benchmarkIndex ${benchmark}`);
console.table(rows);
if (failed) {
  console.error(`At least one score is below ${minimum}.`);
  process.exit(1);
}
