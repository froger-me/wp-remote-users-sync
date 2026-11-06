import { test, expect, lowerCapabilityStorageState } from '../../../../../.anyape-wp-test-tools/e2e/test';

test('an administrator can open all settings tabs', async ({ page }) => {
  await page.goto('/wp-admin/options-general.php?page=wprus');
  await expect(page.getByRole('heading', { name: 'WP Remote Users Sync' })).toBeVisible();
  for (const name of ['Remote Sites', 'Security', 'Activity Logs', 'Miscellaneous', 'Help']) {
    const tab = page.getByRole('link', { name });
    await tab.click();
    await expect(tab).toHaveClass(/nav-tab-active/);
  }
});

test('an editor cannot open the plugin settings', async ({ browser, baseURL }) => {
  const context = await browser.newContext({ baseURL, storageState: lowerCapabilityStorageState, ignoreHTTPSErrors: true });
  try {
    const page = await context.newPage();
    const response = await page.goto('/wp-admin/options-general.php?page=wprus');
    expect(response?.status()).toBe(403);
  } finally {
    await context.close();
  }
});

test('an administrator can add, save, and remove a connected site', async ({ page }) => {
  await page.goto('/wp-admin/options-general.php?page=wprus');
  const url = 'https://second.wprus.test';
  const site = page.locator('.postbox.wprus-site').filter({ has: page.locator(`.wprus-container[data-url="${url}"]`) });
  await page.locator('#wprus_add_value').fill(url);
  await page.locator('#wprus_add_trigger').click();
  await expect(site).toHaveCount(1);
  await page.locator('#wprus_add_trigger').click();
  await expect(site).toHaveCount(1);
  await page.locator('#submit').click();
  await expect(page.getByText('Settings saved.')).toBeVisible();
  await expect(page.locator(`.wprus-container[data-url="${url}"]`)).toHaveCount(1);
  if (!(await site.getByRole('link', { name: 'Remove site configuration' }).isVisible())) {
    await site.locator('.handlediv').click();
  }
  page.once('dialog', dialog => dialog.accept());
  await page.locator(`.wprus-container[data-url="${url}"]`).getByRole('link', { name: 'Remove site configuration' }).click();
  await page.locator('#submit').click();
  await expect(page.getByText('Settings saved.')).toBeVisible();
  await expect(page.locator(`.wprus-container[data-url="${url}"]`)).toHaveCount(0);
});

test('each incoming and outgoing action controls its connection-test button', async ({ page }) => {
  await page.goto('/wp-admin/options-general.php?page=wprus');
  const site = page.locator('.postbox.wprus-site').filter({ has: page.locator('.wprus-container[data-url="https://wprus-peer.test"]') });
  const firstCheckbox = site.locator('.wprus-actions .action-checkbox input').first();
  if (!(await firstCheckbox.isVisible())) await site.locator('.handlediv').click();
  const rows = site.locator('.wprus-actions tr');
  await expect(rows).toHaveCount(16);
  for (const row of await rows.all()) {
    const checkbox = row.locator('.action-checkbox input');
    const button = row.locator('.action-test button');
    const originallyChecked = await checkbox.isChecked();
    if (originallyChecked) {
      await checkbox.uncheck();
      await expect(button).toBeDisabled();
      await checkbox.check();
      await expect(button).toBeEnabled();
    } else {
      await checkbox.check();
      await expect(button).toBeEnabled();
      await checkbox.uncheck();
      await expect(button).toBeDisabled();
    }
  }
  const toggle = site.getByRole('link', { name: 'Toggle all actions' });
  await toggle.click();
  for (const checkbox of await site.locator('.wprus-actions .action-checkbox input').all()) await expect(checkbox).toBeChecked();
  await toggle.click();
  for (const checkbox of await site.locator('.wprus-actions .action-checkbox input').all()) await expect(checkbox).not.toBeChecked();
});

test('security keys are shown on focus and hidden after leaving the field', async ({ page }) => {
  await page.goto('/wp-admin/options-general.php?page=wprus');
  await page.getByRole('link', { name: 'Security' }).click();
  for (const selector of ['#wprus_aes_key', '#wprus_hmac_key']) {
    const field = page.locator(selector);
    await expect(field).toHaveAttribute('type', 'password');
    await field.focus();
    await expect(field).toHaveAttribute('type', 'text');
    await field.blur();
    await expect(field).toHaveAttribute('type', 'password');
  }
});

test('the removed import and export controls are not available', async ({ page, request }) => {
  await page.goto('/wp-admin/options-general.php?page=wprus');
  await expect(page.locator('#wprus_import_file_trigger')).toBeHidden();
  await expect(page.locator('#wprus_export_trigger')).toBeHidden();
  for (const action of ['wprus_import_users', 'wprus_export_users', 'wprus_get_usernames']) {
    const response = await request.post('/wp-admin/admin-ajax.php', { form: { action } });
    expect(response.status(), action).toBe(400);
    expect(await response.text(), action).toBe('0');
  }
});

