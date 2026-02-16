import { test, expect } from '@playwright/test';

test.describe('Test Environment Toggle Active', () => {
  test('environment has active status indicator', async ({ page }) => {
    await page.goto('/admin/test-environments');
    const rows = page.locator('tbody tr');
    const count = await rows.count();

    if (count === 0) {
      test.skip();
      return;
    }

    const statusIndicator = rows.first().locator('.badge, [class*="status"]');
    await expect(statusIndicator.first()).toBeVisible();
  });
});
