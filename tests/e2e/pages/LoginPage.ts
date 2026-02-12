import { type Page, type Locator } from '@playwright/test';
import { BasePage } from './BasePage';

export class LoginPage extends BasePage {
  readonly path = '/login';

  readonly usernameInput: Locator;
  readonly passwordInput: Locator;
  readonly submitButton: Locator;
  readonly errorAlert: Locator;
  readonly rememberMe: Locator;

  constructor(page: Page) {
    super(page);
    this.usernameInput = page.locator('#username');
    this.passwordInput = page.locator('#password');
    this.submitButton = page.locator('button[type="submit"]');
    this.errorAlert = page.locator('.alert-danger');
    this.rememberMe = page.locator('#remember_me');
  }

  async login(username: string, password: string): Promise<void> {
    await this.usernameInput.fill(username);
    await this.passwordInput.fill(password);
    await this.submitButton.click();
  }
}
