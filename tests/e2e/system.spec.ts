import { test, expect } from '@playwright/test';

test.describe('cron jobs', () => {
  test('page renders grid or empty state', async ({ page }) => {
    await page.goto('/admin/cron-jobs');
    await expect(page.locator('h1')).toHaveText('Cron Jobs');
    await expect(
      page.getByText('No cron jobs yet').or(page.locator('.card tbody a.link').first()),
    ).toBeVisible();
  });
});

test.describe('notification templates', () => {
  test('Slack and Email cards show 4 template rows each', async ({ page }) => {
    await page.goto('/admin/notification-templates');
    await expect(page.locator('h1')).toHaveText('Notification Templates');

    const slack = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Slack Templates' }) });
    await expect(slack.locator('tbody tr')).toHaveCount(4);

    const email = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Email Templates' }) });
    await expect(email.locator('tbody tr')).toHaveCount(4);
  });
});

test.describe('audit logs', () => {
  test('page renders rows and filters', async ({ page }) => {
    await page.goto('/admin/audit-logs');
    await expect(page.locator('h1')).toHaveText('Audit Logs');

    for (const id of ['#filter-entity-type', '#filter-action', '#filter-user', '#filter-date-from', '#filter-date-to']) {
      await expect(page.locator(id)).toBeVisible();
    }
    await expect(page.getByPlaceholder('Search by entity label...')).toBeVisible();

    // Wait for real data rows (skeleton rows carry no badges)
    await expect(page.locator('.card tbody .badge').first()).toBeVisible();
    expect(await page.locator('.card tbody tr').count()).toBeGreaterThan(0);
  });
});
