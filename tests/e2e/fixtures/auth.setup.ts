import { test as setup, expect } from '@playwright/test';

const ADMIN_USER = process.env.E2E_ADMIN_USER ?? 'admin';
const ADMIN_PASS = process.env.E2E_ADMIN_PASS ?? 'admin123';
const AUTH_FILE = 'tests/e2e/.auth/admin.json';

setup('authenticate as admin', async ({ page }) => {
  await page.goto('/login', { waitUntil: 'networkidle' });

  // Wait for form to be ready
  await page.waitForSelector('form', { state: 'visible' });
  await page.waitForSelector('#username', { state: 'visible' });
  await page.waitForSelector('#password', { state: 'visible' });
  await page.waitForSelector('button[type="submit"]', { state: 'visible' });

  await page.locator('#username').fill(ADMIN_USER);
  await page.locator('#password').fill(ADMIN_PASS);

  // Submit form and wait for navigation
  await Promise.all([
    page.waitForURL(/\/admin/, { timeout: 15_000 }),
    page.locator('button[type="submit"]').click(),
  ]);

  await page.context().storageState({ path: AUTH_FILE });
});
