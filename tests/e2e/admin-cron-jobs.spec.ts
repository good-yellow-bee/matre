import { test, expect } from '@playwright/test';

test.describe('Cron Jobs Management', () => {
  test('cron jobs index page loads with Vue grid', async ({ page }) => {
    await page.goto('/admin/cron-jobs');

    await expect(page.locator('h1')).toHaveText('Cron Jobs');
    await expect(page.locator('[data-vue-island="cron-job-grid"]')).toBeVisible();
  });

  test('cron jobs grid hydrates', async ({ page }) => {
    await page.goto('/admin/cron-jobs');

    await page.waitForSelector('[data-vue-island="cron-job-grid"][data-v-app]', { timeout: 10_000 });
  });

  test('cron jobs has create button', async ({ page }) => {
    await page.goto('/admin/cron-jobs');

    const addBtn = page.getByRole('link', { name: 'Create New' });
    await expect(addBtn).toBeVisible();
  });

  test('new cron job form loads with Vue island', async ({ page }) => {
    await page.goto('/admin/cron-jobs/new');

    await expect(page.locator('h1')).toHaveText('Create New Cron Job');
    await expect(page.locator('[data-vue-island="cron-job-form"]')).toBeVisible();
  });
});
