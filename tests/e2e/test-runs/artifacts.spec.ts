import { test, expect } from '@playwright/test';

test.describe('Test Run Artifacts', () => {
  test('test runs grid shows empty state or data', async ({ page }) => {
    await page.goto('/admin/test-runs');

    await page.waitForSelector('[data-vue-island="test-run-grid"][data-v-app]', { timeout: 10_000 });

    const grid = page.locator('[data-vue-island="test-run-grid"]');
    const gridText = await grid.textContent();
    expect(gridText?.length).toBeGreaterThan(0);
  });

  test('new run form has Vue island', async ({ page }) => {
    await page.goto('/admin/test-runs/new');

    await expect(page.locator('[data-vue-island="test-run-form"]')).toBeVisible();
  });

  test('new run form Vue island hydrates', async ({ page }) => {
    await page.goto('/admin/test-runs/new');

    await page.waitForSelector('[data-vue-island="test-run-form"][data-v-app]', { timeout: 10_000 });
  });
});
