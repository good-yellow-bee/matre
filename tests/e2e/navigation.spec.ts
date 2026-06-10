import { test, expect } from '@playwright/test';

const NAV_ITEMS = [
  { label: 'Test Runs', url: /\/admin\/test-runs$/, h1: 'Test Runs' },
  { label: 'Test History', url: /\/admin\/test-history$/, h1: 'Test History' },
  { label: 'Environments', url: /\/admin\/test-environments$/, h1: 'Test Environments' },
  { label: 'Test Suites', url: /\/admin\/test-suites$/, h1: 'Test Suites' },
  { label: 'Env Variables', url: /\/admin\/env-variables$/, h1: 'Global Env Variables' },
  { label: 'Users', url: /\/admin\/users$/, h1: 'Users' },
  { label: 'Notif. Templates', url: /\/admin\/notification-templates$/, h1: 'Notification Templates' },
  { label: 'Settings', url: /\/admin\/settings$/, h1: 'Settings' },
  { label: 'Cron Jobs', url: /\/admin\/cron-jobs$/, h1: 'Cron Jobs' },
  { label: 'Audit Logs', url: /\/admin\/audit-logs$/, h1: 'Audit Logs' },
  { label: 'Dashboard', url: /\/admin$/, h1: 'Welcome back' },
];

test('sidebar navigates to every section with correct heading', async ({ page }) => {
  await page.goto('/admin');
  await expect(page.locator('h1')).toContainText('Welcome back');

  const sidebar = page.locator('aside');
  for (const item of NAV_ITEMS) {
    await sidebar.getByRole('link', { name: item.label, exact: true }).click();
    await expect(page).toHaveURL(item.url);
    await expect(page.locator('h1')).toContainText(item.h1);
  }
});
