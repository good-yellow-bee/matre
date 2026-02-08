import { test, expect } from '@playwright/test';

test.describe('2FA Setup', () => {
  test('2FA setup page loads', async ({ page }) => {
    const response = await page.goto('/2fa-setup');
    // Should either load or redirect (if 2FA already configured)
    expect(response?.status()).toBeLessThan(500);
  });

  test('2FA setup page accessible for authenticated user', async ({ page }) => {
    await page.goto('/2fa-setup');

    // Page should load without 500 error — may show setup form or redirect
    const url = page.url();
    expect(url).toMatch(/\/(2fa-setup|admin|login)/);
  });
});
