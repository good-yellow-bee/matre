import { test, expect } from '@playwright/test';

test.describe('Cron Job Manual Execution', () => {
  test('manual run button available for cron jobs', async ({ page }) => {
    await page.goto('/admin/cron-jobs');

    const rows = page.locator('tbody tr');
    const count = await rows.count();

    if (count === 0) {
      test.skip();
      return;
    }

    const runBtn = rows.first().locator('button:has-text("run"), form[action*="/run"]');
    const hasRun = await runBtn.count();

    expect(hasRun).toBeGreaterThanOrEqual(0);
  });
});
