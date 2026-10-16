import { PageResponseSchema } from '@websage/contracts';
import { expect, test, type Page } from '@playwright/test';

const wpUrl = process.env.WSH_E2E_WP_URL;
const user = process.env.WSH_E2E_WP_USER;
const password = process.env.WSH_E2E_WP_PASSWORD;

test.skip(
  !wpUrl || !user || !password,
  'Set WSH_E2E_WP_URL, WSH_E2E_WP_USER and WSH_E2E_WP_PASSWORD',
);

async function logIn(page: Page) {
  const target = encodeURIComponent(`${wpUrl}/wp-admin/`);
  await page.goto(`${wpUrl}/wp-login.php?redirect_to=${target}`);
  await page.getByLabel('Username or Email Address').fill(user!);
  await page.getByLabel('Password', { exact: true }).fill(password!);
  await page.getByRole('button', { name: 'Log In' }).click();
  await expect(page).toHaveURL(/wp-admin/);
}

test('a hero saved in the block editor reaches the frontend', async ({
  page,
  request,
}) => {
  const unique = `e2e-${Date.now()}`;
  const heading = `Hero from the editor ${unique}`;
  const editorErrors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error') editorErrors.push(message.text());
  });

  await logIn(page);
  await page.goto(`${wpUrl}/wp-admin/post-new.php?post_type=page`);

  // First-run welcome guide and the new-page pattern chooser.
  await page.waitForSelector('iframe[name="editor-canvas"]');
  const dialog = page.locator('.components-modal__frame');
  for (let attempt = 0; attempt < 3; attempt++) {
    if (
      !(await dialog
        .first()
        .isVisible({ timeout: 3_000 })
        .catch(() => false))
    )
      break;
    await page.keyboard.press('Escape');
    await expect(dialog)
      .toHaveCount(0, { timeout: 5_000 })
      .catch(() => {});
  }

  const canvas = page.frameLocator('iframe[name="editor-canvas"]');
  await canvas.getByRole('textbox', { name: 'Add title' }).fill(unique);

  await page.getByRole('button', { name: /block inserter/i }).click();
  await page.getByPlaceholder('Search').fill('Hero');
  // Block results come first; theme patterns titled "Hero …" follow.
  await page
    .getByRole('option')
    .filter({ hasText: /^Hero$/ })
    .first()
    .click();

  await canvas.getByLabel('Eyebrow').fill('From Playwright');
  await canvas.getByLabel('Heading').fill(heading);
  await canvas
    .getByRole('textbox', { name: 'Body text' })
    .fill('Saved through the real editor.');

  await page
    .getByRole('region', { name: 'Editor top bar' })
    .getByRole('button', { name: 'Publish', exact: true })
    .click();
  await page
    .getByRole('region', { name: 'Editor publish' })
    .getByRole('button', { name: 'Publish', exact: true })
    .click();
  await expect(
    page
      .getByRole('region', { name: 'Editor publish' })
      .getByText(/is now live/),
  ).toBeVisible();

  // REST payload produced by parse_blocks() satisfies the shared contract.
  const response = await request.get(
    `${wpUrl}/wp-json/websage/v1/pages/${unique}`,
  );
  expect(response.status()).toBe(200);
  const body = PageResponseSchema.parse(await response.json());
  expect(body.data.sections[0]).toMatchObject({
    type: 'hero',
    eyebrow: 'From Playwright',
    heading,
    body: 'Saved through the real editor.',
    theme: 'default',
  });

  // Frontend renders it (fresh path, so nothing is cached yet).
  await page.goto(`/${unique}`);
  await expect(
    page.getByRole('heading', { level: 1, name: heading }),
  ).toBeVisible();
  await expect(page.getByText('Saved through the real editor.')).toBeVisible();

  expect(
    editorErrors.filter((text) => /websage|block validation/i.test(text)),
  ).toEqual([]);
});

test('a missing page shows the not-found UI and is not indexable', async ({
  page,
}) => {
  await page.goto('/this-page-does-not-exist-e2e');
  await expect(
    page.getByRole('heading', { name: 'Page not found' }),
  ).toBeVisible();
  await expect(page.locator('head meta[name="robots"]')).toHaveAttribute(
    'content',
    'noindex',
  );
});
