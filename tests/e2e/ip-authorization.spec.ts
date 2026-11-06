import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';
import { request as playwrightRequest } from '@playwright/test';
import fs from 'node:fs';
import { encryptMessage } from './support/protocol';

test('incoming requests follow the configured allowed IP addresses', async ({ page, baseURL }) => {
  const usersFile = process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE;
  if (!baseURL || !usersFile) throw new Error('The browser-test configuration is missing.');
  const secret = JSON.parse(fs.readFileSync(usersFile, 'utf8')).token as string;
  const client = await playwrightRequest.newContext({ baseURL, storageState: { cookies: [], origins: [] }, ignoreHTTPSErrors: true });
  const settings = '/wp-admin/options-general.php?page=wprus';
  const setAllowed = async (value: string) => {
    await page.goto(settings);
    await page.getByRole('link', { name: 'Security' }).click();
    await page.locator('#wprus_ip_whitelist').fill(value);
    await page.locator('#submit').click();
    await expect(page.getByText('Settings saved.')).toBeVisible();
  };
  const readUser = async () => {
    const response = await client.get('/wp-json/wprus-tests/v1/user/wprus_test_alice', { headers: { 'X-WPRUS-Test-Token': secret } });
    expect(response.ok()).toBeTruthy();
    return response.json() as Promise<{ display_name: string }>;
  };
  const update = async (displayName: string) => {
    const response = await client.post('/wprus/token/', {
      form: { wprusdata: encryptMessage({ action: 'update', method: 'post', base_url: baseURL }) },
    });
    expect(response.ok()).toBeTruthy();
    const { nonce } = await response.json() as { nonce: string };
    await client.post('/wprus/update/', {
      form: { token: nonce, wprusdata: encryptMessage({ username: 'wprus_test_alice', base_url: 'https://wprus-peer.test', display_name: displayName }) },
    });
  };
  try {
    const response = await client.get('/wp-json/wprus-tests/v1/remote-address', { headers: { 'X-WPRUS-Test-Token': secret } });
    expect(response.ok()).toBeTruthy();
    const { address } = await response.json() as { address: string };
    expect(address).toMatch(/^\d+\.\d+\.\d+\.\d+$/);
    await setAllowed(address);
    await update('Allowed by IP');
    expect((await readUser()).display_name).toBe('Allowed by IP');
    const blockedRange = address.startsWith('192.0.2.') ? '198.51.100.0/24' : '192.0.2.0/24';
    await setAllowed(blockedRange);
    await update('Blocked by IP');
    expect((await readUser()).display_name).toBe('Allowed by IP');
  } finally {
    await setAllowed('');
    await client.dispose();
  }
});
