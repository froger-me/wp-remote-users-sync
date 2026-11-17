# WPRUS client example: Laravel

Use Laravel's HTTP client and PHP encryption functions to communicate with WPRUS. The examples show the steps to use in an application service.

## Connection details

Keep the receiver URL, your registered sender URL, and shared keys in protected environment settings. Make them available through your Laravel configuration, for example under `services.wprus`:

```text
WPRUS_RECEIVER_URL=https://receiver.example
WPRUS_SENDER_URL=https://sender.example
WPRUS_ENCRYPTION_KEY=<shared encryption key>
WPRUS_SIGNATURE_KEY=<shared signature key>
```

Read the configured values in application code:

```php
$receiver = rtrim(config('services.wprus.receiver_url'), '/');
$sender = rtrim(config('services.wprus.sender_url'), '/');
$encryptionKey = config('services.wprus.encryption_key');
$signatureKey = config('services.wprus.signature_key');
```

Use HTTPS. The receiving site must recognize `sender` as a connected site and permit the requested operation.

## Encrypt and decrypt

```php
function encryptMessage(array $fields, string $encryptionKey, string $signatureKey): string
{
    $plain = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $aesKey = hash('sha256', $encryptionKey, true);
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($plain, 'aes-256-cbc', $aesKey, OPENSSL_RAW_DATA, $iv);

    if ($encrypted === false) {
        throw new RuntimeException('Encryption failed');
    }

    $signature = hash_hmac('sha256', $iv . $encrypted, $signatureKey, true);

    return str_replace('/', '_', base64_encode($iv . $signature . $encrypted));
}

function decryptMessage(string $encoded, string $encryptionKey, string $signatureKey): array
{
    $bundle = base64_decode(str_replace('_', '/', $encoded), true);
    if ($bundle === false || strlen($bundle) < 64 ||
        (strlen($bundle) - 48) % 16 !== 0) {
        throw new RuntimeException('Invalid message');
    }

    $iv = substr($bundle, 0, 16);
    $signature = substr($bundle, 16, 32);
    $encrypted = substr($bundle, 48);
    $expected = hash_hmac('sha256', $iv . $encrypted, $signatureKey, true);

    if (!hash_equals($expected, $signature)) {
        throw new RuntimeException('Invalid signature');
    }

    $aesKey = hash('sha256', $encryptionKey, true);
    $plain = openssl_decrypt($encrypted, 'aes-256-cbc', $aesKey, OPENSSL_RAW_DATA, $iv);
    if ($plain === false) {
        throw new RuntimeException('Decryption failed');
    }

    return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
}
```

The wire format is a random 16-byte IV, a 32-byte HMAC-SHA-256 signature, and AES-256-CBC ciphertext, Base64-encoded with only `/` replaced by `_`. Check the decoder against the [fixed test message](protocol.md#encryption-test-message).

## Request a token

The token request's `base_url` is the **receiver URL**.

```php
use Illuminate\Support\Facades\Http;

$tokenRequest = [
    'action' => 'identity',
    'method' => 'post',
    'base_url' => $receiver,
];

$tokenResponse = Http::asJson()->timeout(15)->withoutRedirecting()->post(
    $receiver . '/wprus/token/',
    ['wprusdata' => encryptMessage($tokenRequest, $encryptionKey, $signatureKey)]
)->throw();

$details = $tokenResponse->json();
if (!is_string($details['nonce'] ?? null) || $details['nonce'] === '' ||
    !is_numeric($details['expiry'] ?? null) || (float) $details['expiry'] <= time()) {
    throw new RuntimeException('Invalid or expired token');
}

$token = $details['nonce'];
```

The returned `nonce` is the token; `expiry` is the expiration time as a Unix timestamp. POST tokens may be reused until expiry.

## Send an operation

The operation message's `base_url` is the **sender URL**.

```php
$message = [
    'username' => 'example-user',
    'user_email' => 'user@example.net',
    'base_url' => $sender,
];

$response = Http::asJson()->timeout(15)->withoutRedirecting()->post(
    $receiver . '/wprus/identity/',
    [
        'wprusdata' => encryptMessage($message, $encryptionKey, $signatureKey),
        'token' => $token,
    ]
)->throw();

$result = $response->json();
$available = ($result['success'] ?? false) === true
    && ($result['data']['username_exists'] ?? null) === false
    && ($result['data']['email_exists'] ?? null) === false;
```

For a user update, request a POST token for `update` and send encrypted JSON to `/wprus/update/`. Include `username`, the account's current `identity_email`, and the fields to change. For an email change, use the previous address in `identity_email` and the new one in `user_email`.

Administrative requests use `/wprus/admin/` with `action` set to `test` or `outgoing_meta`. Include the sender URL in both `base_url` and `data.base_url`.

A successful account-changing request returns HTTP 200 with `{"success":true,"data":true}`. Check the HTTP status and returned JSON errors for unsuccessful operations.

## Handle errors

For account-changing requests, inspect the HTTP response before using `throw()`. The error code and optional details identify the actual outcome:

```php
function readWprusResponse(\Illuminate\Http\Client\Response $response): array
{
    if ($response->status() === 200 && $response->json('success') === true) {
        return ['success' => true, 'data' => $response->json('data')];
    }

    $code = $response->json('data.code');
    $details = $response->json('data.details') ?? [];

    if (!is_string($code) || $code === '') {
        throw new RuntimeException('Unrecognized WPRUS error response');
    }

    return [
        'success' => false,
        'status' => $response->status(),
        'code' => $code,
        'details' => $details,
    ];
}
```

**Check these details before retrying:**
- `reassignment_user_not_found`: `details.account_deleted` is `true` — do not repeat the deletion.
- `role_not_allowed` / `role_sync_disabled`: inspect `details.account_saved`, `details.applied_roles` and `details.rejected_roles`.
- `metadata_partial_failure`: inspect `details.applied` and `details.rejected`.

See the [error code reference](operations.md#error-responses-and-retries).

## Browser login and logout

Request a token for `action: login` and `method: get`, then encrypt the account identity and sender URL:

```php
$loginToken = $detailsFromGetTokenRequest['nonce'];
$loginData = [
    'username' => 'example-user',
    'identity_email' => 'user@example.net',
    'base_url' => $sender,
];

$query = http_build_query([
    'wprusdata' => encryptMessage($loginData, $encryptionKey, $signatureKey),
    'token' => $loginToken,
], '', '&', PHP_QUERY_RFC3986);

$loginUrl = $receiver . '/wprus/login/?' . $query;
```

Open `$loginUrl` in the visiting user's browser. For logout, use `/wprus/logout/` and obtain a fresh GET token. Browser tokens are single-use.

See [Operations and responses](operations.md) for additional request fields.
