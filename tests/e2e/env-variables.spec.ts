import { test, expect } from '@playwright/test';

test.describe('env variables', () => {
  test('grid renders, add + search + sensitive masking + delete', async ({ page }) => {
    await page.goto('/admin/env-variables');
    await expect(page.locator('h1')).toHaveText('Global Env Variables');

    // Self-seed a sensitive variable so the spec is data-independent (CI runs on fixtures only)
    await page.getByRole('button', { name: 'Add Variable' }).click();
    await page.getByPlaceholder('VARIABLE_NAME').fill('E2E_PROBE_PASSWORD');
    await page.getByPlaceholder('value', { exact: true }).fill('e2e-secret-value');
    await page.getByRole('button', { name: 'Save', exact: true }).click();

    const rows = page.locator('.card tbody tr');
    await expect(page.locator('.card tbody').getByText('E2E_PROBE_PASSWORD').first()).toBeVisible();

    // Search filters to it
    await page.getByPlaceholder('Search variables...').fill('E2E_PROBE_PASSWORD');
    await expect(rows.filter({ hasText: 'E2E_PROBE_PASSWORD' }).first()).toBeVisible();

    // *PASSWORD* value is masked with a reveal toggle
    await expect(page.locator('.card tbody').getByText('••••••••').first()).toBeVisible();
    await expect(page.locator('.card tbody').getByTitle('Reveal value').first()).toBeVisible();

    // Clean up through the danger dialog (loop in case earlier failed runs left duplicates)
    while (await rows.filter({ hasText: 'E2E_PROBE_PASSWORD' }).count() > 0) {
      await rows.filter({ hasText: 'E2E_PROBE_PASSWORD' }).first().getByTitle('Delete variable').click();
      await page.getByRole('button', { name: 'Delete', exact: true }).click();
      await expect(page.getByRole('button', { name: 'Delete', exact: true })).toBeHidden();
    }
    await expect(page.locator('.card tbody').getByText('E2E_PROBE_PASSWORD')).toHaveCount(0);
  });
});
