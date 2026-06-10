import { test, expect } from '@playwright/test';

test.describe('environments', () => {
  test('grid shows 6 environments with credentials stripped from base URLs', async ({ page }) => {
    await page.goto('/admin/test-environments');
    await expect(page.locator('h1')).toHaveText('Test Environments');

    // Scope to the DataTable card — the Symfony dev toolbar injects its own hidden tables
    const baseUrlLinks = page.locator('.card tbody a[target="_blank"]');
    await expect(baseUrlLinks).toHaveCount(6);
    await expect(page.locator('.card tbody tr')).toHaveCount(6);

    for (const link of await baseUrlLinks.all()) {
      expect(await link.textContent()).not.toContain('@');
      expect(await link.getAttribute('href')).not.toContain('@');
    }
  });

  test('detail page renders config card and variables tables', async ({ page }) => {
    await page.goto('/admin/test-environments/3');
    await expect(page.locator('h1')).toHaveText('preprod-es');

    const config = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Environment Configuration' }) });
    await expect(config).toBeVisible();
    await expect(config.getByText('Base URL')).toBeVisible();
    const baseUrlLink = config.locator('a[target="_blank"]');
    await expect(baseUrlLink).toBeVisible();
    expect(await baseUrlLink.textContent()).not.toContain('@');

    const vars = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Environment Variables' }) });
    await expect(vars.getByRole('heading', { name: 'Inherited Global Variables' })).toBeVisible();
    await expect(vars.getByRole('heading', { name: 'Environment-Specific Variables' })).toBeVisible();
    expect(await vars.locator('table').first().locator('tbody tr').count()).toBeGreaterThan(10);
  });
});
