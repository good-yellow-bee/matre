import { test, expect } from '@playwright/test';

test.describe('User Toggle Active', () => {
  test('users grid hydrates', async ({ page }) => {
    await page.goto('/admin/users');

    await page.waitForSelector('[data-vue-island="customers-grid"][data-v-app]', { timeout: 10_000 });
  });

  test('users page has grid with content', async ({ page }) => {
    await page.goto('/admin/users');

    await page.waitForSelector('[data-vue-island="customers-grid"][data-v-app]', { timeout: 10_000 });

    const grid = page.locator('[data-vue-island="customers-grid"]');
    const gridText = await grid.textContent();
    expect(gridText?.length).toBeGreaterThan(0);
  });
});
