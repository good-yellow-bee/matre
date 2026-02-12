import { test, expect } from '@playwright/test';

test.describe('User 2FA Reset', () => {
  test('users page loads with Vue grid', async ({ page }) => {
    await page.goto('/admin/users');

    await page.waitForSelector('[data-vue-island="customers-grid"][data-v-app]', { timeout: 10_000 });
  });

  test('users page returns 200', async ({ page }) => {
    const response = await page.goto('/admin/users');
    expect(response?.status()).toBe(200);
  });
});
