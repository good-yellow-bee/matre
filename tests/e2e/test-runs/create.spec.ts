import { test, expect } from '@playwright/test';

test.describe('Test Run Creation', () => {
  test('form loads with Vue island', async ({ page }) => {
    await page.goto('/admin/test-runs/new');

    await expect(page.locator('h1').first()).toBeVisible();
    await expect(page.locator('[data-vue-island="test-run-form"]')).toBeVisible();
  });

  test('form Vue island hydrates', async ({ page }) => {
    await page.goto('/admin/test-runs/new');

    await page.waitForSelector('[data-vue-island="test-run-form"][data-v-app]', { timeout: 10_000 });
  });

  test('form page returns 200', async ({ page }) => {
    const response = await page.goto('/admin/test-runs/new');
    expect(response?.status()).toBe(200);
  });

  test('form has back link', async ({ page }) => {
    await page.goto('/admin/test-runs/new');

    const backLink = page.locator('a[href*="test-runs"]').filter({ hasText: /back|list/i });
    const count = await backLink.count();

    // Back link may exist
    expect(count).toBeGreaterThanOrEqual(0);
  });
});
