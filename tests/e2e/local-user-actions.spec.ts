import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';
import fs from 'node:fs';

/** Enable the test peer's user actions for the duration of one browser test. */
async function withUserActions(page: import('@playwright/test').Page, callback: (secret: string) => Promise<void>) {
  const usersFile = process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE;
  if (!usersFile) throw new Error('The browser-test secret file is missing.');
  const secret = JSON.parse(fs.readFileSync(usersFile, 'utf8')).token as string;
  const configure = async (enabled: boolean) => {
    const response = await page.request.post('/wp-admin/admin-ajax.php', {
      headers: { 'X-WPRUS-Test-Token': secret },
      form: { action: 'wprus_tests_peer_user_actions', enabled: enabled ? '1' : '0' },
    });
    expect(response.ok()).toBeTruthy();
  };
  await configure(true);
  try {
    await callback(secret);
  } finally {
    await configure(false);
  }
}

/** Create a disposable WordPress user through the administrator screen. */
async function createUser(page: import('@playwright/test').Page, secret: string, username: string) {
  await page.goto('/wp-admin/user-new.php');
  await page.locator('#user_login').fill(username);
  await page.locator('#email').fill(`${username}@example.test`);
  const notify = page.locator('#send_user_notification');
  if (await notify.isChecked()) await notify.uncheck();
  await page.locator('#createusersub').click();
  await expect(page.locator('#message')).toContainText(/user|created/i);
  const response = await page.request.get(`/wp-json/wprus-tests/v1/user/${username}`, { headers: { 'X-WPRUS-Test-Token': secret } });
  expect(response.ok()).toBeTruthy();
  return (await response.json() as { ID: number }).ID;
}

/** Read users held only by the simulated connected site. */
async function peerUsers(page: import('@playwright/test').Page, secret: string) {
  const response = await page.request.get('/wp-json/wprus-tests/v1/peer', { headers: { 'X-WPRUS-Test-Token': secret } });
  expect(response.ok()).toBeTruthy();
  return (await response.json() as { users: Record<string, Record<string, unknown>> }).users;
}

test('editing a local user sends profile, role, password, and selected metadata', async ({ page }) => {
  await withUserActions(page, async secret => {
    const username = `wprus_test_edit_${Date.now()}`;
    const id = await createUser(page, secret, username);
    await page.goto(`/wp-admin/user-edit.php?user_id=${id}`);
    await page.locator('#first_name').fill('Updated first name');
    await page.locator('#email').fill('updated-profile@example.test');
    await page.locator('#role').selectOption('editor');
    await page.locator('#wprus-test-note').fill('Selected extra information');
    await page.getByRole('button', { name: 'Set New Password' }).click();
    await page.locator('#pass1').fill('Strong-Test-Password-1843!');
    await page.locator('#submit').click();
    await expect(page.locator('#message')).toContainText(/updated/i);
    await expect.poll(async () => (await peerUsers(page, secret))[username]?.user_email).toBe('updated-profile@example.test');
    const user = (await peerUsers(page, secret))[username];
    expect(user.first_name).toBe('Updated first name');
    expect(user.roles).toEqual({ set: 'editor', add: false });
    expect(user.user_pass).toBe('Strong-Test-Password-1843!');
    expect(user.meta).toEqual({ test_note: ['Selected extra information'] });
  });
});

test('deleting a local user sends a deletion to the simulated site', async ({ page }) => {
  await withUserActions(page, async secret => {
    const username = `wprus_test_delete_${Date.now()}`;
    const id = await createUser(page, secret, username);
    expect((await peerUsers(page, secret))[username]).toBeTruthy();
    await page.goto(`/wp-admin/users.php?s=${username}`);
    const row = page.locator(`#user-${id}`);
    await row.hover();
    await row.locator('.delete a').click();
    await page.locator('#submit').click();
    await expect.poll(async () => (await peerUsers(page, secret))[username] ?? null).toBeNull();
  });
});
