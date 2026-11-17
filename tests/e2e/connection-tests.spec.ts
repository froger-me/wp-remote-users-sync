import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';

const settings = '/wp-admin/options-general.php?page=wprus';

for (const mode of ['readable', 'compact'] as const) {
  test(`connection test uses the ${mode} administration script`, async ({ page }) => {
    await page.goto(`${settings}&wprus_test_script=${mode}`);
    const file = mode === 'readable' ? 'main.js' : 'main.min.js';
    await expect(page.locator(`script[src*="/js/admin/${file}?"]`)).toHaveCount(1);
    const site = page.locator('.postbox.wprus-site').filter({
      has: page.locator('.wprus-container[data-url="https://wprus-peer.test"]'),
    });
    const button = site.locator('.wprus-site-test').getByRole('button', { name: 'Test' });
    if (!(await button.isVisible())) await site.locator('.handlediv').click();
    const requests: URLSearchParams[] = [];
    page.on('request', request => {
      if (request.url().includes('admin-ajax.php') && request.postData()?.includes('wprus_admin_notify_test_remote')) {
        requests.push(new URLSearchParams(request.postData() ?? ''));
      }
    });
    await button.click();
    await expect(site.locator('.wprus-site-test-result.success')).toContainText('All action settings match.');
    expect(requests).toHaveLength(1);
    expect(requests[0].get('data[actions][outgoing][create]')).toBe('1');
    expect(requests[0].get('data[actions][incoming][login]')).toBe('1');
  });
}

test('one button tests every action setting in both directions', async ({ page }) => {
  const site = page.locator('.postbox.wprus-site').filter({
    has: page.locator('.wprus-container[data-url="https://wprus-peer.test"]'),
  });
  const openSite = async () => {
    const first = site.locator('.wprus-actions input[type="checkbox"]').first();
    if (!(await first.isVisible())) await site.locator('.handlediv').click();
  };
  await page.goto(settings);
  await openSite();
  const original = await site.locator('.wprus-actions input[type="checkbox"]').evaluateAll(
    inputs => inputs.map(input => (input as HTMLInputElement).checked),
  );
  page.on('dialog', dialog => dialog.dismiss());
  try {
    for (const checkbox of await site.locator('.wprus-actions input[type="checkbox"]').all()) await checkbox.check();
    await page.locator('#submit').click();
    await expect(page.getByText('Settings saved.')).toBeVisible();
    await openSite();
    const requests: URLSearchParams[] = [];
    page.on('request', request => {
      if (request.url().includes('admin-ajax.php') && request.postData()?.includes('wprus_admin_notify_test_remote')) {
        requests.push(new URLSearchParams(request.postData() ?? ''));
      }
    });
    const button = site.locator('.wprus-site-test').getByRole('button', { name: 'Test' });
    await expect(button).toHaveCount(1);
    await button.click();
    await expect(site.locator('.wprus-site-test-result.success')).toContainText('All action settings match.');
    expect(requests).toHaveLength(1);
    expect(Array.from(requests[0].keys()).filter(key => key.includes('data[actions]'))).toHaveLength(16);
  } finally {
    await page.goto(settings);
    await openSite();
    const inputs = await site.locator('.wprus-actions input[type="checkbox"]').all();
    for (let index = 0; index < inputs.length; index++) {
      if (original[index]) await inputs[index].check();
      else await inputs[index].uncheck();
    }
    await page.locator('#submit').click();
    await expect(page.getByText('Settings saved.')).toBeVisible();
  }
});

test('connection test summarizes every failure from the simulated site', async ({ page }) => {
  const usersFile = process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE;
  if (!usersFile) throw new Error('The browser-test secret file is missing.');
  const { readFileSync } = await import('node:fs');
  const secret = JSON.parse(readFileSync(usersFile, 'utf8')).token as string;
  const mode = async (value: string) => {
    const response = await page.request.post('/wp-admin/admin-ajax.php', {
      headers: { 'X-WPRUS-Test-Token': secret },
      form: { action: 'wprus_tests_peer_mode', mode: value },
    });
    expect(response.ok()).toBeTruthy();
  };
  await page.goto(settings);
  const site = page.locator('.postbox.wprus-site').filter({
    has: page.locator('.wprus-container[data-url="https://wprus-peer.test"]'),
  });
  const button = site.locator('.wprus-site-test').getByRole('button', { name: 'Test' });
  if (!(await button.isVisible())) await site.locator('.handlediv').click();
  page.on('dialog', dialog => dialog.dismiss());
  try {
    for (const failure of ['disabled', 'malformed', 'timeout']) {
      await mode(failure);
      await button.click();
      const result = site.locator('.wprus-site-test-result.failure');
      await expect(result).toContainText('The connection test could not be completed.');
      await expect(result.locator('p')).toHaveCount(1);
      await expect(result.locator('li')).toHaveCount(0);
    }
    await mode('unknown');
    await button.click();
    await expect(site.locator('.wprus-site-test-result.failure')).toContainText('The remote site did not recognize the connection test request.');
    await mode('mismatch');
    await button.click();
    await expect(site.locator('.wprus-site-test-result.warning')).toContainText('An action will not synchronize unless');
    const mismatches = site.locator('.wprus-site-test-result.warning li');
    await expect(mismatches).toHaveCount(2);
    await expect(mismatches.nth(0)).toContainText('Incoming Actions — Login');
    await expect(mismatches.nth(1)).toContainText('Outgoing Actions — Create');
  } finally {
    await mode('normal');
  }
});
