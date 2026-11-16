# Receive WPRUS requests

An application can receive WPRUS synchronization requests by exposing compatible HTTPS addresses. Implement the operations that the connected site is configured to send.

## Set up the connection

1. Give the WPRUS administrator your application's stable HTTPS base URL.
2. Arrange secure access to the shared encryption and signature keys.
3. Agree which outgoing actions WPRUS will send to your application.
4. Configure the account fields, roles and metadata keys that your application will accept.
5. Expose the required paths under your base URL.

Both keys are credentials shared by the connected group. Treat every holder of these keys as a trusted connection.

## HTTP addresses

| Request | Address |
| --- | --- |
| Issue a token | `POST /wprus/token/` |
| Check an account identity | `POST /wprus/identity/` |
| Create an account | `POST /wprus/create/` |
| Update an account | `POST /wprus/update/` |
| Delete an account | `POST /wprus/delete/` |
| Change a password | `POST /wprus/password/` |
| Change metadata | `POST /wprus/meta/` |
| Check connection permissions or metadata settings | `POST /wprus/wp-admin/` |
| Create a visiting user's session | `GET /wprus/login/` |
| End a visiting user's session | `GET /wprus/logout/` |
| Process a logout notification | `POST /wprus/logout/` |

Use the [OpenAPI specification](openapi.yaml) for the complete request and response definitions. Role changes travel in the `roles` fields of Create and Update.

## Parse incoming POST requests

For each POST request:

1. Require the `Content-Type: application/json` header.
2. Parse the outer JSON object and read its `wprusdata` string. Except for token requests, also read its `token` string.
3. Decode the `wprusdata` string, verify its HMAC-SHA-256 signature, decrypt AES-256-CBC, and parse the resulting UTF-8 JSON.
4. For ordinary operations, validate that the token was issued by your application, has not expired, and is valid for the request method.
5. Check the decrypted `base_url` against your registered sending sites and the operation's permissions.
6. Validate the operation's required fields and the account identity before making changes.

The exact encryption steps are in [Message format and tokens](protocol.md).

## Issue and validate tokens

A token request is an `application/json` POST body containing only `wprusdata`. The decrypted JSON includes:

```json
{
  "action": "identity",
  "method": "post",
  "base_url": "https://your-application.example"
}
```

The `base_url` identifies the **receiving application**. Validate `method` as `post` or `get`. Issue a random, unpredictable token and store its expiration time. A successful response has HTTP 200:

```json
{"nonce":"issued-token-value","expiry":1794800000}
```

`expiry` is a Unix timestamp. A token requested for POST may be reused until its expiration. A token used for a browser GET must be consumed when validated.

For invalid token requests, return an unsuccessful HTTP status with a JSON error, for example HTTP 400:

```json
{"success":false,"data":{"code":"invalid_token_request"}}
```

## Check usernames and emails

When you receive `POST /wprus/identity/`, decrypt the request and check `username` and `user_email` separately.

Return HTTP 200 with both booleans:

```json
{
  "success": true,
  "data": {
    "username_exists": false,
    "email_exists": false
  }
}
```

Set each value according to the accounts you maintain. Return HTTP 400 for invalid input. A connected WPRUS site requires dependable identity results before ordinary registrations, so implement identity checks if your application participates in account synchronization.

## Apply account changes

For Create, Update, Delete, Password, and Metadata operations, validate `username`, `base_url` and, for an existing account, `identity_email`. Compare `identity_email` with the account's current email without regard to letter case.

For an email change, `identity_email` identifies the previous address and `user_email` supplies the new one.

Return HTTP 200 and:

```json
{"success":true,"data":true}
```

when the operation succeeds. When an operation fails, return an HTTP status and a specific error code:

```json
{
  "success": false,
  "data": {
    "code": "existing_user_email"
  }
}
```

**Error response fields:**
- `data.code` — required identifier for the failure.
- `data.message` — optional human-readable explanation.
- `data.details` — optional information about partial success.

**HTTP statuses:**

| HTTP | Meaning |
| --- | --- |
| 400 | Invalid input |
| 403 | Permission denied |
| 404 | Account not found |
| 409 | Account identity or identifier conflict |
| 422 | Requested change could not be completed |

Use the [error code reference](operations.md#error-responses-and-retries) for specific codes and conditions.

**Partial-success details:**
- **Role failure:** `account_saved`, `applied_roles`, `rejected_roles`.
- **Deleted account, failed reassignment:** `account_deleted`, `content_reassigned`.
- **Metadata failures:** `applied` count and `rejected` entries with operation, key and code.

Report completed changes accurately so the sender does not repeat them.

The permitted profile fields, role structure and metadata entry formats are described in [Operations and responses](operations.md).

## Handle administrative requests

The administrative address receives encrypted requests with `action` set to `ping` or `outgoing_meta`, plus a nested `data` object. Confirm that both `base_url` and `data.base_url` identify the same registered sender.

For `ping`, check whether `target_action` is enabled in the requested `direction` on your application. Return HTTP 200 with `{"success":true,"data":"..."}` for an allowed action or HTTP 403 with `{"success":false,"data":{"code":"wprus_ping_disabled"}}` when it is disabled.

For `outgoing_meta`, return the metadata keys configured to be sent to the requesting site:

```json
{"success":true,"data":["public_note","customer_preference"]}
```

**Invalid administrative requests:** HTTP 400, with the appropriate error code:
- `wprus_invalid_admin_request`
- `wprus_unknown_admin_action`
- `wprus_invalid_outgoing_meta_request`

See the [administrative error table](operations.md#error-codes-by-operation) for the other administrative cases.

## Handle browser sessions

Browser login and logout requests use GET with URL query parameters `wprusdata` and `token`. Decode and validate the encrypted message and the single-use GET token. Apply the session change in the visiting browser and set or clear its session cookies as appropriate.

The login message includes `username`, the sender's `base_url`, optional `remember` and, when redirecting, `callback_url`. Use HTTP 303 and a `Location` header if the browser should navigate to the callback destination.

Logout can also arrive as a JSON POST notification. Its effect depends on the account and session represented by that request.

## Verify compatibility

Use the [fixed encryption test message](protocol.md#encryption-test-message) to verify decryption, then test token expiration, identity checks, successful and denied actions, metadata permissions, and browser login/logout against a test connection.
