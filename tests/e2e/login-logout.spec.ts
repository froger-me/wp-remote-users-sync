import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';
import fs from 'node:fs';
import { encryptMessage } from './support/protocol';

test('a connected-site request logs the user in and out with real WordPress cookies', async ({ browser, baseURL }) => {
  const usersFile = process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE;
  if (!baseURL || !usersFile) throw new Error('The browser-test configuration is missing.');
  const secret = JSON.parse(fs.readFileSync(usersFile, 'utf8')).token as string;
  const context = await browser.newContext({ baseURL, ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
  try {
    const page = await context.newPage();
    const tokenFor = async (action: string) => {
      const response = await context.request.post('/wprus/token/', {
        form: { wprusdata: encryptMessage({ action, method: 'get', base_url: baseURL }) },
      });
      expect(response.ok()).toBeTruthy();
      return (await response.json() as { nonce: string }).nonce;
    };
    const requestUrl = (action: string, token: string) => {
      const url = new URL(`/wprus/${action}/`, baseURL);
      url.searchParams.set('token', token);
      url.searchParams.set('wprusdata', encryptMessage({
        username: 'wprus_test_alice',
        base_url: 'https://wprus-peer.test',
        remember: 0,
        callback_url: baseURL,
      }));
      return url.toString();
    };
    const currentUser = async () => {
      const response = await context.request.post('/wp-admin/admin-ajax.php', {
        headers: { 'X-WPRUS-Test-Token': secret },
        form: { action: 'wprus_tests_identity' },
      });
      expect(response.ok()).toBeTruthy();
      return (await response.json() as { data: { username: string } }).data.username;
    };

    expect(await currentUser()).toBe('');
    const loginToken = await tokenFor('login');
    await page.goto(requestUrl('login', loginToken));
    expect(await currentUser()).toBe('wprus_test_alice');

    const logoutToken = await tokenFor('logout');
    await page.goto(requestUrl('logout', logoutToken));
    expect(await currentUser()).toBe('');

    await page.goto(requestUrl('login', loginToken));
    expect(await currentUser()).toBe('');
  } finally {
    await context.close();
  }
});