test('an administrator can save metadata and role selections', async ({ page }) => {
  await page.goto('/wp-admin/options-general.php?page=wprus');
  const site = page.locator('.postbox.wprus-site').filter({ has: page.locator('.wprus-container[data-url="https://wprus-peer.test"]') });
  const outgoingMeta = site.locator('.row-outgoing-metadata');
  if (!(await outgoingMeta.locator('.select2-selection').isVisible())) await site.locator('.handlediv').click();
  const choose = async (row: ReturnType<typeof site.locator>, value: string) => {
    await row.locator('.select2-selection').click();
    await page.locator('.select2-container--open .select2-results__option').getByText(value, { exact: true }).click();
  };
  try {
    await choose(outgoingMeta, 'test_note');
    await choose(site.locator('.row-outgoing-roles'), 'editor');
    await choose(site.locator('.row-incoming-roles'), 'subscriber');
    await site.locator('input[name$="[incoming_roles_merge]"]').check();
    await page.locator('#submit').click();
    await expect(page.getByText('Settings saved.')).toBeVisible();
    await expect(site.locator('select[name$="[outgoing_meta][]"]')).toHaveValues(['test_note']);
    await expect(site.locator('select[name$="[outgoing_roles][]"]')).toHaveValues(['editor']);
    await expect(site.locator('select[name$="[incoming_roles][]"]')).toHaveValues(['subscriber']);
    await expect(site.locator('input[name$="[incoming_roles_merge]"]')).toBeChecked();
  } finally {
    await page.goto('/wp-admin/options-general.php?page=wprus');
    for (const row of ['.row-outgoing-metadata', '.row-outgoing-roles', '.row-incoming-roles']) {
      await site.locator(`${row} select`).evaluate(element => {
        const select = element as HTMLSelectElement;
        for (const option of select.options) option.selected = false;
        select.dispatchEvent(new Event('change', { bubbles: true }));
      });
    }
    await site.locator('input[name$="[incoming_roles_merge]"]').evaluate(element => { (element as HTMLInputElement).checked = false; });
    await page.locator('#submit').click();
    await expect(page.getByText('Settings saved.')).toBeVisible();
  }
});

test('browser support choices are saved and conflicting choices are disabled', async ({ page }) => {
  const settings = '/wp-admin/options-general.php?page=wprus';
  const misc = async () => { await page.getByRole('link', { name: 'Miscellaneous' }).click(); };
  await page.goto(settings);
  await misc();
  try {
    await page.locator('#wprus_force_login_redirect').check();
    await expect(page.locator('#wprus_disable_login_redirect')).toBeDisabled();
    await page.locator('#wprus_silent_login_logout_strict').check();
    await page.locator('#submit').click();
    await expect(page.getByText('Settings saved.')).toBeVisible();
    await misc();
    await expect(page.locator('#wprus_force_login_redirect')).toBeChecked();
    await expect(page.locator('#wprus_silent_login_logout_strict')).toBeChecked();
    await page.locator('#wprus_force_login_redirect').uncheck();
    await page.locator('#wprus_disable_login_redirect').check();
    await expect(page.locator('#wprus_force_login_redirect')).toBeDisabled();
    await page.locator('#submit').click();
    await expect(page.getByText('Settings saved.')).toBeVisible();
    await misc();
    await expect(page.locator('#wprus_disable_login_redirect')).toBeChecked();
  } finally {
    await page.goto(settings);
    await misc();
    const disabled = page.locator('#wprus_disable_login_redirect');
    if (await disabled.isChecked()) await disabled.uncheck();
    const forced = page.locator('#wprus_force_login_redirect');
    if (await forced.isChecked()) await forced.uncheck();
    await page.locator('#wprus_silent_login_logout_strict').uncheck();
    await page.locator('#submit').click();
    await expect(page.getByText('Settings saved.')).toBeVisible();
  }
});

test('the administrator can save and clear allowed IP addresses', async ({ page }) => {
  const settings = '/wp-admin/options-general.php?page=wprus';
  const security = async () => { await page.getByRole('link', { name: 'Security' }).click(); };
  await page.goto(settings);
  await security();
  try {
    await page.locator('#wprus_ip_whitelist').fill('192.0.2.0/24\n198.51.100.2');
    await page.locator('#submit').click();
    await expect(page.getByText('Settings saved.')).toBeVisible();
    await security();
    await expect(page.locator('#wprus_ip_whitelist')).toHaveValue('192.0.2.0/24\n198.51.100.2');
  } finally {
    await page.goto(settings);
    await security();
    await page.locator('#wprus_ip_whitelist').fill('');
    await page.locator('#submit').click();
    await expect(page.getByText('Settings saved.')).toBeVisible();
  }
});
