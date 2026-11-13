import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';
import fs from 'node:fs';

test('a subscriber with valid request values cannot clear logs or test connections', async ({ browser, baseURL }) => {
  const usersFile = process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE;
  if (!baseURL || !usersFile) throw new Error('The browser-test configuration is missing.');
  const secret = JSON.parse(fs.readFileSync(usersFile, 'utf8')).token as string;
  const context = await browser.newContext({ baseURL, ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
  try {
    const loginResponse = await context.request.post('/wp-admin/admin-ajax.php', {
      headers: { 'X-WPRUS-Test-Token': secret },
      form: { action: 'wprus_tests_login' },
    });
    expect(loginResponse.ok()).toBeTruthy();
    expect((await loginResponse.json() as { success: boolean }).success).toBe(true);
    const nonceResponse = await context.request.post('/wp-admin/admin-ajax.php', {
      headers: { 'X-WPRUS-Test-Token': secret },
      form: { action: 'wprus_tests_nonce' },
    });
    expect(nonceResponse.ok()).toBeTruthy();
    const nonces = (await nonceResponse.json() as { data: { logs: string; ping: string } }).data;
    for (const action of ['wprus_refresh_logs', 'wprus_clear_logs']) {
      const response = await context.request.post('/wp-admin/admin-ajax.php', { form: { action, nonce: nonces.logs } });
      expect(response.status(), action).toBe(403);
      expect((await response.json() as { success: boolean }).success, action).toBe(false);
    }
    for (const action of ['wprus_wp_admin_notify_ping_remote', 'wprus_wp_admin_notify_outgoing_meta_remote']) {
      const response = await context.request.post('/wp-admin/admin-ajax.php', {
        form: { action, nonce: nonces.ping, site_url: 'https://wprus-peer.test' },
      });
      expect(response.status(), action).toBe(403);
      expect((await response.json() as { success: boolean }).success, action).toBe(false);
    }
  } finally {
    await context.close();
  }
});

test('metadata matching requires a valid administrator request and a saved site', async ({ page }) => {
  await page.goto('/wp-admin/options-general.php?page=wprus');
  const nonce = await page.locator('#wprus_ping_nonce').inputValue();
  const action = 'wprus_wp_admin_notify_outgoing_meta_remote';
  const invalidNonce = await page.request.post('/wp-admin/admin-ajax.php', {
    form: { action, nonce: 'invalid', site_url: 'https://wprus-peer.test' },
  });
  expect((await invalidNonce.json() as { success: boolean }).success).toBe(false);
  const unknown = await page.request.post('/wp-admin/admin-ajax.php', {
    form: { action, nonce, site_url: 'https://unconfigured.test' },
  });
  expect(unknown.status()).toBe(400);
  expect((await unknown.json() as { success: boolean }).success).toBe(false);
  const valid = await page.request.post('/wp-admin/admin-ajax.php', {
    form: { action, nonce, site_url: 'https://wprus-peer.test' },
  });
  expect(valid.status()).toBe(200);
  expect((await valid.json() as { success: boolean; data: string[] }).data).toContain('billing_address_1');
});

test('an administrator can refresh and clear the activity log', async ({ page }) => {
  await page.goto('/wp-admin/options-general.php?page=wprus');
  await page.getByRole('link', { name: 'Activity Logs' }).click();
  const refresh = page.waitForResponse(response => response.url().includes('admin-ajax.php') && response.request().postData()?.includes('wprus_refresh_logs') === true);
  await page.locator('#wprus_log_refresh').click();
  expect((await (await refresh).json() as { success: boolean }).success).toBe(true);
  const clear = page.waitForResponse(response => response.url().includes('admin-ajax.php') && response.request().postData()?.includes('wprus_clear_logs') === true);
  await page.locator('.logs-clean-trigger').click();
  expect((await (await clear).json() as { success: boolean }).success).toBe(true);
});

for (const [mode, file] of [['readable', 'main.js'], ['compact', 'main.min.js']] as const) {
  test(`activity log controls work with the ${mode} administration script`, async ({ page }) => {
    await page.goto(`/wp-admin/options-general.php?page=wprus&wprus_test_script=${mode}`);
    const script = page.locator(`script[src*="/js/admin/${file}?"]`);
    await expect(script).toHaveCount(1);
    await page.getByRole('link', { name: 'Activity Logs' }).click();
    const refresh = page.waitForResponse(response => response.url().includes('admin-ajax.php') && response.request().postData()?.includes('wprus_refresh_logs') === true);
    await page.locator('#wprus_log_refresh').click();
    expect((await (await refresh).json() as { success: boolean }).success).toBe(true);
    const clear = page.waitForResponse(response => response.url().includes('admin-ajax.php') && response.request().postData()?.includes('wprus_clear_logs') === true);
    await page.locator('.logs-clean-trigger').click();
    expect((await (await clear).json() as { success: boolean }).success).toBe(true);
  });
}
