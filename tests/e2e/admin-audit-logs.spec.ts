import { test, expect } from '@playwright/test';

test.describe('Audit Logs', () => {
  test('audit logs index page loads with Vue grid', async ({ page }) => {
    await page.goto('/admin/audit-logs');

    await expect(page.locator('h1')).toHaveText('Admin Changes History');
    await expect(page.locator('[data-vue-island="audit-log-grid"]')).toBeVisible();
  });

  test('audit logs grid hydrates', async ({ page }) => {
    await page.goto('/admin/audit-logs');

    await page.waitForSelector('[data-vue-island="audit-log-grid"][data-v-app]', { timeout: 10_000 });
  });

  test('audit logs page has API URLs configured', async ({ page }) => {
    await page.goto('/admin/audit-logs');

    const grid = page.locator('[data-vue-island="audit-log-grid"]');
    const apiUrl = await grid.getAttribute('data-api-url');
    const filtersUrl = await grid.getAttribute('data-filters-url');
    expect(apiUrl).toContain('/api/audit-logs');
    expect(filtersUrl).toContain('/api/audit-logs/filters');
  });
});
