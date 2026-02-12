import { test, expect } from '@playwright/test';

test.describe('Environment Variables CRUD', () => {
  test('env vars page loads', async ({ page }) => {
    await page.goto('/admin/env-variables');
    await expect(page.locator('h1')).toContainText('Global Environment Variables');
  });

  test('env vars Vue grid hydrates', async ({ page }) => {
    await page.goto('/admin/env-variables');

    await page.waitForSelector('[data-vue-island="env-variable-grid"][data-v-app]', { timeout: 10_000 });
  });

  test('env vars grid shows content', async ({ page }) => {
    await page.goto('/admin/env-variables');

    await page.waitForSelector('[data-vue-island="env-variable-grid"][data-v-app]', { timeout: 10_000 });

    const grid = page.locator('[data-vue-island="env-variable-grid"]');
    const gridText = await grid.textContent();
    expect(gridText?.length).toBeGreaterThan(0);
  });
});
