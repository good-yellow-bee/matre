import { test, expect } from '@playwright/test';

test.describe('Test Run Live Output', () => {
  test('test runs index returns 200', async ({ page }) => {
    const response = await page.goto('/admin/test-runs');
    expect(response?.status()).toBe(200);
  });

  test('test run new form returns 200', async ({ page }) => {
    const response = await page.goto('/admin/test-runs/new');
    expect(response?.status()).toBe(200);
  });

  test('non-existent test run does not return 500', async ({ page }) => {
    const response = await page.goto('/admin/test-runs/99998');
    expect(response?.status()).toBeLessThan(500);
  });
});
