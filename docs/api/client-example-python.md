# WPRUS client example: Python

Use Python with `cryptography` for encryption and `requests` for HTTP. The examples show the steps to use within an application.

## Connection details

```python
import os

receiver = os.environ["WPRUS_RECEIVER_URL"].rstrip("/")
sender = os.environ["WPRUS_SENDER_URL"].rstrip("/")
encryption_key = os.environ["WPRUS_ENCRYPTION_KEY"]
signature_key = os.environ["WPRUS_SIGNATURE_KEY"]
```

Use HTTPS and keep both keys in protected settings. The receiver must recognize `sender` as a connected site and permit the requested operations. Install dependencies with `python -m pip install cryptography requests`.

## Encrypt and decrypt

```python
import base64
import hashlib
import hmac
import json

from cryptography.hazmat.primitives import padding
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes

def encrypt_message(fields):
    plain = json.dumps(fields, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
    key = hashlib.sha256(encryption_key.encode("utf-8")).digest()
    iv = os.urandom(16)
    padder = padding.PKCS7(128).padder()
    padded = padder.update(plain) + padder.finalize()
    cipher = Cipher(algorithms.AES(key), modes.CBC(iv)).encryptor()
    encrypted = cipher.update(padded) + cipher.finalize()
    signature = hmac.new(signature_key.encode("utf-8"), iv + encrypted, hashlib.sha256).digest()
    return base64.b64encode(iv + signature + encrypted).decode("ascii").replace("/", "_")

def decrypt_message(encoded):
    bundle = base64.b64decode(encoded.replace("_", "/"), validate=True)
    if len(bundle) < 64 or (len(bundle) - 48) % 16:
        raise ValueError("Invalid message length")
    iv, signature, encrypted = bundle[:16], bundle[16:48], bundle[48:]
    expected = hmac.new(signature_key.encode("utf-8"), iv + encrypted, hashlib.sha256).digest()
    if not hmac.compare_digest(signature, expected):
        raise ValueError("Invalid signature")
    key = hashlib.sha256(encryption_key.encode("utf-8")).digest()
    cipher = Cipher(algorithms.AES(key), modes.CBC(iv)).decryptor()
    padded = cipher.update(encrypted) + cipher.finalize()
    unpadder = padding.PKCS7(128).unpadder()
    return json.loads((unpadder.update(padded) + unpadder.finalize()).decode("utf-8"))
```

The message consists of a random 16-byte IV, 32-byte HMAC-SHA-256 signature, and AES-256-CBC ciphertext. Base64 encoding replaces `/` with `_` and leaves `+` and `=` unchanged. Use the [fixed test message](protocol.md#encryption-test-message) to verify decryption.

## Request a token

The token request uses the **receiver URL** in `base_url` and sends `Content-Type: application/json`.

```python
import requests
import time

def request_token(action, method="post"):
    message = {"action": action, "method": method, "base_url": receiver}
    response = requests.post(
        receiver + "/wprus/token/",
        json={"wprusdata": encrypt_message(message)},
        timeout=15,
        allow_redirects=False,
    )
    response.raise_for_status()
    details = response.json()
    if not isinstance(details.get("nonce"), str) or not details["nonce"]:
        raise ValueError("Invalid token")
    if float(details["expiry"]) <= time.time():
        raise ValueError("Expired token")
    return details["nonce"]
```

The response contains `nonce` and `expiry`, a Unix timestamp. Reuse POST tokens until expiry; GET tokens are single-use.

## Send an operation

The operation request uses the **sender URL** in `base_url` and sends `Content-Type: application/json`.

```python
def send_operation(action, fields):
    token = request_token(action, "post")
    message = {**fields, "base_url": sender}
    if action == "wp-admin" and isinstance(message.get("data"), dict):
        message["data"] = {**message["data"], "base_url": sender}
    return requests.post(
        receiver + "/wprus/" + action + "/",
        json={"wprusdata": encrypt_message(message), "token": token},
        timeout=15,
        allow_redirects=False,
    )

response = send_operation("identity", {
    "username": "example-user",
    "user_email": "user@example.net",
})
response.raise_for_status()
result = response.json()
available = (
    result.get("success") is True
    and result["data"]["username_exists"] is False
    and result["data"]["email_exists"] is False
)
```

For an update, supply `username`, the account's current `identity_email`, and any fields to change. Use the old email in `identity_email` and the new email in `user_email`. A successful account-changing request returns HTTP 200 with `{"success":true,"data":true}`. Check the HTTP status and returned JSON errors for unsuccessful operations.

## Handle errors

A failed request returns JSON with a specific `data.code` and optional `data.details`. Inspect these fields before deciding whether to retry:

```python
def read_operation_response(response):
    result = response.json()
    if response.status_code == 200 and result.get("success") is True:
        return result["data"]

    error = result.get("data", {})
    code = error.get("code")
    details = error.get("details", {})

    if not isinstance(code, str):
        raise ValueError("Unrecognized WPRUS error response")

    if code == "reassignment_user_not_found" and details.get("account_deleted"):
        # The account is already deleted; do not repeat Delete.
        pass
    elif code in ("role_not_allowed", "role_sync_disabled"):
        # Account changes may be saved; inspect the applied and rejected roles.
        pass
    elif code == "metadata_partial_failure":
        # Check details["applied"] and details["rejected"] before retrying.
        pass

    raise RuntimeError(f"WPRUS HTTP {response.status_code}: {code}")
```

Other useful codes include `invalid_token` (HTTP 403), `identity_mismatch` (HTTP 409), `existing_user_login` (HTTP 409) and `existing_user_email` (HTTP 409). See the [error code reference](operations.md#error-responses-and-retries).

## Browser login and logout

Request a token with `method="get"` and encrypt a message with the account identity and sender address.

```python
from urllib.parse import urlencode

token = request_token("login", "get")
message = {
    "username": "example-user",
    "identity_email": "user@example.net",
    "base_url": sender,
}
login_url = receiver + "/wprus/login/?" + urlencode({
    "wprusdata": encrypt_message(message), "token": token,
})
```

Navigate the user's browser to `login_url`. For logout, use the corresponding logout path and request a fresh GET token.

See [Operations and responses](operations.md) for additional message fields.
