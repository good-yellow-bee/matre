import { test, expect } from '@playwright/test';

test.describe('users', () => {
  test('grid shows admin row without self-modify actions', async ({ page }) => {
    await page.goto('/admin/users');
    await expect(page.locator('h1')).toHaveText('Users');

    const adminRow = page.locator('.card tbody tr').filter({ has: page.getByRole('link', { name: 'admin', exact: true }) });
    await expect(adminRow).toBeVisible();
    await expect(adminRow.getByText('ADMIN', { exact: true })).toBeVisible();

    // Self-guard: edit is allowed, toggle/delete are hidden for own account
    await expect(adminRow.locator('[title="Edit user"]')).toBeVisible();
    await expect(
      adminRow.locator('[title="Deactivate user"], [title="Activate user"], [title="Delete user"]'),
    ).toHaveCount(0);
  });

  test('detail page renders with self-guard note', async ({ page }) => {
    await page.goto('/admin/users/1');
    await expect(page.locator('h1')).toContainText('admin');
    await expect(page.getByRole('heading', { name: 'User Details' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Two-Factor Authentication' })).toBeVisible();
    await expect(page.getByText('You cannot modify or delete your own account.')).toBeVisible();
  });
});
