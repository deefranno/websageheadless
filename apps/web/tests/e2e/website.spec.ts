import { expect, test, type Page } from '@playwright/test';
import { configured, skipReason } from './support/wp';

// Runs against the seeded demo site (`wp websage seed`).
test.skip(!configured, skipReason);

/** A distinct client address per test, so rate-limit buckets never collide. */
function clientAddress() {
  const octet = () => Math.floor(Math.random() * 250) + 1;
  return `198.51.${octet()}.${octet()}`;
}

test.describe('navigation', () => {
  test('main navigation reaches every top-level page', async ({ page }) => {
    await page.goto('/');
    await expect(
      page.getByRole('heading', {
        level: 1,
        name: 'Websites that grow with your business',
      }),
    ).toBeVisible();
    const nav = page.getByRole('navigation', { name: 'Main' });

    await nav.getByRole('link', { name: 'Services' }).click();
    await expect(page).toHaveURL(/\/services$/);
    await expect(
      page.getByRole('heading', { level: 1, name: 'Services' }),
    ).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Services' })).toHaveAttribute(
      'aria-current',
      'page',
    );

    await nav.getByRole('link', { name: 'Blog' }).click();
    await expect(page).toHaveURL(/\/blog$/);

    await nav.getByRole('link', { name: 'Contact' }).click();
    await expect(
      page.getByRole('heading', { level: 1, name: 'Contact us' }),
    ).toBeVisible();
  });

  test('submenus open from the keyboard and close with Escape', async ({
    page,
  }) => {
    await page.goto('/');
    const about = page
      .getByRole('navigation', { name: 'Main' })
      .getByRole('button', { name: /About/ });
    await about.focus();
    await page.keyboard.press('Enter');
    await expect(about).toHaveAttribute('aria-expanded', 'true');
    const submenuLink = page
      .locator(`#${await about.getAttribute('aria-controls')}`)
      .getByRole('link', { name: 'Services' });
    await expect(submenuLink).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(about).toHaveAttribute('aria-expanded', 'false');
    await expect(about).toBeFocused();
  });

  test('skip link and back-to-top link work', async ({ page }) => {
    await page.goto('/about');
    await page.keyboard.press('Tab');
    const skip = page.getByRole('link', { name: 'Skip to content' });
    await expect(skip).toBeFocused();
    await expect(
      page.getByRole('link', { name: 'Back to top' }),
    ).toHaveAttribute('href', '#top');
  });
});

test.describe('page rendering', () => {
  test('every section type renders from WordPress content', async ({
    page,
  }) => {
    await page.goto('/services');
    await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
    await expect(
      page.getByRole('region', { name: 'Built for your team' }),
    ).toBeVisible();
    const packages = page.getByRole('region', { name: 'Packages' });
    await expect(packages.getByRole('listitem')).toHaveCount(3);
    await expect(
      page.getByRole('heading', { level: 2, name: 'Pricing' }),
    ).toBeVisible();
    await expect(
      page.getByRole('link', { name: 'Get in touch' }),
    ).toHaveAttribute('href', '/contact');

    await page.goto('/');
    const testimonials = page.getByRole('region', { name: 'What clients say' });
    await expect(testimonials.locator('figure')).toHaveCount(3);
    await expect(testimonials.getByText('Northwind Studio')).toBeVisible();

    await page.goto('/about');
    const faq = page.getByRole('region', {
      name: 'Frequently asked questions',
    });
    const question = faq.getByText('How long does a project take?');
    await expect(faq.getByText('six to ten weeks')).toBeHidden();
    await question.click();
    await expect(faq.getByText('six to ten weeks')).toBeVisible();
  });

  test('pages carry metadata and structured data', async ({ page }) => {
    await page.goto('/services');
    await expect(page).toHaveTitle('Services | WebSage Demo');
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
      'href',
      /\/services$/,
    );
    await expect(page.locator('meta[name="description"]')).toHaveAttribute(
      'content',
      'Strategy, design and development services.',
    );
    await expect(page.locator('meta[property="og:title"]')).toHaveAttribute(
      'content',
      'Services | WebSage Demo',
    );
    const ld = await page
      .locator('script[type="application/ld+json"]')
      .allTextContents();
    const types = ld.flatMap((text) => {
      const data = JSON.parse(text);
      return (data['@graph'] ?? [data]).map(
        (item: { '@type': string }) => item['@type'],
      );
    });
    expect(types).toEqual(
      expect.arrayContaining(['Organization', 'WebSite', 'BreadcrumbList']),
    );
  });

  test('sitemap and robots are generated from WordPress routes', async ({
    request,
  }) => {
    const sitemap = await request.get('/sitemap.xml');
    expect(sitemap.status()).toBe(200);
    const xml = await sitemap.text();
    expect(xml).toContain('/services</loc>');
    expect(xml).toContain('/blog/our-autumn-availability</loc>');
    const robots = await (await request.get('/robots.txt')).text();
    expect(robots).toContain('Disallow: /api/');
  });
});

