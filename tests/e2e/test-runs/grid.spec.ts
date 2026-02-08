import { test, expect } from '@playwright/test';

test.describe('Test Runs Grid', () => {
  test('grid loads with Vue island', async ({ page }) => {
    await page.goto('/admin/test-runs');

    const grid = page.locator('[data-vue-island="test-run-grid"]');
    await expect(grid).toBeVisible({ timeout: 10_000 });
  });

  test('grid Vue island hydrates', async ({ page }) => {
    await page.goto('/admin/test-runs');

    await page.waitForSelector('[data-vue-island="test-run-grid"][data-v-app]', { timeout: 10_000 });
  });

  test('new run button is visible', async ({ page }) => {
    await page.goto('/admin/test-runs');

    const newRunBtn = page.getByRole('link', { name: 'Start New Run' });
    await expect(newRunBtn).toBeVisible();
  });

  test('grid shows empty state or data', async ({ page }) => {
    await page.goto('/admin/test-runs');

    await page.waitForSelector('[data-vue-island="test-run-grid"][data-v-app]', { timeout: 10_000 });

    const grid = page.locator('[data-vue-island="test-run-grid"]');
    const gridText = await grid.textContent();

    // Grid should show either data rows or an empty state message
    expect(gridText?.length).toBeGreaterThan(0);
  });
});
