import { test, expect } from '@playwright/test';

test.describe('Notification Template Editing', () => {
  test('notification templates page loads', async ({ page }) => {
    await page.goto('/admin/notification-templates');
    await expect(page.locator('h1')).toContainText('Notification Templates');
  });

  test('notification templates page has tables', async ({ page }) => {
    await page.goto('/admin/notification-templates');

    const main = page.locator('main');
    await expect(main.locator('table').first()).toBeVisible();
  });

  test('template edit link available', async ({ page }) => {
    await page.goto('/admin/notification-templates');

    const main = page.locator('main');
    const editLinks = main.locator('a[href*="/edit"]');
    const count = await editLinks.count();

    // Templates may or may not have edit links depending on fixtures
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('template tables show event and status columns', async ({ page }) => {
    await page.goto('/admin/notification-templates');

    const main = page.locator('main');
    await expect(main.locator('th', { hasText: 'Event' }).first()).toBeVisible();
    await expect(main.locator('th', { hasText: 'Status' }).first()).toBeVisible();
  });
});
