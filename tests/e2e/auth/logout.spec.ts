import { test, expect } from '@playwright/test';

const ADMIN_USER = process.env.E2E_ADMIN_USER ?? 'admin';
const ADMIN_PASS = process.env.E2E_ADMIN_PASS ?? 'admin123';

test.describe('Logout', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('logout redirects to login page', async ({ page }) => {
    // Login first
    await page.goto('/login');
    await page.locator('#username').fill(ADMIN_USER);
    await page.locator('#password').fill(ADMIN_PASS);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin/, { timeout: 15_000 });

    // Logout
    await page.goto('/logout');
    await expect(page).toHaveURL(/\/login/);
  });

  test('logout clears session', async ({ page }) => {
    // Login
    await page.goto('/login');
    await page.locator('#username').fill(ADMIN_USER);
    await page.locator('#password').fill(ADMIN_PASS);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin/, { timeout: 15_000 });

    // Logout
    await page.goto('/logout');
    await expect(page).toHaveURL(/\/login/);

    // Try to access protected page — should redirect to login
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/login/);
  });
});
