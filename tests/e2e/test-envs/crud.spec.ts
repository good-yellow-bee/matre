import { test, expect } from '@playwright/test';

test.describe('Test Environment CRUD', () => {
  test('environments index page loads', async ({ page }) => {
    await page.goto('/admin/test-environments');
    await expect(page.locator('h1')).toContainText('Test Environments');
  });

  test('new environment button visible', async ({ page }) => {
    await page.goto('/admin/test-environments');
    const newBtn = page.getByRole('link', { name: 'Add Environment' });
    await expect(newBtn).toBeVisible();
  });

  test('environments table has expected columns', async ({ page }) => {
    await page.goto('/admin/test-environments');

    const main = page.locator('main');
    await expect(main.locator('th', { hasText: 'Name' })).toBeVisible();
    await expect(main.locator('th', { hasText: 'Base URL' })).toBeVisible();
  });

  test('environments table shows data or empty state', async ({ page }) => {
    await page.goto('/admin/test-environments');

    const main = page.locator('main');
    await expect(main.locator('table').first()).toBeVisible();
  });
});
