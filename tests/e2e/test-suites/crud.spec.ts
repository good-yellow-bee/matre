import { test, expect } from '@playwright/test';

test.describe('Test Suite CRUD', () => {
  test('test suites index page loads', async ({ page }) => {
    await page.goto('/admin/test-suites');
    await expect(page.locator('h1')).toContainText('Test Suites');
  });

  test('new suite button visible', async ({ page }) => {
    await page.goto('/admin/test-suites');
    const newBtn = page.getByRole('link', { name: 'Add Suite' });
    await expect(newBtn).toBeVisible();
  });

  test('new suite form loads with Vue island', async ({ page }) => {
    await page.goto('/admin/test-suites/new');

    await expect(page.locator('h1')).toHaveText('Add Test Suite');
    await expect(page.locator('[data-vue-island="test-suite-form"]')).toBeVisible();
  });

  test('new suite form Vue island hydrates', async ({ page }) => {
    await page.goto('/admin/test-suites/new');

    await page.waitForSelector('[data-vue-island="test-suite-form"][data-v-app]', { timeout: 10_000 });
  });

  test('suites table has expected columns', async ({ page }) => {
    await page.goto('/admin/test-suites');

    const main = page.locator('main');
    await expect(main.locator('th', { hasText: 'Name' })).toBeVisible();
    await expect(main.locator('th', { hasText: 'Type' })).toBeVisible();
    await expect(main.locator('th', { hasText: 'Test Pattern' })).toBeVisible();
  });
});
