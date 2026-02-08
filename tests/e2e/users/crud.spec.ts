import { test, expect } from '@playwright/test';

test.describe('User CRUD', () => {
  test('users index page loads', async ({ page }) => {
    await page.goto('/admin/users');
    await expect(page.locator('h1')).toContainText('Users Management');
  });

  test('new user button visible', async ({ page }) => {
    await page.goto('/admin/users');
    const newBtn = page.getByRole('link', { name: 'New User' });
    await expect(newBtn).toBeVisible();
  });

  test('new user form loads with Vue island', async ({ page }) => {
    await page.goto('/admin/users/new');

    await expect(page.locator('h1')).toHaveText('Create New User');
    await expect(page.locator('[data-vue-island="user-form"]')).toBeVisible();
  });

  test('new user form Vue island hydrates', async ({ page }) => {
    await page.goto('/admin/users/new');

    await page.waitForSelector('[data-vue-island="user-form"][data-v-app]', { timeout: 10_000 });
  });

  test('users grid shows fixture data', async ({ page }) => {
    await page.goto('/admin/users');

    const grid = page.locator('[data-vue-island="customers-grid"]');
    await page.waitForSelector('[data-vue-island="customers-grid"][data-v-app]', { timeout: 10_000 });

    await expect(grid.getByRole('cell', { name: 'admin', exact: true })).toBeVisible({ timeout: 15_000 });
  });
});
