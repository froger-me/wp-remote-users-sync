import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';
import fs from 'node:fs';

test('creating a WordPress user sends the profile to the simulated site', async ({ page, request }) => {
  const usersFile = process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE;
  if (!usersFile) throw new Error('The browser-test secret file is missing.');
  const secret = JSON.parse(fs.readFileSync(usersFile, 'utf8')).token as string;

  await page.goto('/wp-admin/user-new.php');
  await page.locator('#user_login').fill('wprus_test_remote');
  await page.locator('#email').fill('wprus-remote@example.test');
  const notify = page.locator('#send_user_notification');
  if (await notify.isChecked()) await notify.uncheck();
  await expect(page.locator('#pass1')).toHaveValue(/\S/);
  await page.locator('#createusersub').click();
  await expect(page.locator('#message')).toContainText(/user|created/i);

  const response = await request.get('/wp-json/wprus-tests/v1/peer', {
    headers: { 'X-WPRUS-Test-Token': secret },
  });
  expect(response.ok()).toBeTruthy();
  const peer = await response.json() as {
    users: Record<string, { user_email: string }>;
    requests: Array<{ path: string }>;
  };
  expect(peer.users.wprus_test_remote.user_email).toBe('wprus-remote@example.test');
  expect(peer.requests.map(item => item.path)).toContain('/wprus/token/');
  expect(peer.requests.map(item => item.path)).toContain('/wprus/create/');
});
