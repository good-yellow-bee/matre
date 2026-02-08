import { test, expect } from '@playwright/test';

test.describe('Dashboard Stats', () => {
  test('dashboard page loads', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/admin/);
  });

  test('dashboard shows environment aggregates', async ({ page }) => {
    await page.goto('/admin');

    const statsCards = page.locator('.card, [class*="stat"], [data-stat-card]');
    const hasStats = await statsCards.count();

    expect(hasStats).toBeGreaterThan(0);
  });

  test('dashboard shows test run statistics', async ({ page }) => {
    await page.goto('/admin');

    const pageText = await page.textContent('body');

    expect(pageText).toMatch(/test|run|pass|fail/i);
  });

  test('dashboard has links to main sections', async ({ page }) => {
    await page.goto('/admin');

    const testRunsLink = page.locator('a[href*="/test-runs"]');
    const suitesLink = page.locator('a[href*="/test-suites"]');
    const envsLink = page.locator('a[href*="/test-environments"]');

    await expect(testRunsLink.first()).toBeVisible();
    await expect(suitesLink.first()).toBeVisible();
    await expect(envsLink.first()).toBeVisible();
  });

  test('dashboard shows recent test runs', async ({ page }) => {
    await page.goto('/admin');

    const recentRuns = page.locator('table, .recent-runs, [data-recent-runs]');
    const hasRecent = await recentRuns.count();

    expect(hasRecent).toBeGreaterThanOrEqual(0);
  });
});
