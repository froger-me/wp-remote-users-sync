import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';
import { request as playwrightRequest } from '@playwright/test';
import fs from 'node:fs';
import { encryptMessage } from './support/protocol';

test('identity endpoint reports only username and email existence', async ({ baseURL }) => {
  if (!baseURL) throw new Error('The local WordPress address is missing.');
  const client = await playwrightRequest.newContext({ baseURL, storageState: { cookies: [], origins: [] }, ignoreHTTPSErrors: true });
  try {
    const tokenResponse = await client.post('/wprus/token/', {
      form: { wprusdata: encryptMessage({ action: 'identity', method: 'post', base_url: baseURL }) },
    });
    expect(tokenResponse.ok()).toBeTruthy();
    const { nonce } = await tokenResponse.json() as { nonce: string };
    const response = await client.post('/wprus/identity/', {
      form: {
        token: nonce,
        wprusdata: encryptMessage({
          username: 'wprus_test_alice',
          user_email: 'wprus-alice@example.test',
          base_url: 'https://wprus-peer.test',
        }),
      },
    });
    expect(response.ok()).toBeTruthy();
    expect(await response.json()).toEqual({ success: true, data: { username_exists: true, email_exists: true } });
  } finally {
    await client.dispose();
  }
});

test('a different email cannot change an existing account password', async ({ baseURL }) => {
  if (!baseURL || !process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE) throw new Error('Browser-test configuration is missing.');
  const secret = JSON.parse(fs.readFileSync(process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE, 'utf8')).token as string;
  const client = await playwrightRequest.newContext({ baseURL, storageState: { cookies: [], origins: [] }, ignoreHTTPSErrors: true });
  try {
    const tokenResponse = await client.post('/wprus/token/', {
      form: { wprusdata: encryptMessage({ action: 'password', method: 'post', base_url: baseURL }) },
    });
    expect(tokenResponse.ok()).toBeTruthy();
    const { nonce } = await tokenResponse.json() as { nonce: string };
    const response = await client.post('/wprus/password/', {
      form: {
        token: nonce,
        wprusdata: encryptMessage({
          username: 'wprus_test_alice',
          identity_email: 'different@example.test',
          user_pass: 'unwanted-password',
          base_url: 'https://wprus-peer.test',
        }),
      },
    });
    expect(response.status()).toBe(409);
    const passwordCheck = await client.post('/wp-admin/admin-ajax.php', {
      headers: { 'X-WPRUS-Test-Token': secret },
      form: { action: 'wprus_tests_password', username: 'wprus_test_alice', password: 'unwanted-password' },
    });
    expect(passwordCheck.ok()).toBeTruthy();
    expect((await passwordCheck.json() as { data: { matches: boolean } }).data.matches).toBe(false);
  } finally {
    await client.dispose();
  }
});

test('incoming Update changes the named user and keeps the login and role', async ({ baseURL }) => {
  if (!baseURL) throw new Error('The local WordPress address is missing.');
  const usersFile = process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE;
  if (!usersFile) throw new Error('The browser-test secret file is missing.');
  const secret = JSON.parse(fs.readFileSync(usersFile, 'utf8')).token as string;
  const client = await playwrightRequest.newContext({ baseURL, storageState: { cookies: [], origins: [] }, ignoreHTTPSErrors: true });
  try {
    const tokenResponse = await client.post('/wprus/token/', {
      form: { wprusdata: encryptMessage({ action: 'update', method: 'post', base_url: baseURL }) },
    });
    expect(tokenResponse.status()).toBe(200);
    const token = await tokenResponse.json() as { nonce: string; expiry: number };
    expect(typeof token.nonce).toBe('string');
    expect(token.nonce.length).toBeGreaterThan(0);
    expect(token.expiry).toBeGreaterThan(Math.floor(Date.now() / 1000));

    await client.post('/wprus/update/', {
      form: {
        token: token.nonce,
        wprusdata: encryptMessage({
          username: 'wprus_test_alice',
          identity_email: 'wprus-alice@example.test',
          user_email: 'wprus-alice@example.test',
          base_url: 'https://wprus-peer.test',
          display_name: 'Changed by connected site',
          role: 'administrator',
        }),
      },
    });
    const response = await client.get('/wp-json/wprus-tests/v1/user/wprus_test_alice', {
      headers: { 'X-WPRUS-Test-Token': secret },
    });
    expect(response.ok()).toBeTruthy();
    const user = await response.json() as { user_login: string; display_name: string; roles: string[] };
    expect(user.user_login).toBe('wprus_test_alice');
    expect(user.display_name).toBe('Changed by connected site');
    expect(user.roles).toEqual(['subscriber']);
  } finally {
    await client.dispose();
  }
});

test('the test helper rejects requests without the per-run secret', async ({ request }) => {
  const response = await request.get('/wp-json/wprus-tests/v1/user/wprus_test_alice');
  expect([401, 403]).toContain(response.status());
});

test('incoming Create cannot overwrite an existing account by ID', async ({ baseURL }) => {
  if (!baseURL || !process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE) throw new Error('Browser-test configuration is missing.');
  const secret = JSON.parse(fs.readFileSync(process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE, 'utf8')).token as string;
  const client = await playwrightRequest.newContext({ baseURL, storageState: { cookies: [], origins: [] }, ignoreHTTPSErrors: true });
  try {
    const readUser = async (username: string) => {
      const response = await client.get(`/wp-json/wprus-tests/v1/user/${username}`, {
        headers: { 'X-WPRUS-Test-Token': secret },
      });
      expect(response.ok()).toBeTruthy();
      return response.json() as Promise<{ ID: number; user_email: string; roles: string[] }>;
    };
    const before = await readUser('wprus_test_alice');
    const response = await client.post('/wprus/token/', {
      form: { wprusdata: encryptMessage({ action: 'create', method: 'post', base_url: baseURL }) },
    });
    expect(response.ok()).toBeTruthy();
    const token = await response.json() as { nonce: string };
    const username = `wprus_test_new_${Date.now()}`;
    await client.post('/wprus/create/', {
      form: {
        token: token.nonce,
        wprusdata: encryptMessage({
          username,
          identity_email: 'created@example.test',
          base_url: 'https://wprus-peer.test',
          ID: before.ID,
          user_email: 'created@example.test',
          role: 'administrator',
        }),
      },
    });
    expect(await readUser('wprus_test_alice')).toEqual(before);
    const created = await readUser(username);
    expect(created.ID).not.toBe(before.ID);
    expect(created.user_email).toBe('created@example.test');
    expect(created.roles).toEqual(['subscriber']);
  } finally {
    await client.dispose();
  }
});
