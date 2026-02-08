import { test, expect } from '@playwright/test';

test.describe('Test Run Allure Report', () => {
  test('non-existent test run does not return 500', async ({ page }) => {
    const response = await page.goto('/admin/test-runs/99999');
    expect(response?.status()).toBeLessThan(500);
  });

  test('test runs page loads', async ({ page }) => {
    const response = await page.goto('/admin/test-runs');
    expect(response?.status()).toBe(200);
  });
});
