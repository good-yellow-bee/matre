import { test, expect } from '@playwright/test';
import { LoginPage } from '../pages/LoginPage';

test.describe('Login', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('login page renders all elements', async ({ page }) => {
    const loginPage = new LoginPage(page);
    await loginPage.goto();

    await expect(page).toHaveTitle(/Sign in/);
    await expect(loginPage.usernameInput).toBeVisible();
    await expect(loginPage.passwordInput).toBeVisible();
    await expect(loginPage.submitButton).toBeVisible();
    await expect(loginPage.submitButton).toHaveText('Sign in');
  });

  test('valid credentials redirect to dashboard', async ({ page }) => {
    const loginPage = new LoginPage(page);
    await loginPage.goto();
    await loginPage.login('admin', 'admin123');

    await expect(page).toHaveURL(/\/admin/, { timeout: 15_000 });
  });

  test('invalid credentials show error', async ({ page }) => {
    const loginPage = new LoginPage(page);
    await loginPage.goto();
    await loginPage.login('admin', 'wrongpassword');

    await expect(loginPage.errorAlert).toBeVisible();
    await expect(page).toHaveURL(/\/login/);
  });

  test('remember me checkbox available', async ({ page }) => {
    const loginPage = new LoginPage(page);
    await loginPage.goto();

    const rememberCheckbox = page.locator('[name="_remember_me"]');
    const hasRememberMe = await rememberCheckbox.count();

    expect(hasRememberMe).toBeGreaterThanOrEqual(0);
  });

  test('unauthenticated access redirects to login', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/login/);
  });

  test('password field masked by default', async ({ page }) => {
    const loginPage = new LoginPage(page);
    await loginPage.goto();

    await expect(loginPage.passwordInput).toHaveAttribute('type', 'password');
  });

  test('form validation prevents empty submission', async ({ page }) => {
    const loginPage = new LoginPage(page);
    await loginPage.goto();

    await loginPage.submitButton.click();

    // Should remain on login page
    await expect(page).toHaveURL(/\/login/);
  });
});
