import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';

test('an administrator can test every action in both directions', async ({ page }) => {
  const settings = '/wp-admin/options-general.php?page=wprus';
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
    const rows = site.locator('.wprus-actions tr');
    await expect(rows).toHaveCount(16);
    for (const row of await rows.all()) {
      const cell = row.locator('.action-test');
      const action = await cell.getAttribute('data-action');
      const direction = await cell.getAttribute('data-direction');
      const response = page.waitForResponse(item =>
        item.url().includes('admin-ajax.php') &&
        item.request().postData()?.includes(`wprus_${action}_notify_ping_remote`) === true,
      );
      await cell.getByRole('button', { name: 'Test' }).click();
      const result = await response;
      expect((await result.json() as { success: boolean }).success, `${action} ${direction}`).toBe(true);
      await expect(row.locator('.action-test-result .success'), `${action} ${direction}`).toBeVisible();
    }
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

test('connection tests show failures from the simulated site', async ({ page }) => {
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
  await page.goto('/wp-admin/options-general.php?page=wprus');
  const site = page.locator('.postbox.wprus-site').filter({ has: page.locator('.wprus-container[data-url="https://wprus-peer.test"]') });
  const cell = site.locator('.action-test[data-action="create"][data-direction="incoming"]');
  if (!(await cell.getByRole('button', { name: 'Test' }).isVisible())) await site.locator('.handlediv').click();
  const row = cell.locator('xpath=ancestor::tr');
  page.on('dialog', dialog => dialog.dismiss());
  try {
    for (const failure of ['disabled', 'malformed', 'timeout']) {
      await mode(failure);
      const response = page.waitForResponse(item =>
        item.url().includes('admin-ajax.php') && item.request().postData()?.includes('wprus_create_notify_ping_remote') === true,
      );
      await cell.getByRole('button', { name: 'Test' }).click();
      const result = await response;
      expect((await result.json() as { success: boolean }).success, failure).toBe(false);
      await expect(row.locator('.action-test-result .failure'), failure).toBeVisible();
    }
  } finally {
    await mode('normal');
  }
});
