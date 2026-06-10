import { test, expect } from '@playwright/test';

test.describe('dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/admin');
    await expect(page.locator('h1')).toContainText('Welcome back');
  });

  test('stat cards render with numeric values', async ({ page }) => {
    const statsSection = page.locator('section').first();
    const values = statsSection.locator('.font-mono.text-2xl');
    await expect(values).toHaveCount(7);
    for (const text of await values.allTextContents()) {
      expect(text).toMatch(/\d/);
    }
    await expect(statsSection.getByText('Running Now')).toBeVisible();
  });

  test('environment health section renders environment cards', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Environment Health' })).toBeVisible();
    // Data-agnostic: an environment card (status badge or no-runs placeholder) or the empty state
    await expect
      .poll(async () =>
        (await page.getByText('No completed runs').count())
        + (await page.locator('.badge').count())
        + (await page.getByText('No environments yet').count()))
      .toBeGreaterThan(0);
  });

  test('quick action navigates to test runs list', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Quick Actions' })).toBeVisible();
    await page.getByRole('link', { name: 'View All Runs' }).click();
    await expect(page).toHaveURL(/\/admin\/test-runs$/);
    await expect(page.locator('h1')).toHaveText('Test Runs');
  });
});
