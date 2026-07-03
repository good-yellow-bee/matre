import { test, expect, type Page } from '@playwright/test';

const ADMIN_USER = process.env.E2E_ADMIN_USER ?? 'admin';
const ADMIN_PASS = process.env.E2E_ADMIN_PASS ?? 'admin123';

test.use({ storageState: { cookies: [], origins: [] } });

async function login(page: Page) {
  await page.goto('/login');
  await page.locator('#username').fill(ADMIN_USER);
  await page.locator('#password').fill(ADMIN_PASS);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await page.waitForURL(/\/admin/);
}

test.describe('authentication', () => {
  test('unauthenticated /admin redirects to login', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForURL(/\/login/);
    await expect(page.locator('#username')).toBeVisible();
  });

  test('bad credentials show error alert', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#username').fill(ADMIN_USER);
    await page.locator('#password').fill('definitely-wrong-password');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.getByText('Invalid credentials.')).toBeVisible();
    await expect(page).toHaveURL(/\/login/);
  });

  test('good login lands on dashboard', async ({ page }) => {
    await login(page);
    await expect(page.locator('h1')).toContainText(`Welcome back, ${ADMIN_USER}`);
  });

  test('logout returns to login', async ({ page }) => {
    await login(page);
    await expect(page.locator('h1')).toContainText('Welcome back');
    await page.locator('button[title="Sign out"]').click();
    await page.waitForURL(/\/login/);
    await expect(page.locator('#username')).toBeVisible();
  });
});
