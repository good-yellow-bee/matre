import { test, expect } from '@playwright/test';

test.describe('Test Environments Management', () => {
  test('environments index page shows table', async ({ page }) => {
    await page.goto('/admin/test-environments');

    const main = page.locator('main');
    await expect(page.locator('h1')).toHaveText('Test Environments');
    await expect(main.locator('table').first()).toBeVisible();
    await expect(main.locator('th', { hasText: 'Name' })).toBeVisible();
    await expect(main.locator('th', { hasText: 'Base URL' })).toBeVisible();
  });

  test('environments index has add button', async ({ page }) => {
    await page.goto('/admin/test-environments');

    const addBtn = page.locator('a[href*="test-environments/new"]');
    await expect(addBtn).toBeVisible();
    await expect(addBtn).toHaveText(/Add Environment/);
  });

  test('new environment form loads with Vue island', async ({ page }) => {
    await page.goto('/admin/test-environments/new');

    await expect(page.locator('h1')).toHaveText('Add Test Environment');
    await expect(page.locator('[data-vue-island="test-environment-form"]')).toBeVisible();
    await expect(page.locator('a', { hasText: 'Back to List' })).toBeVisible();
  });

  test('environments index shows fixture data', async ({ page }) => {
    await page.goto('/admin/test-environments');

    // TestFixtures creates "Staging US" and "Pre-Production EU"
    await expect(page.locator('td', { hasText: 'Staging US' })).toBeVisible();
  });
});
