import { defineConfig, devices } from '@playwright/test';

/**
 * Integration suite against a real WordPress and a running frontend.
 *
 *   WSH_E2E_WP_URL        WordPress origin (DDEV: https://websage-headless.ddev.site)
 *   WSH_E2E_WP_USER       disposable admin account on that site
 *   WSH_E2E_WP_PASSWORD
 *   WSH_E2E_WEB_URL       frontend origin (default http://127.0.0.1:3000)
 *
 * The suite creates pages and changes the front page: run it only against a
 * disposable site.
 */
export default defineConfig({
  testDir: './tests/e2e',
  timeout: 60_000,
  fullyParallel: false,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? 'github' : 'list',
  use: {
    baseURL: process.env.WSH_E2E_WEB_URL ?? 'http://127.0.0.1:3000',
    // Local DDEV and harness certificates are self-signed.
    ignoreHTTPSErrors: true,
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
