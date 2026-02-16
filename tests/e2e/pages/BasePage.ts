import { type Page, type Locator, expect } from '@playwright/test';
import { waitForVueHydration, waitForPageReady } from '../utils/waits';

export abstract class BasePage {
  constructor(protected readonly page: Page) {}

  abstract readonly path: string;

  async goto(): Promise<void> {
    await this.page.goto(this.path);
    await waitForPageReady(this.page);
  }

  async waitForVue(): Promise<void> {
    await waitForVueHydration(this.page);
  }

  /** Get the page title text. */
  async getHeading(): Promise<string> {
    return this.page.locator('h1').first().innerText();
  }

  /** Get a flash message alert. */
  getFlash(type: 'success' | 'danger' | 'warning' | 'info' = 'success'): Locator {
    return this.page.locator(`.alert-${type}`);
  }

  /** Assert current URL matches expected path. */
  async assertUrl(expected: string): Promise<void> {
    await expect(this.page).toHaveURL(new RegExp(expected));
  }
}
