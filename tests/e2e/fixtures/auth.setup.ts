import { test as setup, expect } from '@playwright/test';

const ADMIN_USER = process.env.E2E_ADMIN_USER ?? 'admin';
const ADMIN_PASS = process.env.E2E_ADMIN_PASS ?? 'admin123';
const AUTH_FILE = 'tests/e2e/.auth/admin.json';

setup('authenticate as admin', async ({ page }) => {
  await page.goto('/login');
  await page.locator('#username').fill(ADMIN_USER);
  await page.locator('#password').fill(ADMIN_PASS);
  await page.locator('button[type="submit"]').click();

  // Wait for redirect to dashboard after login
  await expect(page).toHaveURL(/\/admin/, { timeout: 15_000 });

  await page.context().storageState({ path: AUTH_FILE });
});
