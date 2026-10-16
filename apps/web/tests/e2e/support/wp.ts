import { expect, type Page } from '@playwright/test';

export const wpUrl = process.env.WSH_E2E_WP_URL ?? '';
const user = process.env.WSH_E2E_WP_USER ?? '';
const password = process.env.WSH_E2E_WP_PASSWORD ?? '';

export const configured = Boolean(wpUrl && user && password);
export const skipReason =
  'Set WSH_E2E_WP_URL, WSH_E2E_WP_USER and WSH_E2E_WP_PASSWORD';

export async function logIn(page: Page) {
  const target = encodeURIComponent(`${wpUrl}/wp-admin/`);
  await page.goto(`${wpUrl}/wp-login.php?redirect_to=${target}`);
  await page.getByLabel('Username or Email Address').fill(user);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Log In' }).click();
  await expect(page).toHaveURL(/wp-admin/);
}

/** Opens the block editor and closes first-run dialogs. */
export async function openEditor(page: Page, url: string) {
  await page.goto(url);
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
  return page.frameLocator('iframe[name="editor-canvas"]');
}

/** Inserts the Hero block through the inserter. */
export async function insertHero(page: Page) {
  await page.getByRole('button', { name: /block inserter/i }).click();
  await page.getByPlaceholder('Search').fill('Hero');
  await page
    .getByRole('option')
    .filter({ hasText: /^Hero$/ })
    .first()
    .click();
}

export async function publish(page: Page) {
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
}

/** Saves changes to already-published content. */
export async function update(page: Page) {
  await page
    .getByRole('region', { name: 'Editor top bar' })
    .getByRole('button', { name: /^(Update|Save)$/ })
    .click();
  await expect(page.locator('.components-snackbar').last()).toContainText(
    /updated/i,
    { timeout: 15_000 },
  );
}

/** Post ID of the page open in the editor. */
export async function currentPostId(page: Page): Promise<number> {
  return page.evaluate(() =>
    (
      window as unknown as {
        wp: {
          data: { select: (s: string) => { getCurrentPostId: () => number } };
        };
      }
    ).wp.data
      .select('core/editor')
      .getCurrentPostId(),
  );
}
