import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';
import { request as playwrightRequest } from '@playwright/test';
import fs from 'node:fs';
import { encryptMessage } from './support/protocol';

test('a connected site can create, change, and delete an allowed user', async ({ baseURL }) => {
  const usersFile = process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE;
  if (!baseURL || !usersFile) throw new Error('The browser-test configuration is missing.');
  const secret = JSON.parse(fs.readFileSync(usersFile, 'utf8')).token as string;
  const client = await playwrightRequest.newContext({ baseURL, storageState: { cookies: [], origins: [] }, ignoreHTTPSErrors: true });
  const username = `wprus_test_features_${Date.now()}`;
  try {
    const send = async (action: string, data: Record<string, unknown>) => {
      const response = await client.post('/wprus/token/', {
        form: { wprusdata: encryptMessage({ action, method: 'post', base_url: baseURL }) },
      });
      expect(response.ok()).toBeTruthy();
      const { nonce } = await response.json() as { nonce: string };
      await client.post(`/wprus/${action}/`, {
        form: { token: nonce, wprusdata: encryptMessage({ username, base_url: 'https://wprus-peer.test', ...data }) },
      });
    };
    const readUser = async () => {
      const response = await client.get(`/wp-json/wprus-tests/v1/user/${username}`, {
        headers: { 'X-WPRUS-Test-Token': secret },
      });
      expect(response.ok()).toBeTruthy();
      return response.json() as Promise<{ user_email: string; roles: string[]; test_note: string }>;
    };
    const passwordMatches = async (password: string) => {
      const response = await client.post('/wp-admin/admin-ajax.php', {
        headers: { 'X-WPRUS-Test-Token': secret },
        form: { action: 'wprus_tests_password', username, password },
      });
      expect(response.ok()).toBeTruthy();
      return (await response.json() as { data: { matches: boolean } }).data.matches;
    };

    await send('create', { user_email: 'features@example.test', user_pass: 'first-password' });
    expect((await readUser()).user_email).toBe('features@example.test');
    expect(await passwordMatches('first-password')).toBe(true);

    await send('update', { roles: { set: 'editor', add: [] } });
    expect((await readUser()).roles).toEqual(['editor']);

    await send('password', { user_pass: 'second-password' });
    expect(await passwordMatches('second-password')).toBe(true);
    expect(await passwordMatches('first-password')).toBe(false);

    await send('meta', { add: { test_note: [{ meta_value: 'first', unique: true }] } });
    expect((await readUser()).test_note).toBe('first');
    await send('meta', { update: { test_note: [{ meta_value: 'second', prev_value: 'first' }] } });
    expect((await readUser()).test_note).toBe('second');
    await send('meta', { delete: { test_note: [{ meta_value: 'second', delete_all: false }] } });
    expect((await readUser()).test_note).toBe('');

    await send('delete', { reassign_username: false });
    const deleted = await client.get(`/wp-json/wprus-tests/v1/user/${username}`, {
      headers: { 'X-WPRUS-Test-Token': secret },
    });
    expect(deleted.status()).toBe(404);
  } finally {
    await client.dispose();
  }
});
