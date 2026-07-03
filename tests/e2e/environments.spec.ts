import { test, expect } from '@playwright/test';

test.describe('environments', () => {
  test('grid shows environments with credentials stripped from base URLs', async ({ page }) => {
    await page.goto('/admin/test-environments');
    await expect(page.locator('h1')).toHaveText('Test Environments');

    // Scope to the DataTable card — the Symfony dev toolbar injects its own hidden tables.
    // Data-agnostic: at least one row (CI fixtures seed 2; local datasets vary).
    const rows = page.locator('.card tbody tr');
    await expect(rows.first()).toBeVisible();

    const baseUrlLinks = page.locator('.card tbody a[target="_blank"]');
    await expect(baseUrlLinks.first()).toBeVisible();
    for (const link of await baseUrlLinks.all()) {
      expect(await link.textContent()).not.toContain('@');
      expect(await link.getAttribute('href')).not.toContain('@');
    }
  });

  test('detail page renders config card and variables tables', async ({ page }) => {
    // Navigate via the grid so the spec works with any seeded environment set
    await page.goto('/admin/test-environments');
    const firstName = page.locator('.card tbody tr').first().locator('a').first();
    const envName = (await firstName.textContent())?.trim() ?? '';
    await firstName.click();
    await expect(page.locator('h1')).toHaveText(envName);

    const config = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Environment Configuration' }) });
    await expect(config).toBeVisible();
    await expect(config.getByText('Base URL')).toBeVisible();
    const baseUrlLink = config.locator('a[target="_blank"]');
    await expect(baseUrlLink).toBeVisible();
    expect(await baseUrlLink.textContent()).not.toContain('@');

    const vars = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Environment Variables' }) });
    // 'Environment-Specific Variables' always renders; the inherited-globals block only when globals exist
    await expect(vars.getByRole('heading', { name: 'Environment-Specific Variables' })).toBeVisible();
  });
});