test.describe('blog', () => {
  test('list paginates and filters by category', async ({ page }) => {
    await page.goto('/blog');
    await expect(
      page.getByRole('heading', { level: 1, name: 'Blog' }),
    ).toBeVisible();
    await expect(page.getByRole('article')).toHaveCount(9);
    const pagination = page.getByRole('navigation', { name: 'Pagination' });
    await expect(pagination).toContainText('Page 1 of 2');

    await pagination.getByRole('link', { name: /Older posts/ }).click();
    await expect(page).toHaveURL(/page=2/);
    await expect(pagination).toContainText('Page 2 of 2');
    // Seeded posts plus anything else published (e.g. WordPress's sample post).
    expect(await page.getByRole('article').count()).toBeGreaterThan(0);

    await page
      .getByRole('navigation', { name: 'Categories' })
      .getByRole('link', { name: 'Guides' })
      .click();
    await expect(
      page.getByRole('heading', { level: 1, name: 'Blog: Guides' }),
    ).toBeVisible();
    await expect(page.getByRole('article')).toHaveCount(6);
  });

  test('post detail shows the article with date, categories and structured data', async ({
    page,
  }) => {
    await page.goto('/blog');
    await page.getByRole('link', { name: 'Our autumn availability' }).click();
    await expect(page).toHaveURL(/\/blog\/our-autumn-availability$/);
    await expect(
      page.getByRole('heading', { level: 1, name: 'Our autumn availability' }),
    ).toBeVisible();
    // Next.js keeps the previous route mounted but hidden; query the visible article.
    await expect(
      page.getByRole('article').locator('time').first(),
    ).toHaveAttribute('datetime', /2026-09/);
    await expect(
      page.getByRole('heading', { level: 2, name: 'Why it matters' }),
    ).toBeVisible();
    await expect(page).toHaveTitle(/Our autumn availability/);
    const ld = (
      await page.locator('script[type="application/ld+json"]').allTextContents()
    ).join(' ');
    expect(ld).toContain('"@type":"Article"');

    await page.getByRole('link', { name: '← All posts' }).click();
    await expect(page).toHaveURL(/\/blog$/);
  });

  test('a missing post is a real 404', async ({ page }) => {
    const response = await page.goto('/blog/no-such-post');
    expect(response?.status()).toBe(404);
  });
});

test.describe('contact form', () => {
  async function fill(page: Page) {
    await page.getByLabel('Name', { exact: true }).fill('E2E Visitor');
    await page.getByLabel('Email', { exact: true }).fill('visitor@example.com');
    await page
      .getByLabel('Message', { exact: true })
      .fill(`Hello from Playwright ${Date.now()}`);
    await page.getByLabel(/I agree/).check();
  }

  test('a valid message is sent and confirmed', async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'x-forwarded-for': clientAddress() });
    await page.goto('/contact');
    await fill(page);
    await page.getByRole('button', { name: 'Send message' }).click();
    await expect(
      page.getByRole('status').filter({ hasText: 'Thank you' }),
    ).toContainText('Your message has been sent');
    await expect(page.getByLabel('Name', { exact: true })).toHaveValue('');
  });

  test('validation errors are shown, linked and focused', async ({ page }) => {
    await page.setExtraHTTPHeaders({ 'x-forwarded-for': clientAddress() });
    await page.goto('/contact');
    await page.getByLabel('Email', { exact: true }).fill('not-an-email');
    await page.getByRole('button', { name: 'Send message' }).click();

    await expect(
      page.getByText(/There are problems with your message/),
    ).toBeVisible();
    await expect(page.getByLabel('Email', { exact: true })).toHaveAttribute(
      'aria-invalid',
      'true',
    );
    await expect(
      page.getByLabel('Email', { exact: true }),
    ).toHaveAccessibleDescription(/valid email address/);
    await expect(
      page.getByLabel('Name', { exact: true }),
    ).toHaveAccessibleDescription('Please enter your name.');
  });

  test('the sixth message in fifteen minutes is rate limited', async ({
    page,
  }) => {
    await page.setExtraHTTPHeaders({ 'x-forwarded-for': clientAddress() });
    await page.goto('/contact');
    for (let attempt = 1; attempt <= 5; attempt++) {
      await fill(page);
      await page.getByRole('button', { name: 'Send message' }).click();
      await expect(
        page.getByRole('status').filter({ hasText: 'Thank you' }),
      ).toBeVisible();
    }
    await fill(page);
    await page.getByRole('button', { name: 'Send message' }).click();
    await expect(
      page.getByRole('status').filter({ hasText: 'several messages' }),
    ).toBeVisible();
    await expect(page.getByLabel('Message', { exact: true })).not.toHaveValue(
      '',
      {
        timeout: 1_000,
      },
    );
  });
});

test.describe('mobile menu', () => {
  test.use({ viewport: { width: 375, height: 800 } });

  test('opens, navigates and closes on small screens', async ({ page }) => {
    await page.goto('/');
    const toggle = page.getByRole('button', { name: /Menu/ });
    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    const panel = page.locator(
      `#${await toggle.getAttribute('aria-controls')}`,
    );
    await expect(panel.getByRole('link', { name: 'Home' })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(toggle).toBeFocused();

    await toggle.click();
    await panel.getByRole('link', { name: 'Contact' }).click();
    await expect(page).toHaveURL(/\/contact$/);
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  });

  test('pages fit a 320px screen without horizontal scrolling', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 320, height: 800 });
    for (const path of ['/', '/services', '/blog', '/contact']) {
      await page.goto(path);
      const overflow = await page.evaluate(
        () =>
          document.documentElement.scrollWidth -
          document.documentElement.clientWidth,
      );
      expect(overflow, path).toBeLessThanOrEqual(0);
    }
  });
});
