import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';
import { request as playwrightRequest } from '@playwright/test';
import fs from 'node:fs';
import { encryptMessage } from './support/protocol';

test('invalid tokens cannot change a user through any action', async ({ baseURL }) => {
  if (!baseURL || !process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE) throw new Error('Browser-test configuration is missing.');
  const secret = JSON.parse(fs.readFileSync(process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE, 'utf8')).token as string;
  const client = await playwrightRequest.newContext({ baseURL, storageState: { cookies: [], origins: [] }, ignoreHTTPSErrors: true });
  try {
    const readUser = async () => {
      const response = await client.get('/wp-json/wprus-tests/v1/user/wprus_test_alice', {
        headers: { 'X-WPRUS-Test-Token': secret },
      });
      expect(response.ok()).toBeTruthy();
      return response.json();
    };
    const before = await readUser();
    const data = encryptMessage({
      username: 'wprus_test_alice',
      base_url: 'https://wprus-peer.test',
      user_email: 'changed-without-token@example.test',
      user_pass: 'unexpected-password',
      role: 'administrator',
      remember: 0,
    });
    for (const action of ['create', 'update', 'delete', 'password', 'role', 'meta']) {
      await client.post(`/wprus/${action}/`, { form: { token: 'invalid-token', wprusdata: data } });
      expect(await readUser(), action).toEqual(before);
    }
    for (const action of ['login', 'logout']) {
      await client.get(`/wprus/${action}/`, { params: { token: 'invalid-token', wprusdata: data } });
      expect(await readUser(), action).toEqual(before);
    }
  } finally {
    await client.dispose();
  }
});

test('an administrator cannot clear logs using an invalid request value', async ({ request }) => {
  const response = await request.post('/wp-admin/admin-ajax.php', {
    form: { action: 'wprus_clear_logs', nonce: 'invalid' },
  });
  expect(response.status()).toBe(403);
  expect((await response.json() as { success: boolean }).success).toBe(false);
});
