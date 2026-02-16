import { test, expect } from '@playwright/test';

test.describe('Test Runs Management', () => {
  test('test runs index page loads', async ({ page }) => {
    await page.goto('/admin/test-runs');

    await expect(page.locator('h1')).toHaveText('Test Runs');
    await expect(page.locator('[data-vue-island="test-run-grid"]')).toBeVisible();
  });

  test('test runs index has new run button', async ({ page }) => {
    await page.goto('/admin/test-runs');

    const newRunBtn = page.getByRole('link', { name: 'Start New Run' });
    await expect(newRunBtn).toBeVisible();
  });

  test('new test run form loads', async ({ page }) => {
    const response = await page.goto('/admin/test-runs/new');
    expect(response?.status()).toBe(200);
    await expect(page.locator('h1').first()).toBeVisible();
  });
});
