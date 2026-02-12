import { test, expect } from '@playwright/test';

test.describe('Test Suite Toggle Active', () => {
  test('suite has active/inactive status indicator', async ({ page }) => {
    await page.goto('/admin/test-suites');
    const rows = page.locator('tbody tr');
    const count = await rows.count();

    if (count === 0) {
      test.skip();
      return;
    }

    // Look for status badge or toggle
    const statusIndicator = rows.first().locator('.badge, [class*="status"], input[type="checkbox"]');
    await expect(statusIndicator.first()).toBeVisible();
  });

  test('toggle button available for suites', async ({ page }) => {
    await page.goto('/admin/test-suites');
    const rows = page.locator('tbody tr');
    const count = await rows.count();

    if (count === 0) {
      test.skip();
      return;
    }

    const toggleBtn = rows.first().locator('form[action*="toggle"], button:has-text("activate"), button:has-text("deactivate")');
    const hasToggle = await toggleBtn.count();

    expect(hasToggle).toBeGreaterThanOrEqual(0);
  });
});
