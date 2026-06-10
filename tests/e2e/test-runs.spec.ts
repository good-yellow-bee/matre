import { test, expect } from '@playwright/test';

test.describe('test runs list', () => {
  test('renders rows and run #416 shows failed badge', async ({ page }) => {
    await page.goto('/admin/test-runs');
    await expect(page.locator('h1')).toHaveText('Test Runs');

    const row416 = page.locator('.card tbody tr').filter({ has: page.getByRole('link', { name: '#416', exact: true }) });
    await expect(row416).toBeVisible();
    await expect(row416.locator('.badge').filter({ hasText: 'failed' })).toBeVisible();
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

test.describe('test run detail (run #416)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/admin/test-runs/416');
    await expect(page.locator('h1')).toHaveText('Test Run #416', { timeout: 15_000 });
  });

  test('shows failed badge and results summary counts', async ({ page }) => {
    await expect(page.locator('.badge').filter({ hasText: 'failed' }).first()).toBeVisible();

    const summary = page.locator('section').filter({ hasText: 'Results Summary' });
    await expect(summary).toBeVisible();
    await expect(summary.locator('.text-3xl.text-fail')).toHaveText('1');
    await expect(summary.locator('.text-3xl.text-pass')).toHaveText('0');
  });

  test('shows output log content', async ({ page }) => {
    const output = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Output Log' }) });
    await expect(output.locator('pre.ansi-log')).toContainText('Generate Tests Command Run');
  });

  test('shows artifacts with screenshot thumbnail', async ({ page }) => {
    const artifacts = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Artifacts', exact: true }) });
    await expect(artifacts.getByText('Screenshots (1)')).toBeVisible();
    await expect(artifacts.locator('img').first()).toBeVisible();
    await expect(artifacts.locator('figcaption').first()).toContainText('.fail.png');
  });

  test('steps modal opens and closes', async ({ page }) => {
    await page.getByRole('button', { name: 'Steps' }).first().click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText('MOEC13447Cest:Moec13447');
    await expect(dialog.getByText(/top-level steps/)).toBeVisible();

    await dialog.getByRole('button', { name: 'Close' }).click();
    await expect(dialog).not.toBeVisible();
  });
});
