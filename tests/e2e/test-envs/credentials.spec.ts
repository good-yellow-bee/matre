import { test, expect } from '@playwright/test';

test.describe('Test Environment Credentials', () => {
  test('new environment form loads with Vue island', async ({ page }) => {
    await page.goto('/admin/test-environments/new');

    await expect(page.locator('h1')).toHaveText('Add Test Environment');
    await expect(page.locator('[data-vue-island="test-environment-form"]')).toBeVisible();
  });

  test('new environment form Vue island hydrates', async ({ page }) => {
    await page.goto('/admin/test-environments/new');

    await page.waitForSelector('[data-vue-island="test-environment-form"][data-v-app]', { timeout: 10_000 });
  });

  test('new environment form page returns 200', async ({ page }) => {
    const response = await page.goto('/admin/test-environments/new');
    expect(response?.status()).toBe(200);
  });
});
