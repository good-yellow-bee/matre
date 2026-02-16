import { type Page, type Locator } from '@playwright/test';
import { BasePage } from './BasePage';

export class DashboardPage extends BasePage {
  readonly path = '/admin';

  readonly heading: Locator;
  readonly quickActions: Locator;
  readonly environmentStats: Locator;

  constructor(page: Page) {
    super(page);
    this.heading = page.locator('h1').first();
    this.quickActions = page.locator('[data-quick-actions], .quick-actions, a[href*="test-runs/new"]');
    this.environmentStats = page.locator('[data-vue-island="environment-stats"]');
  }
}
