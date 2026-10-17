import { expect, test } from '@playwright/test';
import { configured, skipReason } from './support/wp';

// Phase 5 security review: frontend controls observable over HTTP.
test.skip(!configured, skipReason);

const SECRET_NAMES = [
  'WSH_SERVER_API_SECRET',
  'WSH_PREVIEW_SECRET',
  'WSH_REVALIDATION_SECRET',
  'WSH_RATE_LIMIT_SECRET',
  'WSH_E2E_WP_PASSWORD',
] as const;

test('every response carries the security headers', async ({ request }) => {
  for (const path of ['/', '/blog', '/no-such-page', '/sitemap.xml']) {
    const response = await request.get(path);
    const headers = response.headers();
    const csp = headers['content-security-policy'] ?? '';
    expect(csp, path).toContain("frame-ancestors 'none'");
    expect(csp, path).toContain("object-src 'none'");
    expect(csp, path).toContain("base-uri 'self'");
    expect(csp, path).not.toContain('unsafe-eval');
    expect(headers['strict-transport-security'], path).toMatch(/max-age=\d+/);
    expect(headers['x-content-type-options'], path).toBe('nosniff');
    expect(headers['referrer-policy'], path).toBe(
      'strict-origin-when-cross-origin',
    );
    expect(headers['permissions-policy'], path).toContain('camera=()');
    expect(headers['x-powered-by'], path).toBeUndefined();
  }
});

test('no server secret reaches the browser', async ({ page, request }) => {
  const secrets = SECRET_NAMES.map((name) => process.env[name]).filter(
    (value): value is string => Boolean(value && value.length >= 16),
  );
  expect(secrets.length, 'secrets configured for the run').toBeGreaterThan(2);

  const scripts = new Set<string>();
  page.on('response', (response) => {
    if (response.request().resourceType() === 'script')
      scripts.add(response.url());
  });
  const bodies: string[] = [];
  for (const path of [
    '/',
    '/contact',
    '/blog',
    '/blog/our-autumn-availability',
  ]) {
    await page.goto(path);
    bodies.push(await page.content());
  }
  for (const url of scripts) bodies.push(await (await request.get(url)).text());

  for (const body of bodies) {
    for (const secret of secrets) expect(body.includes(secret)).toBe(false);
  }
});

test('the image optimizer refuses hosts other than the CMS', async ({
  request,
}) => {
  const response = await request.get(
    '/_next/image?url=https%3A%2F%2Fevil.example%2Fx.png&w=640&q=75',
  );
  expect(response.status()).toBe(400);
});

test('API routes refuse other methods and cross-site posts', async ({
  request,
}) => {
  expect((await request.get('/api/revalidate')).status()).toBe(405);
  expect((await request.get('/api/contact')).status()).toBe(405);
  const crossSite = await request.post('/api/contact', {
    headers: { origin: 'https://evil.example' },
    data: {},
  });
  expect(crossSite.status()).toBe(403);
  const exit = await request.post('/api/preview/exit', {
    headers: { origin: 'https://evil.example' },
    maxRedirects: 0,
  });
  expect(exit.status()).toBe(403);
});

test('preview entry never redirects off-site and is never cached', async ({
  request,
}) => {
  const response = await request.get(
    '/api/preview?token=forged&path=//evil.example/',
    { maxRedirects: 0 },
  );
  expect(response.status()).toBeGreaterThanOrEqual(400);
  expect(response.headers()['location'] ?? '').not.toContain('evil.example');
  expect(response.headers()['cache-control'] ?? '').toContain('no-store');
});

test('a malformed URL shows no internals', async ({ request }) => {
  const response = await request.get('/blog/%E0%A4%A');
  const body = await response.text();
  expect(body).not.toMatch(
    /at \w+ \(|node_modules|ApiError|wp-content\/plugins/,
  );
});
