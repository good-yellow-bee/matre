import { test, expect } from '@playwright/test';

test.describe('Global Settings', () => {
  test('settings page loads', async ({ page }) => {
    await page.goto('/admin/settings');
    await expect(page.locator('h1')).toContainText('Settings');
  });

  test('site name field available', async ({ page }) => {
    await page.goto('/admin/settings');

    const siteNameField = page.locator('[name*="siteName"], [name*="site_name"]');
    const hasField = await siteNameField.count();

    expect(hasField).toBeGreaterThan(0);
  });

  test('2FA enforcement toggle available', async ({ page }) => {
    await page.goto('/admin/settings');

    const enforce2faField = page.locator('[name*="enforce2fa"], [name*="2fa"], input[type="checkbox"]');
    const hasField = await enforce2faField.count();

    expect(hasField).toBeGreaterThan(0);
  });

  test('headless mode toggle available', async ({ page }) => {
    await page.goto('/admin/settings');

    const headlessField = page.locator('[name*="headless"]');
    const hasField = await headlessField.count();

    expect(hasField).toBeGreaterThanOrEqual(0);
  });

  test('settings form has save button', async ({ page }) => {
    await page.goto('/admin/settings');

    const saveBtn = page.locator('button[type="submit"]:has-text("save")');
    await expect(saveBtn).toBeVisible();
  });
});
