import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';
import { request as playwrightRequest } from '@playwright/test';
import fs from 'node:fs';
import { encryptMessage } from './support/protocol';

test('a trusted peer cannot reset a different same-login Administrator password', async ({ page, baseURL }) => {
  if (!baseURL || !process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE) {
    throw new Error('The browser-test site or secret file is missing.');
  }

  const secret = JSON.parse(fs.readFileSync(process.env.ANYAPE_WP_TEST_TOOLS_E2E_USERS_FILE, 'utf8')).token as string;
  const suffix = Date.now().toString();
  const adminLogin = `wprus_test_collision_admin_${suffix}`;
  const controlLogin = `wprus_test_collision_control_${suffix}`;
  const adminEmail = `target-admin-${suffix}@example.test`;
  const sourceEmail = `source-subscriber-${suffix}@example.test`;
  const controlEmail = `same-subscriber-${suffix}@example.test`;
  const originalAdminPassword = `Original-Admin-${suffix}!`;
  const attackerPassword = `Attacker-Password-${suffix}!`;
  const originalControlPassword = `Original-Control-${suffix}!`;
  const changedControlPassword = `Changed-Control-${suffix}!`;
  const peerUrl = 'https://wprus-peer.test';

  const nonceResponse = await page.request.get('/wp-admin/admin-ajax.php?action=rest-nonce');
  expect(nonceResponse.ok()).toBeTruthy();
  const adminNonce = (await nonceResponse.text()).trim();
  const headers = { 'X-WP-Nonce': adminNonce };
  const meResponse = await page.request.get('/wp-json/wp/v2/users/me?context=edit', { headers });
  expect(meResponse.ok()).toBeTruthy();
  const { id: testAdministratorId } = await meResponse.json() as { id: number };

  const createdIds: number[] = [];
  const createUser = async (username: string, email: string, password: string, role: string) => {
    const response = await page.request.post('/wp-json/wp/v2/users', {
      headers,
      data: { username, email, password, roles: [role] },
    });
    expect(response.status(), await response.text()).toBe(201);
    const user = await response.json() as { id: number; roles: string[] };
    createdIds.push(user.id);
    expect(user.roles).toContain(role);
    return user.id;
  };

  const attacker = await playwrightRequest.newContext({
    baseURL,
    storageState: { cookies: [], origins: [] },
    ignoreHTTPSErrors: true,
  });

  try {
    await createUser(adminLogin, adminEmail, originalAdminPassword, 'administrator');
    await createUser(controlLogin, controlEmail, originalControlPassword, 'subscriber');

    const anonymousMe = await attacker.get('/wp-json/wp/v2/users/me');
    expect(anonymousMe.status()).toBe(401);

    const sendPeerPassword = async (username: string, password: string, identityEmail?: string) => {
      const tokenResponse = await attacker.post('/wprus/token/', {
        form: { wprusdata: encryptMessage({ method: 'post', action: 'password', base_url: peerUrl }) },
      });
      expect(tokenResponse.status()).toBe(200);
      const { nonce } = await tokenResponse.json() as { nonce: string };
      expect(nonce).toBeTruthy();
      const payload: Record<string, string> = {
        username,
        user_pass: password,
        base_url: peerUrl,
      };
      if (identityEmail !== undefined) payload.identity_email = identityEmail;
      return attacker.post('/wprus/password/', {
        form: { wprusdata: encryptMessage(payload), token: nonce },
      });
    };

    const passwordMatches = async (username: string, password: string) => {
      const response = await attacker.post('/wp-admin/admin-ajax.php', {
        headers: { 'X-WPRUS-Test-Token': secret },
        form: { action: 'wprus_tests_password', username, password },
      });
      expect(response.ok()).toBeTruthy();
      return (await response.json() as { data: { matches: boolean } }).data.matches;
    };
    expect(await passwordMatches(adminLogin, originalAdminPassword)).toBe(true);
    expect(await passwordMatches(adminLogin, attackerPassword)).toBe(false);

    // The old request omitted the source account's email. It must leave the Administrator untouched.
    expect((await sendPeerPassword(adminLogin, attackerPassword)).status()).toBe(409);
    expect(await passwordMatches(adminLogin, originalAdminPassword)).toBe(true);
    expect(await passwordMatches(adminLogin, attackerPassword)).toBe(false);

    // A source Subscriber with the same login belongs to another person when its email differs.
    expect((await sendPeerPassword(adminLogin, attackerPassword, sourceEmail)).status()).toBe(409);
    expect(await passwordMatches(adminLogin, originalAdminPassword)).toBe(true);
    expect(await passwordMatches(adminLogin, attackerPassword)).toBe(false);

    // A matching account still accepts an authenticated password update.
    expect((await sendPeerPassword(controlLogin, changedControlPassword, controlEmail)).status()).toBe(200);
    expect(await passwordMatches(controlLogin, changedControlPassword)).toBe(true);
    expect(await passwordMatches(adminLogin, originalAdminPassword)).toBe(true);

    const loginAs = async (password: string) => {
      const session = await playwrightRequest.newContext({
        baseURL,
        storageState: { cookies: [], origins: [] },
        ignoreHTTPSErrors: true,
      });
      await session.get('/wp-login.php');
      await session.post('/wp-login.php', {
        form: {
          log: adminLogin,
          pwd: password,
          'wp-submit': 'Log In',
          redirect_to: `${baseURL}/wp-admin/`,
          testcookie: '1',
        },
        maxRedirects: 0,
      });
      return session;
    };

    const failedLogin = await loginAs(attackerPassword);
    try {
      const state = await failedLogin.storageState();
      expect(state.cookies.some(cookie => cookie.name.startsWith('wordpress_logged_in'))).toBe(false);
    } finally {
      await failedLogin.dispose();
    }

    const originalLogin = await loginAs(originalAdminPassword);
    try {
      const state = await originalLogin.storageState();
      expect(state.cookies.some(cookie => cookie.name.startsWith('wordpress_logged_in'))).toBe(true);
      const restNonce = (await (await originalLogin.get('/wp-admin/admin-ajax.php?action=rest-nonce')).text()).trim();
      const identity = await originalLogin.get('/wp-json/wp/v2/users/me?context=edit', {
        headers: { 'X-WP-Nonce': restNonce },
      });
      expect(identity.ok()).toBeTruthy();
      const user = await identity.json() as { id: number; username: string; roles: string[] };
      expect(user.id).toBe(createdIds[0]);
      expect(user.username).toBe(adminLogin);
      expect(user.roles).toContain('administrator');
    } finally {
      await originalLogin.dispose();
    }
  } finally {
    for (const id of createdIds.reverse()) {
      await page.request.delete(`/wp-json/wp/v2/users/${id}?force=true&reassign=${testAdministratorId}`, { headers });
    }
    await attacker.dispose();
  }
});
