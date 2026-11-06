import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';

test('an administrator can deactivate and reactivate the plugin', async ({ page }) => {
  await page.goto('/wp-admin/plugins.php');
  const row = page.locator('tr[data-plugin="wp-remote-users-sync/wprus.php"]');
  await expect(row).toHaveClass(/(^|\s)active(\s|$)/);
  await row.locator('.deactivate a').click();
  await expect(row).toHaveClass(/inactive/);
  await row.locator('.activate a').click();
  await expect(row).toHaveClass(/(^|\s)active(\s|$)/);
  await page.goto('/wp-admin/options-general.php?page=wprus');
  await expect(page.locator('.wprus-container[data-url="https://wprus-peer.test"]')).toHaveCount(1);
});
