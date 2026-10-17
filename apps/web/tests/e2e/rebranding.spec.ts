import { readFile } from 'node:fs/promises';
import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { configured, logIn, skipReason, wpUrl } from './support/wp';

/*
 * Rebranding test (docs/REBRANDING.md): logo, every brand colour and both
 * fonts are changed in WebSage Settings only, then every page is checked
 * for leftovers of the old brand. No component is edited.
 */
test.skip(!configured, skipReason);
test.describe.configure({ mode: 'serial' });

const DEFAULTS = {
  primary: '#174F46',
  on_primary: '#FFFFFF',
  background: '#FFFFFF',
  foreground: '#17201E',
  muted: '#F2F5F4',
  on_muted: '#35443F',
  border: '#62736D',
  focus: '#126BDA',
};

/** Every value differs from every default, so leftovers are detectable. */
const REBRAND = {
  primary: '#6B21A8',
  on_primary: '#FFF7ED',
  background: '#FFFBF5',
  foreground: '#1C1917',
  muted: '#F3E8FF',
  on_muted: '#3B0764',
  border: '#7C6A8A',
  focus: '#B45309',
};

const PAGES = [
  '/',
  '/about',
  '/services',
  '/contact',
  '/blog',
  '/blog/our-autumn-availability',
  '/no-such-page',
];

function rgb(hex: string) {
  const n = Number.parseInt(hex.slice(1), 16);
  return `rgb(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255})`;
}

async function uploadLogo(page: Page): Promise<{ url: string; id: number }> {
  const data = await readFile(
    new URL('./fixtures/rebrand-logo.png', import.meta.url),
  );
  let response;
  // On the SQLite harness, concurrent admin requests can overwrite a fresh
  // login session, invalidating the nonce ("Cookie check failed"). Log in
  // again and retry rather than fail on a harness race.
  for (let attempt = 1; attempt <= 3; attempt++) {
    const nonce = await (
      await page.request.get(
        `${wpUrl}/wp-admin/admin-ajax.php?action=rest-nonce`,
      )
    ).text();
    response = await page.request.post(`${wpUrl}/wp-json/wp/v2/media`, {
      headers: {
        'X-WP-Nonce': nonce,
        'Content-Type': 'image/png',
        'Content-Disposition': 'attachment; filename="rebrand-logo.png"',
      },
      data,
    });
    if (response.status() !== 403 || attempt === 3) break;
    await logIn(page);
  }
  if (!response) throw new Error('upload not attempted');
  expect(response.status(), await response.text()).toBe(201);
  const media = (await response.json()) as { source_url: string; id: number };
  return { url: media.source_url, id: media.id };
}

/** Saves branding through the real settings screen. */
async function saveBranding(
  page: Page,
  values: {
    tokens: Record<string, string>;
    heading: string;
    body: string;
    logo: { url: string; id: number } | null;
  },
) {
  await page.goto(`${wpUrl}/wp-admin/admin.php?page=wsh_settings`);
  await page.getByRole('tab', { name: 'Branding' }).click();
  await page.getByLabel('Heading font').selectOption(values.heading);
  await page.getByLabel('Body font').selectOption(values.body);
  // The colour pickers and the media modal are core WordPress widgets; set
  // the inputs they write to, exactly as they would.
  await page.evaluate(
    ({ tokens, logo }) => {
      const set = (name: string, value: string) => {
        const input = document.querySelector<HTMLInputElement>(
          `input[name="${name}"]`,
        );
        if (!input) throw new Error(`missing input ${name}`);
        input.value = value;
      };
      for (const [name, value] of Object.entries(tokens)) set(name, value);
      // What the media modal writes: the image URL and its attachment ID.
      set('logo', logo?.url ?? '');
      set('logo_id', logo ? String(logo.id) : '');
    },
    { tokens: values.tokens, logo: values.logo },
  );
  await page.getByRole('button', { name: 'Save settings' }).click();
  await expect(
    page.getByText('Settings updated', { exact: false }),
  ).toBeVisible();
  await expect(page.getByText(/rolled back|must be/i)).toHaveCount(0);
}

