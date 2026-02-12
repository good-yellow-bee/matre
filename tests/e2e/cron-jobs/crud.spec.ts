import { test, expect } from '@playwright/test';

test.describe('Cron Jobs CRUD', () => {
  test('cron jobs page loads', async ({ page }) => {
    await page.goto('/admin/cron-jobs');
    await expect(page.locator('h1')).toContainText('Cron Jobs');
  });

  test('cron jobs Vue grid hydrates', async ({ page }) => {
    await page.goto('/admin/cron-jobs');

    await page.waitForSelector('[data-vue-island="cron-job-grid"][data-v-app]', { timeout: 10_000 });
  });

  test('new cron job button visible', async ({ page }) => {
    await page.goto('/admin/cron-jobs');
    const newBtn = page.getByRole('link', { name: 'Create New' });
    await expect(newBtn).toBeVisible();
  });

  test('new cron job form loads with Vue island', async ({ page }) => {
    await page.goto('/admin/cron-jobs/new');

    await expect(page.locator('h1')).toHaveText('Create New Cron Job');
    await expect(page.locator('[data-vue-island="cron-job-form"]')).toBeVisible();
  });

  test('new cron job form Vue island hydrates', async ({ page }) => {
    await page.goto('/admin/cron-jobs/new');

    await page.waitForSelector('[data-vue-island="cron-job-form"][data-v-app]', { timeout: 10_000 });
  });
});
