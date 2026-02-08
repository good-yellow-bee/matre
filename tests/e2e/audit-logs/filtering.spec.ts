import { test, expect } from '@playwright/test';

test.describe('Audit Logs Filtering', () => {
  test('audit logs page loads', async ({ page }) => {
    await page.goto('/admin/audit-logs');
    await expect(page.locator('h1')).toContainText('Admin Changes History');
  });

  test('audit logs Vue grid hydrates', async ({ page }) => {
    await page.goto('/admin/audit-logs');

    await page.waitForSelector('[data-vue-island="audit-log-grid"][data-v-app]', { timeout: 10_000 });
  });

  test('audit logs grid has filter url', async ({ page }) => {
    await page.goto('/admin/audit-logs');

    const grid = page.locator('[data-vue-island="audit-log-grid"]');
    await expect(grid).toHaveAttribute('data-filters-url', /\/api\/audit-logs\/filters/);
  });

  test('audit logs grid shows content', async ({ page }) => {
    await page.goto('/admin/audit-logs');

    await page.waitForSelector('[data-vue-island="audit-log-grid"][data-v-app]', { timeout: 10_000 });

    const grid = page.locator('[data-vue-island="audit-log-grid"]');
    const gridText = await grid.textContent();
    expect(gridText?.length).toBeGreaterThan(0);
  });
});