/** Colours in use on the page that belong to the old brand. */
async function leftovers(page: Page, old: string[]) {
  return page.evaluate((colours) => {
    const found = new Set<string>();
    for (const el of document.querySelectorAll<HTMLElement>('body *')) {
      if (el.closest('[hidden], script, style, noscript')) continue;
      const style = getComputedStyle(el);
      if (style.display === 'none' || style.visibility === 'hidden') continue;
      const props = ['color', 'backgroundColor', 'outlineColor'] as const;
      for (const prop of props) {
        if (colours.includes(style[prop])) {
          found.add(
            `${el.tagName.toLowerCase()}.${el.className} ${prop}=${style[prop]}`,
          );
        }
      }
      if (
        style.borderTopStyle !== 'none' &&
        parseFloat(style.borderTopWidth) > 0
      ) {
        if (colours.includes(style.borderTopColor)) {
          found.add(
            `${el.tagName.toLowerCase()}.${el.className} border=${style.borderTopColor}`,
          );
        }
      }
    }
    return [...found];
  }, old);
}

test('a brand set in WordPress restyles every page without code changes', async ({
  page,
  browser,
}) => {
  test.setTimeout(180_000);
  await logIn(page);
  const logo = await uploadLogo(page);
  await saveBranding(page, {
    tokens: REBRAND,
    heading: 'system',
    body: 'system',
    logo,
  });

  // A separate context: a visitor without the editor's cookies (axe needs one).
  const context = await browser.newContext();
  const visitor = await context.newPage();
  // Settings changes reach the site through the signed webhook.
  await expect(async () => {
    await visitor.goto('/');
    await expect(
      visitor.locator('header').getByRole('img', { name: /WebSage Demo/ }),
    ).toBeVisible({ timeout: 1_000 });
  }).toPass({ timeout: 20_000 });

  const oldColours = [...new Set(Object.values(DEFAULTS).map(rgb))];
  for (const path of PAGES) {
    await visitor.goto(path);
    await expect(visitor.getByRole('heading', { level: 1 })).toBeVisible();

    const theme = await visitor.evaluate(() => {
      const body = getComputedStyle(document.body);
      const h1 = getComputedStyle(document.querySelector('h1')!);
      return {
        background: getComputedStyle(document.documentElement).backgroundColor,
        text: body.color,
        bodyFont: body.fontFamily,
        headingFont: h1.fontFamily,
        logo: document.querySelector('header img')?.getAttribute('src') ?? '',
      };
    });
    expect(theme.background, path).toBe(rgb(REBRAND.background));
    expect(theme.text, path).toBe(rgb(REBRAND.foreground));
    expect(theme.bodyFont, path).toMatch(/system-ui/);
    expect(theme.headingFont, path).toMatch(/system-ui/);
    expect(theme.bodyFont, path).not.toMatch(/Montserrat/i);
    expect(theme.headingFont, path).not.toMatch(/Sora/i);
    expect(theme.logo, path).toContain('rebrand-logo');

    expect(await leftovers(visitor, oldColours), path).toEqual([]);
    const { violations } = await new AxeBuilder({ page: visitor })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
      .analyze();
    expect(
      violations.map((v) => `${v.id}: ${v.nodes.length}`),
      path,
    ).toEqual([]);
  }

  // Interactive states use the new brand too.
  await visitor.goto('/contact');
  await visitor.getByRole('button', { name: 'Send message' }).click();
  await expect(
    visitor.getByText(/There are problems with your message/),
  ).toBeVisible();
  expect(await leftovers(visitor, oldColours), 'contact errors').toEqual([]);
  await visitor.keyboard.press('Tab');
  const outline = await visitor.evaluate(
    () => getComputedStyle(document.activeElement!).outlineColor,
  );
  expect(outline).toBe(rgb(REBRAND.focus));

  await visitor.setViewportSize({ width: 375, height: 800 });
  await visitor.goto('/');
  await visitor.getByRole('button', { name: /Menu/ }).click();
  expect(await leftovers(visitor, oldColours), 'mobile menu').toEqual([]);
  await context.close();
});

test.afterAll(async ({ browser }) => {
  const page = await browser.newPage();
  await logIn(page);
  await saveBranding(page, {
    tokens: DEFAULTS,
    heading: 'sora',
    body: 'montserrat',
    logo: null,
  });
  await page.close();
});
