import { test, expect } from '@playwright/test';
import { waitForVueHydration } from '../utils/waits';

test.describe('Test Suite CRUD', () => {
  test('test suites index page loads', async ({ page }) => {
    await page.goto('/admin/test-suites');
    await expect(page.locator('h1')).toContainText('Test Suites');
  });

  test('new suite button visible', async ({ page }) => {
    await page.goto('/admin/test-suites');
    const newBtn = page.getByRole('link', { name: 'Add Suite' });
    await expect(newBtn).toBeVisible();
  });

  test('new suite form loads with Vue island', async ({ page }) => {
    await page.goto('/admin/test-suites/new');

    await expect(page.locator('h1')).toHaveText('Add Test Suite');
    await expect(page.locator('[data-vue-island="test-suite-form"]')).toBeVisible();
  });

  test('new suite form Vue island hydrates', async ({ page }) => {
    await page.goto('/admin/test-suites/new');

    await page.waitForSelector('[data-vue-island="test-suite-form"][data-v-app]', { timeout: 10_000 });
  });

  test('mftf type selection fetches discovery list via GET', async ({ page }) => {
    await page.route('**/api/test-discovery*', async (route, request) => {
      const url = new URL(request.url());
      if (request.method() === 'GET' && url.pathname === '/api/test-discovery') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            type: url.searchParams.get('type'),
            items: [{ value: 'MOEC7219', label: 'MOEC7219' }],
            cached: true,
            lastUpdated: '2026-01-01T00:00:00Z',
          }),
        });
        return;
      }

      await route.continue();
    });

    await page.goto('/admin/test-suites/new');
    await waitForVueHydration(page);
    await page.waitForSelector('#suite_type option[value="mftf_test"]');

    const discoveryRequestPromise = page.waitForRequest((request) => {
      const url = new URL(request.url());
      return request.method() === 'GET'
        && url.pathname === '/api/test-discovery'
        && url.searchParams.get('type') === 'mftf_test';
    });

    await page.selectOption('#suite_type', 'mftf_test');
    await discoveryRequestPromise;
  });

  test('refresh button posts to refresh endpoint and reloads discovery list', async ({ page }) => {
    let sawRefreshPost = false;
    let getRequestsAfterRefresh = 0;

    await page.route('**/api/test-discovery*', async (route, request) => {
      const url = new URL(request.url());

      if (request.method() === 'POST' && url.pathname === '/api/test-discovery/refresh') {
        sawRefreshPost = true;
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            message: 'Cache refreshed successfully',
            lastUpdated: '2026-01-01T00:00:00Z',
          }),
        });
        return;
      }

      if (request.method() === 'GET' && url.pathname === '/api/test-discovery') {
        if (sawRefreshPost) {
          getRequestsAfterRefresh += 1;
        }
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            type: url.searchParams.get('type'),
            items: [{ value: 'MOEC7219', label: 'MOEC7219' }],
            cached: true,
            lastUpdated: '2026-01-01T00:00:00Z',
          }),
        });
        return;
      }

      await route.continue();
    });

    await page.goto('/admin/test-suites/new');
    await waitForVueHydration(page);
    await page.waitForSelector('#suite_type option[value="mftf_test"]');

    const initialDiscoveryRequestPromise = page.waitForRequest((request) => {
      const url = new URL(request.url());
      return request.method() === 'GET'
        && url.pathname === '/api/test-discovery'
        && url.searchParams.get('type') === 'mftf_test';
    });

    await page.selectOption('#suite_type', 'mftf_test');
    await initialDiscoveryRequestPromise;

    const refreshRequestPromise = page.waitForRequest((request) => {
      const url = new URL(request.url());
      return request.method() === 'POST' && url.pathname === '/api/test-discovery/refresh';
    });

    await page.locator('button[title="Refresh test list from repository"]').click();

    const refreshRequest = await refreshRequestPromise;
    expect(refreshRequest.headers()['x-csrf-token']).toBeTruthy();
    await expect.poll(() => getRequestsAfterRefresh).toBeGreaterThan(0);
  });

  test('suites table has expected columns', async ({ page }) => {
    await page.goto('/admin/test-suites');

    const main = page.locator('main');
    await expect(main.locator('th', { hasText: 'Name' })).toBeVisible();
    await expect(main.locator('th', { hasText: 'Type' })).toBeVisible();
    await expect(main.locator('th', { hasText: 'Test Pattern' })).toBeVisible();
  });
});
