import { expect, test, type Page } from '@playwright/test';
import {
  configured,
  currentPostId,
  logIn,
  openEditor,
  publish,
  skipReason,
  update,
  wpUrl,
} from './support/wp';

test.skip(!configured, skipReason);

/** Reloads until the frontend shows the text, recording how long it took. */
async function eventuallyShows(page: Page, path: string, text: string) {
  const started = Date.now();
  await expect(async () => {
    await page.goto(path);
    await expect(page.getByText(text).first()).toBeVisible({ timeout: 1_000 });
  }).toPass({ timeout: 10_000, intervals: [250, 500, 1_000] });
  return Date.now() - started;
}

test('published edits reach the frontend within seconds, without a redeploy', async ({
  page,
  browser,
}) => {
  const slug = `fresh-${Date.now()}`;
  await logIn(page);
  const canvas = await openEditor(
    page,
    `${wpUrl}/wp-admin/post-new.php?post_type=page`,
  );
  await canvas.getByRole('textbox', { name: 'Add title' }).fill(slug);
  // New pages start from the default template: hero, text and image, CTA.
  await canvas.getByLabel('Heading').first().fill('Before the edit');
  await publish(page);

  const visitor = await browser.newPage();
  await eventuallyShows(visitor, `/${slug}`, 'Before the edit');

  // Edit while the visitor's version is cached, then update.
  await canvas.getByLabel('Heading').first().fill('After the edit');
  await update(page);

  const elapsed = await eventuallyShows(visitor, `/${slug}`, 'After the edit');
  test
    .info()
    .annotations.push({ type: 'freshness-ms', description: String(elapsed) });
  expect(elapsed).toBeLessThan(10_000);
  await visitor.close();
});

test('settings saved in WebSage Settings restyle the site header', async ({
  page,
  browser,
}) => {
  const announcement = `Announcement ${Date.now()}`;
  await logIn(page);
  await page.goto(`${wpUrl}/wp-admin/admin.php?page=wsh_settings`);
  await page.getByRole('tab', { name: 'Header' }).click();
  await page.getByLabel('Announcement text').fill(announcement);
  await page.getByRole('button', { name: 'Save settings' }).click();
  await expect(
    page.getByText('Settings updated', { exact: false }),
  ).toBeVisible();

  const visitor = await browser.newPage();
  await eventuallyShows(visitor, '/', announcement);
  await visitor.close();
});

test('preview shows unpublished changes only to the previewer', async ({
  page,
  browser,
}) => {
  const slug = `preview-${Date.now()}`;
  await logIn(page);
  const canvas = await openEditor(
    page,
    `${wpUrl}/wp-admin/post-new.php?post_type=page`,
  );
  await canvas.getByRole('textbox', { name: 'Add title' }).fill(slug);
  // New pages start from the default template: hero, text and image, CTA.
  await canvas.getByLabel('Heading').first().fill('Published heading');
  await publish(page);

  // Unsaved change, then the editor's own preview path (autosave + link).
  await canvas
    .getByLabel('Heading')
    .first()
    .fill('Draft heading not yet published');
  await canvas
    .getByRole('textbox', { name: 'Add title' })
    .fill(`${slug} draft title`);
  const previewLink = await page.evaluate(async () => {
    const { wp } = window as unknown as {
      wp: {
        data: {
          dispatch: (s: string) => { autosave: (o?: object) => Promise<void> };
          select: (s: string) => { getEditedPostPreviewLink: () => string };
        };
      };
    };
    await wp.data.dispatch('core/editor').autosave({ isPreview: true });
    return wp.data.select('core/editor').getEditedPostPreviewLink();
  });
  expect(previewLink).toContain('action=wsh_preview');
  expect(await currentPostId(page)).toBeGreaterThan(0);

  const previewResponse = await page.goto(previewLink);
  await expect(page).toHaveURL(new RegExp(`/${slug}$`));
  expect(previewResponse?.headers()['cache-control'] ?? '').toMatch(/no-store/);
  await expect(
    page.getByRole('heading', {
      level: 1,
      name: 'Draft heading not yet published',
    }),
  ).toBeVisible();
  await expect(
    page.getByText('Preview: you are seeing unpublished changes.'),
  ).toBeVisible();
  // The tab title follows the draft too.
  await expect(page).toHaveTitle(new RegExp(`${slug} draft title`));

  // Visitors still see the published version.
  const visitor = await browser.newPage();
  await visitor.goto(`/${slug}`);
  await expect(
    visitor.getByRole('heading', { level: 1, name: 'Published heading' }),
  ).toBeVisible();
  await visitor.close();

  // Leaving preview returns to published content.
  await page.getByRole('button', { name: 'Exit preview' }).click();
  await page.goto(`/${slug}`);
  await expect(
    page.getByRole('heading', { level: 1, name: 'Published heading' }),
  ).toBeVisible();
});

test('a forged preview link is refused', async ({ page }) => {
  const response = await page.goto('/api/preview?token=forged.token');
  expect(response?.status()).toBe(401);
});

test('diagnostics report the integration and a test refresh is accepted', async ({
  page,
}) => {
  await logIn(page);
  await page.goto(`${wpUrl}/wp-admin/admin.php?page=wsh-diagnostics`);
  const table = page.getByRole('table');
  for (const name of [
    'WSH_SERVER_API_SECRET',
    'WSH_PREVIEW_SECRET',
    'WSH_REVALIDATION_SECRET',
  ]) {
    await expect(
      table.getByRole('row', { name: new RegExp(name) }),
    ).toContainText('Configured');
  }
  await expect(table).not.toContainText(
    process.env.WSH_PREVIEW_SECRET ?? 'unset',
  );

  await page.getByRole('link', { name: 'Send a test refresh' }).click();
  await expect(page.getByText('Test refresh delivered')).toBeVisible();

  await page
    .getByRole('link', { name: 'Check the website is reachable' })
    .click();
  await expect(
    page.getByText(/The website answered with HTTP 200/),
  ).toBeVisible();
});
