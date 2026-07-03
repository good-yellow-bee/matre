import { test, expect } from '@playwright/test';

test.describe('settings', () => {
  test('form renders all sections with current values', async ({ page }) => {
    await page.goto('/admin/settings');
    await expect(page.locator('h1')).toHaveText('Settings');

    for (const name of ['General', 'SEO & Meta', 'Mode', 'Test Execution', 'Security']) {
      await expect(page.getByRole('heading', { name })).toBeVisible();
    }

    await expect(page.locator('#site-name')).not.toHaveValue('');
    await expect(page.locator('#admin-panel-title')).toBeVisible();
    await expect(page.locator('#default-locale')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Save Settings' })).toBeVisible();
  });
});
