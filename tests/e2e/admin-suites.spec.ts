import { test, expect } from '@playwright/test';

test.describe('Test Suites Management', () => {
  test('suites index page shows table', async ({ page }) => {
    await page.goto('/admin/test-suites');

    const main = page.locator('main');
    await expect(page.locator('h1')).toHaveText('Test Suites');
    await expect(main.locator('table').first()).toBeVisible();
    await expect(main.locator('th', { hasText: 'Name' })).toBeVisible();
    await expect(main.locator('th', { hasText: 'Type' })).toBeVisible();
    await expect(main.locator('th', { hasText: 'Test Pattern' })).toBeVisible();
  });

  test('suites index has add button', async ({ page }) => {
    await page.goto('/admin/test-suites');

    const addBtn = page.locator('a[href*="test-suites/new"]');
    await expect(addBtn).toBeVisible();
    await expect(addBtn).toHaveText(/Add Suite/);
  });

  test('new suite form loads with Vue island', async ({ page }) => {
    await page.goto('/admin/test-suites/new');

    await expect(page.locator('h1')).toHaveText('Add Test Suite');
    await expect(page.locator('[data-vue-island="test-suite-form"]')).toBeVisible();
  });

  test('suites index shows fixture data', async ({ page }) => {
    await page.goto('/admin/test-suites');

    // TestFixtures creates "MFTF Smoke Tests" and "Playwright E2E Suite"
    await expect(page.locator('td', { hasText: 'MFTF Smoke Tests' })).toBeVisible();
    await expect(page.locator('td', { hasText: 'Playwright E2E Suite' })).toBeVisible();
  });
});
