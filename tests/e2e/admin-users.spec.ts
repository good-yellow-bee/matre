import { test, expect } from '@playwright/test';

test.describe('Users Management', () => {
  test('users index page loads with grid', async ({ page }) => {
    await page.goto('/admin/users');

    await expect(page.locator('h1')).toContainText('Users Management');
    await expect(page.locator('[data-vue-island="customers-grid"]')).toBeVisible();
  });

  test('users index has new user button', async ({ page }) => {
    await page.goto('/admin/users');

    const addBtn = page.getByRole('link', { name: 'New User' });
    await expect(addBtn).toBeVisible();
  });

  test('new user form loads with Vue island', async ({ page }) => {
    await page.goto('/admin/users/new');

    await expect(page.locator('h1')).toHaveText('Create New User');
    await expect(page.locator('[data-vue-island="user-form"]')).toBeVisible();
  });

  test('users grid shows fixture data', async ({ page }) => {
    await page.goto('/admin/users');

    // Wait for Vue grid to hydrate
    const grid = page.locator('[data-vue-island="customers-grid"]');
    await page.waitForSelector('[data-vue-island="customers-grid"][data-v-app]', { timeout: 10_000 });

    // The admin user from fixtures should be visible in the grid
    await expect(grid.getByRole('cell', { name: 'admin', exact: true })).toBeVisible({ timeout: 15_000 });
  });

  test('edit user form loads', async ({ page }) => {
    await page.goto('/admin/users');
    await page.waitForSelector('[data-vue-island="customers-grid"][data-v-app]', { timeout: 10_000 });

    const editLink = page.locator('a[href*="/users/"][href*="/edit"]').first();
    await expect(editLink).toBeVisible({ timeout: 10_000 });
    await editLink.click();
    await expect(page.locator('h1')).toHaveText('Edit User');
    await expect(page.locator('[data-vue-island="user-form"]')).toBeVisible();
  });
});
