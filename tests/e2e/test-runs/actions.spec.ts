import { test, expect } from '@playwright/test';

test.describe('Test Run Actions', () => {
  test('test runs index loads with Vue grid', async ({ page }) => {
    await page.goto('/admin/test-runs');

    await expect(page.locator('h1').first()).toBeVisible();
    await expect(page.locator('[data-vue-island="test-run-grid"]')).toBeVisible();
  });

  test('test runs Vue grid hydrates', async ({ page }) => {
    await page.goto('/admin/test-runs');

    await page.waitForSelector('[data-vue-island="test-run-grid"][data-v-app]', { timeout: 10_000 });
  });

  test('start new run button visible', async ({ page }) => {
    await page.goto('/admin/test-runs');

    const newRunBtn = page.getByRole('link', { name: 'Start New Run' });
    await expect(newRunBtn).toBeVisible();
  });
});
