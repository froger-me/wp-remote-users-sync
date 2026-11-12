import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';
import fs from 'node:fs/promises';
import path from 'node:path';

test('registration shows an error when a username exists on the connected site', async ({ page, baseURL }) => {
  if (!baseURL || !process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE) {
    throw new Error('The browser-test site or secret file is missing.');
  }

  const username = 'wprus_test_dup';
  const email = 'new@example.test';
  const secret = JSON.parse(await fs.readFile(process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE, 'utf8')).token as string;
  const screenshotDir = process.env.WPRUS_SCREENSHOT_DIR;
  const screenshot = async (name: string) => {
    if (!screenshotDir) return;
    await fs.mkdir(screenshotDir, { recursive: true });
    await page.screenshot({ path: path.join(screenshotDir, name), fullPage: true });
  };

  // Public registration is enabled only during this browser test run.
  await page.goto('/wp-admin/options-general.php');
  await page.locator('input[name="users_can_register"]').check();
  await page.locator('#submit').click();
  await expect(page.locator('input[name="users_can_register"]')).toBeChecked();

  const localUser = () => page.request.get(`/wp-json/wprus-tests/v1/user/${username}`, {
    headers: { 'X-WPRUS-Test-Token': secret },
  });
  expect((await localUser()).status()).toBe(404);

  await page.context().clearCookies();
  await page.goto('/wp-login.php?action=register');
  await expect(page.locator('#registerform')).toBeVisible();
  await screenshot('01-registration-form.png');

  await page.locator('#user_login').fill(username);
  await page.locator('#user_email').fill(email);
  await screenshot('02-registration-filled.png');

  await page.locator('#wp-submit').click();
  await expect(page.locator('#login_error')).toContainText('This username is already in use. Please choose another.');
  expect((await localUser()).status()).toBe(404);
  await screenshot('03-registration-error.png');
});
