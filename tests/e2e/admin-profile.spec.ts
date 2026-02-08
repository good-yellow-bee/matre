import { test, expect } from '@playwright/test';

test.describe('User Profile', () => {
  test('profile notifications page loads', async ({ page }) => {
    await page.goto('/admin/profile/notifications');

    await expect(page.locator('h1')).toHaveText('Notification Settings');
    await expect(page.locator('[data-vue-island="profile-notifications"]')).toBeVisible();
  });

  test('profile notifications Vue island hydrates', async ({ page }) => {
    await page.goto('/admin/profile/notifications');

    await page.waitForSelector('[data-vue-island="profile-notifications"][data-v-app]', { timeout: 10_000 });
  });

  test('profile notifications has API URL configured', async ({ page }) => {
    await page.goto('/admin/profile/notifications');

    const island = page.locator('[data-vue-island="profile-notifications"]');
    const apiUrl = await island.getAttribute('data-api-url');
    expect(apiUrl).toContain('/api/profile/notifications');
  });
});
