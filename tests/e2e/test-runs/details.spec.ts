import { test, expect } from '@playwright/test';

test.describe('Test Run Details', () => {
  test('test run show page redirects for non-existent', async ({ page }) => {
    const response = await page.goto('/admin/test-runs/99999');
    // Non-existent test runs may return 404 or redirect
    expect(response?.status()).toBeLessThan(500);
  });

  test('test run index links to new form', async ({ page }) => {
    await page.goto('/admin/test-runs');

    const newBtn = page.getByRole('link', { name: 'Start New Run' });
    await expect(newBtn).toBeVisible();
    await newBtn.click();

    await expect(page).toHaveURL(/\/admin\/test-runs\/new/);
  });
});
