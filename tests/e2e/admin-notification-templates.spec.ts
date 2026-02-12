import { test, expect } from '@playwright/test';

test.describe('Notification Templates', () => {
  test('notification templates index page loads', async ({ page }) => {
    await page.goto('/admin/notification-templates');

    await expect(page.locator('h1')).toHaveText('Notification Templates');
  });

  test('notification templates page returns 200', async ({ page }) => {
    const response = await page.goto('/admin/notification-templates');
    expect(response?.status()).toBe(200);
  });
});
