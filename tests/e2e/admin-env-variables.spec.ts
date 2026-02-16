import { test, expect } from '@playwright/test';

test.describe('Environment Variables Management', () => {
  test('env variables index page loads with Vue grid', async ({ page }) => {
    await page.goto('/admin/env-variables');

    await expect(page.locator('h1')).toHaveText('Global Environment Variables');
    await expect(page.locator('[data-vue-island="env-variable-grid"]')).toBeVisible();
  });

  test('env variables grid hydrates', async ({ page }) => {
    await page.goto('/admin/env-variables');

    await page.waitForSelector('[data-vue-island="env-variable-grid"][data-v-app]', { timeout: 10_000 });
  });

  test('env variables page has API URL configured', async ({ page }) => {
    await page.goto('/admin/env-variables');

    const grid = page.locator('[data-vue-island="env-variable-grid"]');
    const apiUrl = await grid.getAttribute('data-api-url');
    expect(apiUrl).toContain('/api/env-variables');
  });
});
