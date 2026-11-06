import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';
import fs from 'node:fs';

test('local login and logout reach the simulated connected site through the browser', async ({ browser, page, baseURL }) => {
  const usersFile = process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE;
  if (!baseURL || !usersFile) throw new Error('The browser-test configuration is missing.');
  const secret = JSON.parse(fs.readFileSync(usersFile, 'utf8')).token as string;
  const peerActions = async (enabled: boolean) => {
    const response = await page.request.post('/wp-admin/admin-ajax.php', {
      headers: { 'X-WPRUS-Test-Token': secret },
      form: { action: 'wprus_tests_peer_sessions', enabled: enabled ? '1' : '0' },
    });
    expect(response.ok()).toBeTruthy();
  };
  await peerActions(true);
  const context = await browser.newContext({ baseURL, ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
  try {
    await context.route('https://wprus-peer.test/**', async route => {
      const response = await context.request.post('/wp-json/wprus-tests/v1/peer-request', {
        headers: { 'X-WPRUS-Test-Token': secret },
        data: { url: route.request().url() },
      });
      if (!response.ok()) {
        await route.fulfill({ status: 500, body: 'The simulated site could not handle this request.' });
        return;
      }
      const result = await response.json() as { status: number; body: string; headers?: Record<string, string> };
      await route.fulfill({ status: result.status, headers: result.headers, contentType: 'text/html', body: result.body });
    });
    const peerState = async () => {
      const response = await context.request.get('/wp-json/wprus-tests/v1/peer', { headers: { 'X-WPRUS-Test-Token': secret } });
      expect(response.ok()).toBeTruthy();
      return response.json() as Promise<{ users: Record<string, { logged_in: boolean }>; requests: Array<{ path: string }> }>;
    };
    const login = await context.request.post('/wp-admin/admin-ajax.php', {
      headers: { 'X-WPRUS-Test-Token': secret },
      form: { action: 'wprus_tests_login' },
    });
    expect(login.ok()).toBeTruthy();
    const userPage = await context.newPage();
    await userPage.goto('/');
    await expect.poll(async () => (await peerState()).users.wprus_test_alice.logged_in).toBe(true);

    const nonces = await context.request.post('/wp-admin/admin-ajax.php', {
      headers: { 'X-WPRUS-Test-Token': secret },
      form: { action: 'wprus_tests_nonce' },
    });
    expect(nonces.ok()).toBeTruthy();
    const { logout } = (await nonces.json() as { data: { logout: string } }).data;
    const logoutUrl = new URL('/wp-login.php', baseURL);
    logoutUrl.searchParams.set('action', 'logout');
    logoutUrl.searchParams.set('_wpnonce', logout);
    await userPage.goto(logoutUrl.toString());
    await expect.poll(async () => (await peerState()).users.wprus_test_alice.logged_in).toBe(false);
    const paths = (await peerState()).requests.map(item => item.path);
    expect(paths).toContain('/wprus/login/');
    expect(paths).toContain('/wprus/logout/');
  } finally {
    await context.close();
    await peerActions(false);
  }
});

for (const silent of [false, true]) {
  test(`forced login redirection reaches the simulated site with ${silent ? 'silent' : 'visible'} processing`, async ({ browser, page, baseURL }) => {
    const usersFile = process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE;
    if (!baseURL || !usersFile) throw new Error('The browser-test configuration is missing.');
    const secret = JSON.parse(fs.readFileSync(usersFile, 'utf8')).token as string;
    const configure = async (enabled: boolean) => {
      const response = await page.request.post('/wp-admin/admin-ajax.php', {
        headers: { 'X-WPRUS-Test-Token': secret },
        form: { action: 'wprus_tests_peer_sessions', enabled: enabled ? '1' : '0', strict: '1', silent: silent ? '1' : '0' },
      });
      expect(response.ok()).toBeTruthy();
    };
    await configure(true);
    const context = await browser.newContext({ baseURL, ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    try {
      await context.route('https://wprus-peer.test/**', async route => {
        const response = await context.request.post('/wp-json/wprus-tests/v1/peer-request', {
          headers: { 'X-WPRUS-Test-Token': secret }, data: { url: route.request().url() },
        });
        if (!response.ok()) {
          await route.fulfill({ status: 500, body: 'The simulated site could not handle this request.' });
          return;
        }
        const result = await response.json() as { status: number; body: string; headers?: Record<string, string> };
        await route.fulfill({ status: result.status, headers: result.headers, contentType: 'text/html', body: result.body });
      });
      const login = await context.request.post('/wp-admin/admin-ajax.php', {
        headers: { 'X-WPRUS-Test-Token': secret }, form: { action: 'wprus_tests_login' },
      });
      expect(login.ok()).toBeTruthy();
      let userPage = await context.newPage();
      let redirectUrl = '';
      userPage.on('response', response => {
        if (response.status() === 303) redirectUrl = response.headers().location || '';
      });
      try {
        await userPage.goto('/');
      } catch (error) {
        // Playwright does not route the destination of a server redirect.
        // Open that destination separately so the simulated site receives it.
        if (!silent || !redirectUrl.startsWith('https://wprus-peer.test/')) throw error;
        await userPage.close();
        userPage = await context.newPage();
        await userPage.goto(redirectUrl);
      }
      await expect.poll(async () => {
        const response = await context.request.get('/wp-json/wprus-tests/v1/peer', { headers: { 'X-WPRUS-Test-Token': secret } });
        expect(response.ok()).toBeTruthy();
        const peer = await response.json() as { users: Record<string, { logged_in: boolean }> };
        return peer.users.wprus_test_alice.logged_in;
      }).toBe(true);
      await expect(userPage).toHaveURL(new RegExp(new URL(baseURL).host.replace(/\./g, '\\.')));
    } finally {
      await context.close();
      await configure(false);
    }
  });
}
