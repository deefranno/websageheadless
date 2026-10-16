import { PageResponseSchema } from '@websage/contracts';
import { expect, test } from '@playwright/test';
import {
  configured,
  logIn,
  openEditor,
  publish,
  skipReason,
  wpUrl,
} from './support/wp';

test.skip(!configured, skipReason);

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
  const canvas = await openEditor(
    page,
    `${wpUrl}/wp-admin/post-new.php?post_type=page`,
  );
  await canvas.getByRole('textbox', { name: 'Add title' }).fill(unique);
  // New pages start from the default template: hero, text and image, CTA.
  await canvas.getByLabel('Eyebrow').first().fill('From Playwright');
  await canvas.getByLabel('Heading').first().fill(heading);
  await canvas
    .getByRole('textbox', { name: 'Body text' })
    .first()
    .fill('Saved through the real editor.');
  await publish(page);

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

  await page.goto(`/${unique}`);
  await expect(
    page.getByRole('heading', { level: 1, name: heading }),
  ).toBeVisible();
  await expect(page.getByText('Saved through the real editor.')).toBeVisible();

  expect(
    editorErrors.filter((text) => /websage|block validation/i.test(text)),
  ).toEqual([]);
});

test('a missing page is a real 404 with the not-found UI', async ({ page }) => {
  const response = await page.goto('/this-page-does-not-exist-e2e');
  expect(response?.status()).toBe(404);
  await expect(
    page.getByRole('heading', { name: 'Page not found' }),
  ).toBeVisible();
  await expect(
    page.locator('head meta[name="robots"]').first(),
  ).toHaveAttribute('content', /noindex/);
});
