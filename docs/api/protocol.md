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

The receiving site returns an HTTP status code and, for server-to-server operations, a JSON response:

- **200:** The operation succeeded. User-changing operations normally return `{"success":true,"data":true}`.
- **400:** The request contains invalid or missing fields.
- **403:** The token, sending connection, address or requested permission was rejected.
- **404:** The targeted account was not found.
- **409:** Account identifiers conflict or the requested session state conflicts with the current state.
- **422:** The operation could not complete with the supplied data or allowed values.
- **500:** The operation did not produce a usable result.

Typical operation errors have the form:

```json
{"success":false,"data":{"code":"action_failed"}}
```

Some operations return more specific error information, such as `{"code":"invalid_identity_request"}` or an administrative error message. See [Operations and responses](operations.md) for the response shapes associated with each address.

A metadata request can apply some entries and reject others; an error response therefore does not always mean that no entry changed. Check the result before retrying operations that may create duplicates or repeat a destructive change.

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
