# Message format and security tokens

Use HTTPS and JSON to exchange messages with WPRUS 2.4.0. All encrypted server-to-server requests use HTTP POST and `Content-Type: application/json`. Browser login and logout use HTTP GET.

## Addresses and request bodies

Append each operation's path to the receiving site's full base URL. For example, if the receiver is `https://receiver.example/subsite`, the token address is `https://receiver.example/subsite/wprus/token/`.

Obtain a token by sending:

```http
POST /wprus/token/ HTTP/1.1
Host: receiver.example
Content-Type: application/json

{"wprusdata":"<encrypted-token-request>"}
```

Send a server-to-server operation using:

```http
POST /wprus/identity/ HTTP/1.1
Host: receiver.example
Content-Type: application/json

{"wprusdata":"<encrypted-action-request>","token":"<issued-nonce>"}
```

`wprusdata` is an encrypted string. The JSON object it represents is described in [Operations and responses](operations.md). Do not put the unencrypted operation fields directly in the outer HTTP body.

## Encrypt a message

1. Serialize the complete operation object as JSON and encode the JSON string in UTF-8.
2. Compute SHA-256 over the UTF-8 bytes of the shared **encryption key**. Use the resulting 32 bytes as the AES-256 key.
3. Generate a fresh, cryptographically random **16-byte IV** for each message.
4. Encrypt the JSON bytes with **AES-256-CBC and PKCS#7 padding**.
5. Calculate the raw 32-byte **HMAC-SHA-256** over the IV followed immediately by the ciphertext. Use the UTF-8 bytes of the shared **signature key** directly as the HMAC key.
6. Concatenate `IV || HMAC || ciphertext` in that order.
7. Encode the combined bytes using ordinary Base64, replacing every `/` character with `_`. Leave `+` and any trailing `=` characters unchanged.

The resulting string is the `wprusdata` value.

To decrypt, replace `_` with `/` and decode Base64. Read the first 16 bytes as the IV, the next 32 bytes as the signature and the rest as ciphertext. Recalculate the HMAC and compare the signatures using a constant-time comparison **before decrypting**. Then decrypt the ciphertext, remove the PKCS#7 padding and parse the UTF-8 JSON.

### Encryption test message

The following values are solely for testing interoperability:

```json
{
  "message": "{\"username\":\"测试-user\",\"value\":0}",
  "aes_key": "wprus-test-encryption-key",
  "hmac_key": "wprus-test-signature-key",
  "bundle": "AAECAwQFBgcICQoLDA0OD9D8e+GQUXhsUoUaRPHpUic56eFEJrFZVaqi5YhAPr2NIPW2oZNa9DtyTJYa0i6IP7Z+NyuIKhJmPnVYafj598hPX87qFleQEU+D3iAzOnEz"
}
```

Decrypting `bundle` using the two test keys must produce the exact `message` string. The fixed message has IV bytes `00` through `0f`. Generate a different random IV for every real message.

## Obtain a token

To send an identity request, encrypt this JSON object:

```json
{
  "action": "identity",
  "method": "post",
  "base_url": "https://receiver.example"
}
```

The `base_url` in the **token request** is the **receiver's** base address. Use `method: "post"` for server-to-server operations and `method: "get"` for browser login and logout.

Send the encrypted message to `POST /wprus/token/` in a JSON body containing `wprusdata`. On success the server returns HTTP 200:

```json
{
  "nonce": "example-token-issued-by-receiver",
  "expiry": 1794800000
}
```

Send the returned `nonce` in the outer `token` field of the next request. The `expiry` value is the expiration time in seconds since January 1, 1970 UTC.

- **POST tokens** may be reused until they expire. Obtain a replacement before `expiry`.
- **GET tokens** are consumed when validated. Obtain a fresh token for each browser action.

Check that the token response is HTTP 200, that `nonce` is a nonempty string and that `expiry` is in the future.

## Send the operation

For an identity check, encrypt:

```json
{
  "username": "example-user",
  "user_email": "user@example.net",
  "base_url": "https://sender.example"
}
```

The `base_url` in an **operation request** identifies the **sender** registered with the receiving site.

Send the encrypted operation and issued token in an `application/json` POST body:

```json
{
  "wprusdata": "<encrypted-identity-request>",
  "token": "example-token-issued-by-receiver"
}
```

A successful identity check returns HTTP 200 and:

```json
{
  "success": true,
  "data": {
    "username_exists": false,
    "email_exists": false
  }
}
```

Check both booleans independently. Account creation is available only when both are `false`. Treat a failed identity check as an unknown result rather than proof of availability.

## Read responses

Server-to-server operations return an HTTP status and JSON. A successful account-changing request returns:

```json
{"success":true,"data":true}
```

Errors return `success:false` and a specific error code in `data.code`:

```json
{
  "success": false,
  "data": {
    "code": "existing_user_email"
  }
}
```

The optional `data.message` is explanatory text. The optional `data.details` reports additional facts, including changes already applied before a failure. For example, a role error includes `account_saved`, `applied_roles` and `rejected_roles`. A failed content reassignment after account deletion includes `account_deleted` and `content_reassigned`.

The status indicates the type of failure:

- **400:** Invalid or incomplete request data.
- **403:** Invalid security token, blocked sending address or an operation that is not permitted.
- **404:** The account does not exist.
- **409:** Username or email conflict, account identity mismatch or session-state conflict.
- **422:** A requested change could not be completed, including rejected roles, unsuccessful content reassignment or metadata changes.
- **500:** A token or administrative operation could not produce a result.

The same HTTP status can describe different conditions. Always inspect `data.code`; use `data.details` to identify partial success. Common examples include `invalid_token` (403), `identity_mismatch` (409), `existing_user_email` (409), `role_not_allowed` (422) and `metadata_partial_failure` (422).

The complete error code and status reference is in [Operations and responses](operations.md#error-responses-and-retries).

For a metadata request, `details.applied` counts successful changes and `details.rejected` identifies unsuccessful entries. For a failed reassignment after deletion, `details.account_deleted` is `true`. Check these values before retrying any operation that can duplicate or destroy data.

## Browser login and logout

Request a token with `method: "get"`. Encrypt the browser operation message containing `username`, the sender's `base_url`, and `identity_email` when the account exists. Pass the encrypted string and token as URL-encoded **query parameters**:

```text
https://receiver.example/wprus/login/?wprusdata=<URL-encoded-encrypted-message>&token=<URL-encoded-token>
```

For logout, use `/wprus/logout/`.

Navigate the visiting user's browser to the URL so its cookies and session can change. A browser action can return HTTP 200 or redirect with HTTP 303, depending on the request. A missing user, blocked action or identity mismatch may produce 400, 403, 404 or 409.

`remember` controls a remembered browser login. `callback_url` specifies a destination when the action requires a redirect.

## Keep credentials secure

Use HTTPS, store the shared keys securely and avoid logging passwords, token values or unencrypted account data. The encryption and signature keys are shared credentials for the connected group; give them only to applications trusted to communicate with that group.
