import { test, expect } from '@playwright/test';

test.describe('Admin Navigation', () => {
  const adminPages = [
    { name: 'Test Runs', path: '/admin/test-runs' },
    { name: 'Test Suites', path: '/admin/test-suites' },
    { name: 'Test Environments', path: '/admin/test-environments' },
    { name: 'Users', path: '/admin/users' },
    { name: 'Cron Jobs', path: '/admin/cron-jobs' },
    { name: 'Env Variables', path: '/admin/env-variables' },
    { name: 'Audit Logs', path: '/admin/audit-logs' },
    { name: 'Settings', path: '/admin/settings' },
  ];

  for (const { name, path } of adminPages) {
    test(`${name} page loads at ${path}`, async ({ page }) => {
      const response = await page.goto(path);
      expect(response?.status()).toBe(200);
      await expect(page.locator('h1').first()).toBeVisible();
    });
  }

  test('new test run form loads', async ({ page }) => {
    const response = await page.goto('/admin/test-runs/new');
    expect(response?.status()).toBe(200);
  });

  test('new test suite form loads', async ({ page }) => {
    const response = await page.goto('/admin/test-suites/new');
    expect(response?.status()).toBe(200);
  });

  test('new test environment form loads', async ({ page }) => {
    const response = await page.goto('/admin/test-environments/new');
    expect(response?.status()).toBe(200);
  });

  test('new user form loads', async ({ page }) => {
    const response = await page.goto('/admin/users/new');
    expect(response?.status()).toBe(200);
  });
});
