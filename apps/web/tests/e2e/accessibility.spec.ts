import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { configured, skipReason } from './support/wp';

// WCAG 2.2 A/AA audit of every page and interactive state of the seeded demo.
test.skip(!configured, skipReason);

const WCAG = [
  'wcag2a',
  'wcag2aa',
  'wcag21a',
  'wcag21aa',
  'wcag22a',
  'wcag22aa',
];

async function expectAccessible(page: Page, state: string) {
  const { violations } = await new AxeBuilder({ page })
    .withTags(WCAG)
    .analyze();
  const report = violations.map(
    (v) =>
      `${v.id} (${v.impact}): ${v.help}\n` +
      v.nodes.map((n) => `    ${n.target.join(' ')}`).join('\n'),
  );
  expect(report, `${state}\n${report.join('\n')}`).toEqual([]);
}

const PAGES = [
  ['home', '/'],
  ['about', '/about'],
  ['services', '/services'],
  ['contact', '/contact'],
  ['blog index', '/blog'],
  ['blog category', '/blog?category=guides'],
  ['blog page 2', '/blog?page=2'],
  ['blog post', '/blog/our-autumn-availability'],
  ['not found', '/no-such-page'],
] as const;

for (const width of [1280, 375]) {
  test.describe(`at ${width}px`, () => {
    test.use({ viewport: { width, height: 900 } });

    for (const [name, path] of PAGES) {
      test(`${name} has no WCAG violations`, async ({ page }) => {
        await page.goto(path);
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        await expectAccessible(page, `${name} at ${width}px`);
      });
    }
  });
}

test.describe('interactive states', () => {
  test('open submenu', async ({ page }) => {
    await page.goto('/');
    const about = page
      .getByRole('navigation', { name: 'Main' })
      .getByRole('button', { name: /About/ });
    await about.click();
    await expect(about).toHaveAttribute('aria-expanded', 'true');
    await expectAccessible(page, 'submenu open');
  });

  test('open mobile menu', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 800 });
    await page.goto('/');
    const toggle = page.getByRole('button', { name: /Menu/ });
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expectAccessible(page, 'mobile menu open');
  });

  test('open FAQ answer', async ({ page }) => {
    await page.goto('/about');
    await page.getByText('How long does a project take?').click();
    await expect(page.getByText('six to ten weeks')).toBeVisible();
    await expectAccessible(page, 'FAQ open');
  });

  test('contact form with validation errors', async ({ page }) => {
    await page.goto('/contact');
    await page.getByLabel('Email', { exact: true }).fill('not-an-email');
    await page.getByRole('button', { name: 'Send message' }).click();
    await expect(
      page.getByText(/There are problems with your message/),
    ).toBeVisible();
    await expectAccessible(page, 'contact errors');
  });

  test('contact form after sending', async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'x-forwarded-for': '198.51.100.77' });
    await page.goto('/contact');
    await page.getByLabel('Name', { exact: true }).fill('Axe Visitor');
    await page.getByLabel('Email', { exact: true }).fill('axe@example.com');
    await page
      .getByLabel('Message', { exact: true })
      .fill(`Accessibility check ${Date.now()}`);
    await page.getByLabel(/I agree/).check();
    await page.getByRole('button', { name: 'Send message' }).click();
    await expect(
      page.getByRole('status').filter({ hasText: 'Thank you' }),
    ).toBeVisible();
    await expectAccessible(page, 'contact sent');
  });
});

test.describe('focus and motion', () => {
  test('keyboard focus is always visible', async ({ page }) => {
    await page.goto('/');
    for (let i = 0; i < 12; i++) {
      await page.keyboard.press('Tab');
      const outline = await page.evaluate(() => {
        const el = document.activeElement as HTMLElement | null;
        if (!el || el === document.body) return 'none';
        const style = getComputedStyle(el);
        return style.outlineStyle !== 'none' && style.outlineWidth !== '0px'
          ? 'outline'
          : style.boxShadow !== 'none'
            ? 'shadow'
            : `none on ${el.outerHTML.slice(0, 80)}`;
      });
      expect(outline, `tab stop ${i + 1}`).not.toMatch(/^none/);
    }
  });

  test('reduced motion turns off smooth scrolling and transitions', async ({
    page,
  }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');
    const motion = await page.evaluate(() => ({
      scroll: getComputedStyle(document.documentElement).scrollBehavior,
      transition: getComputedStyle(
        document.querySelector('a') as HTMLAnchorElement,
      ).transitionDuration,
    }));
    expect(motion.scroll).not.toBe('smooth');
    expect(Number.parseFloat(motion.transition)).toBeLessThanOrEqual(0.01);
  });
});
