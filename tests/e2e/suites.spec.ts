import { test, expect } from '@playwright/test';

test.describe('test suites', () => {
  test('list renders grid or empty state', async ({ page }) => {
    await page.goto('/admin/test-suites');
    await expect(page.locator('h1')).toHaveText('Test Suites');
    await expect(
      page.getByText('No test suites configured').or(page.locator('.card tbody a.link').first()),
    ).toBeVisible();
  });

  test("'Add Test Suite' opens the create form", async ({ page }) => {
    await page.goto('/admin/test-suites');
    await page.getByRole('link', { name: 'Add Test Suite' }).first().click();

    await expect(page).toHaveURL(/\/admin\/test-suites\/new$/);
    await expect(page.locator('h1')).toHaveText('Add Test Suite');
    await expect(page.locator('#suite-name')).toBeVisible();
    await expect(page.locator('#suite-type')).toBeVisible();
    await expect(page.locator('#suite-cron')).toBeVisible();
  });
});
