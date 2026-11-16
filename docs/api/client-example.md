# WPRUS client example: language-neutral

This example describes the steps needed to communicate with WPRUS using the HTTP, JSON and cryptography libraries of your choice.

## Connection details

```text
receiverURL   = "https://receiver.example"
senderURL     = "https://sender.example"
encryptionKey = "<shared encryption key>"
signatureKey  = "<shared signature key>"
```

`senderURL` must be registered with the receiving site, and the requested operation must be permitted. Use HTTPS and store both keys securely.

## Encrypt and decrypt

```text
function encryptMessage(fields):
    plainBytes = UTF8(SerializeJSON(fields))
    aesKey = SHA256(UTF8(encryptionKey))
    iv = SecureRandomBytes(16)
    encrypted = AES256CBCEncryptWithPKCS7(aesKey, iv, plainBytes)
    signature = HMACSHA256(UTF8(signatureKey), JoinBytes(iv, encrypted))
    combined = JoinBytes(iv, signature, encrypted)
    return ReplaceAll(Base64Encode(combined), "/", "_")

function decryptMessage(encoded):
    combined = Base64Decode(ReplaceAll(encoded, "_", "/"))
    RequireLengthAtLeast(combined, 64)
    iv = combined[0:16]
    signature = combined[16:48]
    encrypted = combined[48:]
    expected = HMACSHA256(UTF8(signatureKey), JoinBytes(iv, encrypted))
    if not ConstantTimeEqual(signature, expected):
        fail("Invalid message signature")
    aesKey = SHA256(UTF8(encryptionKey))
    plain = AES256CBCDecryptWithPKCS7(aesKey, iv, encrypted)
    return ParseJSON(UTF8Decode(plain))
```

The ciphertext must have at least one 16-byte block. Use a fresh random IV for every message. The Base64 conversion replaces only `/` with `_`, keeping `+` and `=`. Verify the format with the [encryption test message](protocol.md#encryption-test-message).

## Obtain a token

```text
function requestToken(action, method):
    message = {
        "action": action,
        "method": method,
        "base_url": receiverURL
    }

    response = HTTP_POST_JSON(
        receiverURL + "/wprus/token/",
        {"wprusdata": encryptMessage(message)}
    )

    if response.status != 200:
        fail("Token request rejected")

    details = ParseJSON(response.body)
    if not IsNonemptyString(details.nonce) or
       not IsFutureUnixTimestamp(details.expiry):
        fail("Invalid or expired token")

    return details.nonce
```

`HTTP_POST_JSON` sends JSON with `Content-Type: application/json`. The token request has no `token` field. Token-request `base_url` is the **receiver URL**.

## Send an operation

```text
function sendOperation(action, fields):
    token = requestToken(action, "post")
    message = Copy(fields)
    message["base_url"] = senderURL

    if action == "wp-admin":
        message["data"]["base_url"] = senderURL

    response = HTTP_POST_JSON(
        receiverURL + "/wprus/" + action + "/",
        {
            "wprusdata": encryptMessage(message),
            "token": token
        }
    )
    return response
```

For an identity check:

```text
response = sendOperation("identity", {
    "username": "example-user",
    "user_email": "user@example.net"
})

if response.status != 200:
    fail("Identity check rejected")

result = ParseJSON(response.body)
available = (
    result.success == true and
    result.data.username_exists == false and
    result.data.email_exists == false
)
```

For an account update:

```text
response = sendOperation("update", {
    "username": "example-user",
    "identity_email": "old@example.net",
    "user_email": "new@example.net",
    "display_name": "Example User"
})

if response.status == 200:
    result = ParseJSON(response.body)
    success = result.success == true and result.data == true
else:
    HandleError(response.status, ParseJSON(response.body))
```

`identity_email` is the account's current email on the receiving site; during an email change it contains the old address. A successful account-changing operation normally returns `{"success":true,"data":true}`.

## Create browser login and logout URLs

Request a GET token, encrypt the account and sender details, and encode both values as URL query parameters:

```text
token = requestToken("login", "get")
message = {
    "username": "example-user",
    "identity_email": "user@example.net",
    "base_url": senderURL,
    "remember": true
}

loginURL = receiverURL + "/wprus/login/?" +
    URLQueryEncode({
        "wprusdata": encryptMessage(message),
        "token": token
    })
```

Open `loginURL` in the visiting user's browser. For logout, request a new GET token and use `/wprus/logout/`. A GET token is valid for one use. If the receiving site requires a redirect, supply `callback_url` in the encrypted message.

For complete operation fields and status codes, see [Operations and responses](operations.md).
