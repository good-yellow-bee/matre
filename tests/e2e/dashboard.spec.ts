import { test, expect } from '@playwright/test';
import { DashboardPage } from './pages/DashboardPage';

test.describe('Dashboard', () => {
  test('dashboard page loads', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    await expect(page).toHaveURL(/\/admin/);
    await expect(dashboard.heading).toBeVisible();
  });

  test('dashboard has navigation links', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    // Verify key admin nav links exist
    await expect(page.locator('a[href*="test-runs"]').first()).toBeVisible();
    await expect(page.locator('a[href*="test-suites"]').first()).toBeVisible();
    await expect(page.locator('a[href*="test-environments"]').first()).toBeVisible();
  });
});
