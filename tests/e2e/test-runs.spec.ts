import { test, expect, type Page } from '@playwright/test';

// Specs are data-agnostic: locally the grid holds real runs; in CI a fixture seeds one failed run.

test.describe('test runs list', () => {
  test('renders failed rows with status badges', async ({ page }) => {
    await page.goto('/admin/test-runs');
    await expect(page.locator('h1')).toHaveText('Test Runs');

    const failedRow = page.locator('.card tbody tr').filter({ has: page.locator('.badge').filter({ hasText: 'failed' }) }).first();
    await expect(failedRow).toBeVisible();
  });

  test('filter by status=failed shows failed rows', async ({ page }) => {
    await page.goto('/admin/test-runs');
    await expect(page.locator('.card tbody tr').first()).toBeVisible();

    const filtered = page.waitForResponse(
      (response) => response.url().includes('/api/test-runs') && response.url().includes('status=failed'),
    );
    await page.locator('#filter-status').selectOption('failed');
    await filtered;

    const firstRow = page.locator('.card tbody tr').first();
    await expect(firstRow).toBeVisible();
    await expect(firstRow.locator('.badge').filter({ hasText: 'failed' })).toBeVisible();
  });

  test('row click navigates to run detail', async ({ page }) => {
    await page.goto('/admin/test-runs');
    const firstRow = page.locator('.card tbody tr').filter({ has: page.locator('a.mono-id') }).first();
    await firstRow.locator('td').nth(1).click();
    await expect(page).toHaveURL(/\/admin\/test-runs\/\d+$/);
    await expect(page.locator('h1')).toContainText(/Test Run #\d+/);
  });
});

test.describe('test run detail (latest failed run)', () => {
  // Navigate to the newest failed run via the list so the spec works on any dataset
  async function openLatestFailedRun(page: Page): Promise<void> {
    await page.goto('/admin/test-runs');
    const filtered = page.waitForResponse(
      (response) => response.url().includes('/api/test-runs') && response.url().includes('status=failed'),
    );
    await page.locator('#filter-status').selectOption('failed');
    await filtered;
    await page.locator('.card tbody tr').first().locator('td').nth(1).click();
    await expect(page.locator('h1')).toContainText(/Test Run #\d+/, { timeout: 15_000 });
  }

  test('shows failed badge and results summary counts', async ({ page }) => {
    await openLatestFailedRun(page);
    await expect(page.locator('.badge').filter({ hasText: 'failed' }).first()).toBeVisible();

    const summary = page.locator('section').filter({ hasText: 'Results Summary' });
    await expect(summary).toBeVisible();
    expect(Number(await summary.locator('.text-3xl.text-fail').textContent())).toBeGreaterThan(0);
  });

  test('shows output log content', async ({ page }) => {
    await openLatestFailedRun(page);
    const output = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Output Log' }) });
    await expect(output.locator('pre.ansi-log')).toContainText(/FAILURES!|failure|Fail/);
  });

  test('artifacts section renders screenshots when present', async ({ page }) => {
    await openLatestFailedRun(page);
    const artifacts = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Artifacts', exact: true }) });
    if (await artifacts.count() === 0) {
      test.skip(true, 'Run has no collected artifacts (fixture-seeded runs have none)');
    }
    await expect(artifacts.locator('img').first()).toBeVisible();
  });

  test('steps modal opens and closes when the run has results', async ({ page }) => {
    await openLatestFailedRun(page);
    // Gate on result rows, not the button itself — a missing Steps button on a run WITH results is a regression
    const resultRows = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Test Results' }) }).locator('tbody tr');
    if (await resultRows.count() === 0) {
      test.skip(true, 'Run has no test results');
    }
    const stepsButton = page.getByRole('button', { name: 'Steps' }).first();
    await expect(stepsButton).toBeVisible();
    await stepsButton.click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Close' }).click();
    await expect(dialog).not.toBeVisible();
  });
});
