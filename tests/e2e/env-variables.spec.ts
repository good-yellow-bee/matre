import { test, expect } from '@playwright/test';

test.describe('env variables', () => {
  test('grid renders, search filters, sensitive values masked', async ({ page }) => {
    await page.goto('/admin/env-variables');
    await expect(page.locator('h1')).toHaveText('Global Env Variables');

    const rows = page.locator('.card tbody tr');
    await expect.poll(() => rows.count()).toBeGreaterThan(10);
    const totalRows = await rows.count();

    await page.getByPlaceholder('Search variables...').fill('MAGENTO_ADMIN_PASSWORD');
    await expect(rows.first().getByText('MAGENTO_ADMIN_PASSWORD').first()).toBeVisible();
    const filteredRows = await rows.count();
    expect(filteredRows).toBeGreaterThan(0);
    expect(filteredRows).toBeLessThan(totalRows);

    // *PASSWORD* values are masked
    await expect(page.locator('.card tbody').getByText('••••••••').first()).toBeVisible();
    await expect(page.locator('.card tbody').getByTitle('Reveal value').first()).toBeVisible();
  });
});
