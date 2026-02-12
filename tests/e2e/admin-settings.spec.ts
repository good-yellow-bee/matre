import { test, expect } from '@playwright/test';

test.describe('Site Settings', () => {
  test('settings page loads with form', async ({ page }) => {
    await page.goto('/admin/settings');

    await expect(page.locator('h1')).toHaveText('Site Settings');
    await expect(page.locator('form')).toBeVisible();
  });

  test('settings form has expected sections', async ({ page }) => {
    await page.goto('/admin/settings');

    await expect(page.locator('h3', { hasText: 'General Settings' })).toBeVisible();
    await expect(page.locator('h3', { hasText: 'SEO & Meta' })).toBeVisible();
    await expect(page.locator('h3', { hasText: 'Security' })).toBeVisible();
  });

  test('settings form has save button', async ({ page }) => {
    await page.goto('/admin/settings');

    const saveBtn = page.locator('button[type="submit"]');
    await expect(saveBtn).toBeVisible();
    await expect(saveBtn).toHaveText(/Save Settings/);
  });

  test('settings form shows current values', async ({ page }) => {
    await page.goto('/admin/settings');

    // SettingsFixtures sets siteName = 'ATR'
    const siteNameInput = page.locator('#settings_siteName, [name*="siteName"]');
    await expect(siteNameInput).toBeVisible();
  });
});
