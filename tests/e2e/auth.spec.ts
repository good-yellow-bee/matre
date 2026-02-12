import { test, expect } from '@playwright/test';
import { LoginPage } from './pages/LoginPage';

test.describe('Authentication', () => {
  test.use({ storageState: { cookies: [], origins: [] } }); // No auth for login tests

  test('login page renders correctly', async ({ page }) => {
    const loginPage = new LoginPage(page);
    await loginPage.goto();

    await expect(page).toHaveTitle(/Sign in/);
    await expect(loginPage.usernameInput).toBeVisible();
    await expect(loginPage.passwordInput).toBeVisible();
    await expect(loginPage.submitButton).toBeVisible();
    await expect(loginPage.submitButton).toHaveText('Sign in');
  });

  test('login with valid credentials redirects to dashboard', async ({ page }) => {
    const loginPage = new LoginPage(page);
    await loginPage.goto();
    await loginPage.login('admin', 'admin123');

    await expect(page).toHaveURL(/\/admin/, { timeout: 15_000 });
  });

  test('login with invalid credentials shows error', async ({ page }) => {
    const loginPage = new LoginPage(page);
    await loginPage.goto();
    await loginPage.login('admin', 'wrongpassword');

    await expect(loginPage.errorAlert).toBeVisible();
    await expect(page).toHaveURL(/\/login/);
  });

  test('unauthenticated user is redirected to login', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/login/);
  });

  test('logout redirects to login page', async ({ page }) => {
    // First login
    const loginPage = new LoginPage(page);
    await loginPage.goto();
    await loginPage.login('admin', 'admin123');
    await expect(page).toHaveURL(/\/admin/, { timeout: 15_000 });

    // Then logout
    await page.goto('/logout');
    await expect(page).toHaveURL(/\/login/);
  });
});
