import { test, expect } from '@playwright/test';

test.describe('Test History', () => {
  test('test history page loads with search form', async ({ page }) => {
    await page.goto('/admin/test-history');

    await expect(page.locator('h1')).toHaveText('Test History');
  });

  test('test history has test ID selector', async ({ page }) => {
    await page.goto('/admin/test-history');

    await expect(page.locator('[data-vue-island="test-id-selector"]')).toBeVisible();
  });

  test('test history has environment select', async ({ page }) => {
    await page.goto('/admin/test-history');

    await expect(page.locator('#environmentId')).toBeVisible();
  });

  test('test history page returns 200', async ({ page }) => {
    const response = await page.goto('/admin/test-history');
    expect(response?.status()).toBe(200);
  });
});
