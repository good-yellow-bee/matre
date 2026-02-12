import { type Page } from '@playwright/test';

/** Wait for all Vue islands on the page to mount. */
export async function waitForVueHydration(page: Page, timeout = 10_000): Promise<void> {
  await page.waitForFunction(
    () => {
      const islands = document.querySelectorAll('[data-vue-island]');
      if (islands.length === 0) return true;
      return Array.from(islands).every((el) => el.hasAttribute('data-v-app'));
    },
    { timeout },
  );
}

/** Wait for a specific API response matching the URL pattern. */
export async function waitForApiResponse(
  page: Page,
  urlPattern: string | RegExp,
  options: { status?: number; timeout?: number } = {},
): Promise<void> {
  const { status = 200, timeout = 15_000 } = options;
  await page.waitForResponse(
    (resp) =>
      (typeof urlPattern === 'string'
        ? resp.url().includes(urlPattern)
        : urlPattern.test(resp.url())) && resp.status() === status,
    { timeout },
  );
}

/** Wait for page to be fully loaded (network idle + Vue hydrated). */
export async function waitForPageReady(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle');
  await waitForVueHydration(page);
}
