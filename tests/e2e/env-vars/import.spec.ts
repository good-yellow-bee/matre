import { test, expect } from '@playwright/test';

test.describe('Environment Variables Import', () => {
  test('env vars page loads and grid hydrates', async ({ page }) => {
    await page.goto('/admin/env-variables');

    await page.waitForSelector('[data-vue-island="env-variable-grid"][data-v-app]', { timeout: 10_000 });
  });

  test('env vars page returns 200', async ({ page }) => {
    const response = await page.goto('/admin/env-variables');
    expect(response?.status()).toBe(200);
  });
});
