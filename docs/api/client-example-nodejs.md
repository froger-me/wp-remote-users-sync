# WPRUS client example: Node.js

Use Node.js 20 or later with its built-in encryption, HTTP and URL features. The examples show the communication steps within application code.

## Connection details

```javascript
const receiver = process.env.WPRUS_RECEIVER_URL.replace(/\/+$/, '');
const sender = process.env.WPRUS_SENDER_URL.replace(/\/+$/, '');
const encryptionKey = process.env.WPRUS_ENCRYPTION_KEY;
const signatureKey = process.env.WPRUS_SIGNATURE_KEY;
```

Keep the keys in protected settings. Use HTTPS; the receiver must recognize the sender address as a connected site and permit the requested operation.

## Encrypt and decrypt

```javascript
import {
  createHash, createHmac, createCipheriv, createDecipheriv,
  randomBytes, timingSafeEqual,
} from 'node:crypto';

const aesKey = createHash('sha256').update(encryptionKey, 'utf8').digest();
const hmacKey = Buffer.from(signatureKey, 'utf8');

function encryptMessage(fields) {
  const iv = randomBytes(16);
  const cipher = createCipheriv('aes-256-cbc', aesKey, iv);
  const encrypted = Buffer.concat([
    cipher.update(JSON.stringify(fields), 'utf8'), cipher.final(),
  ]);
  const signature = createHmac('sha256', hmacKey)
    .update(Buffer.concat([iv, encrypted])).digest();
  return Buffer.concat([iv, signature, encrypted])
    .toString('base64').replace(/\//g, '_');
}

function decryptMessage(encoded) {
  const bundle = Buffer.from(encoded.replace(/_/g, '/'), 'base64');
  if (bundle.length < 64 || (bundle.length - 48) % 16 !== 0) {
    throw new Error('Invalid message length');
  }
  const iv = bundle.subarray(0, 16);
  const signature = bundle.subarray(16, 48);
  const encrypted = bundle.subarray(48);
  const expected = createHmac('sha256', hmacKey)
    .update(Buffer.concat([iv, encrypted])).digest();
  if (!timingSafeEqual(signature, expected)) {
    throw new Error('Invalid signature');
  }
  const decipher = createDecipheriv('aes-256-cbc', aesKey, iv);
  return JSON.parse(Buffer.concat([
    decipher.update(encrypted), decipher.final(),
  ]).toString('utf8'));
}
```

The encrypted message contains a fresh 16-byte IV, a 32-byte HMAC-SHA-256 signature, and AES-256-CBC ciphertext. Base64 encoding replaces only `/` with `_`. Check the decoder with the [fixed test message](protocol.md#encryption-test-message).

## Request a token

The token request uses the **receiver URL** in `base_url`.

```javascript
async function postJSON(path, fields) {
  return fetch(receiver + '/wprus/' + path + '/', {
    method: 'POST',
    redirect: 'error',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(fields),
    signal: AbortSignal.timeout(15000),
  });
}

async function requestToken(action, method = 'post') {
  const message = { action, method, base_url: receiver };
  const response = await postJSON('token', {
    wprusdata: encryptMessage(message),
  });
  if (!response.ok) throw new Error('Token request failed');
  const details = await response.json();
  if (typeof details.nonce !== 'string' || !details.nonce ||
      !Number.isFinite(Number(details.expiry)) ||
      Number(details.expiry) <= Date.now() / 1000) {
    throw new Error('Invalid or expired token');
  }
  return details.nonce;
}
```

The returned `nonce` is the token to send with your encrypted operation. A POST token may be reused until its `expiry`; a GET token is single-use.

## Send an operation

The operation message uses the **sender URL** in `base_url`.

```javascript
async function sendOperation(action, fields) {
  const token = await requestToken(action, 'post');
  const message = { ...fields, base_url: sender };
  if (action === 'admin' && message.data) {
    message.data = { ...message.data, base_url: sender };
  }
  return postJSON(action, {
    wprusdata: encryptMessage(message),
    token,
  });
}

const response = await sendOperation('identity', {
  username: 'example-user',
  user_email: 'user@example.net',
});
if (!response.ok) throw new Error('Identity check failed');
const result = await response.json();
const available = result.success === true &&
  result.data.username_exists === false &&
  result.data.email_exists === false;
```

To update an account, send `username`, the identifying `identity_email` and the fields to change. For email changes, send the previous address in `identity_email` and the new address in `user_email`. A successful account-changing request returns HTTP 200 with `{"success":true,"data":true}`. Check the HTTP status and returned JSON errors for unsuccessful operations.

## Handle errors

The error response contains an HTTP status, `data.code` and sometimes `data.details`. Read the body even when the HTTP status indicates an error:

```javascript
async function readOperationResponse(response) {
  const result = await response.json();
  if (response.status === 200 && result.success === true) {
    return result.data;
  }

  const code = result.data?.code;
  const details = result.data?.details ?? {};
  if (typeof code !== 'string') {
    throw new Error('Unrecognized WPRUS error response');
  }

  if (code === 'reassignment_user_not_found' && details.account_deleted) {
    // The account has already been deleted.
  } else if (code === 'role_not_allowed' || code === 'role_sync_disabled') {
    // Inspect details.account_saved, details.applied_roles and details.rejected_roles.
  } else if (code === 'metadata_partial_failure') {
    // Inspect details.applied and details.rejected before retrying.
  }

  throw new Error('WPRUS HTTP ' + response.status + ': ' + code);
}
```

**Other common errors:**
- **403:** `invalid_token` — invalid or expired token.
- **409:** `identity_mismatch` — account identity does not match.
- **409:** `existing_user_login` / `existing_user_email` — login or email already exists.

See the [error code reference](operations.md#error-responses-and-retries).

## Browser login and logout

Use a token with `method="get"` and construct a URL for the visiting user's browser:

```javascript
const token = await requestToken('login', 'get');
const message = {
  username: 'example-user',
  identity_email: 'user@example.net',
  base_url: sender,
};
const loginURL = receiver + '/wprus/login/?' + new URLSearchParams({
  wprusdata: encryptMessage(message),
  token,
});
```

Navigate the user's browser to `loginURL`. For logout, use `/wprus/logout/` and obtain a fresh GET token.

See [Operations and responses](operations.md) for additional request fields.
