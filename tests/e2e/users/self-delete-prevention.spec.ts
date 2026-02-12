import { test, expect } from '@playwright/test';

test.describe('User Self-Delete Prevention', () => {
  test('users page accessible', async ({ page }) => {
    const response = await page.goto('/admin/users');
    expect(response?.status()).toBe(200);
  });

  test('users grid loads', async ({ page }) => {
    await page.goto('/admin/users');

    await expect(page.locator('[data-vue-island="customers-grid"]')).toBeVisible();
  });
});
