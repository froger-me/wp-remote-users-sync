# WPRUS client example: Bash

Use Bash, curl, jq and OpenSSL to communicate with WPRUS from a shell workflow. These examples illustrate the request sequence; incorporate the parts you need into your script.

## Connection details

```bash
receiver="$WPRUS_RECEIVER_URL"
sender="$WPRUS_SENDER_URL"
```

Set `WPRUS_ENCRYPTION_KEY` and `WPRUS_SIGNATURE_KEY` in protected environment settings. Both URLs must use HTTPS. The receiver must recognize `sender` as a configured connection and permit the requested actions.

## Encrypt and decrypt

WPRUS uses SHA-256 of the encryption key text as a 32-byte AES key. Each message has a new random 16-byte IV, AES-256-CBC ciphertext with PKCS#7 padding, and a 32-byte HMAC-SHA-256 signature over the IV followed by the ciphertext.

```bash
encrypt_json() (
  set -e
  tmp=$(mktemp -d)
  trap 'rm -rf "$tmp"' EXIT

  key_hex=$(printf '%s' "$WPRUS_ENCRYPTION_KEY" |
    openssl dgst -sha256 -binary | od -An -tx1 -v | tr -d ' \n')
  openssl rand -out "$tmp/iv" 16
  iv_hex=$(od -An -tx1 -v < "$tmp/iv" | tr -d ' \n')

  printf '%s' "$1" | openssl enc -aes-256-cbc -e \
    -K "$key_hex" -iv "$iv_hex" -out "$tmp/ciphertext"

  cat "$tmp/iv" "$tmp/ciphertext" |
    openssl dgst -sha256 -hmac "$WPRUS_SIGNATURE_KEY" -binary > "$tmp/signature"

  cat "$tmp/iv" "$tmp/signature" "$tmp/ciphertext" |
    openssl base64 -A | tr '/' '_'
)
```

The Base64 result replaces only `/` with `_`, leaving `+` and `=` unchanged. For decryption, reverse the encoding, verify the HMAC with a constant-time comparison and decrypt the ciphertext. Use the [fixed test message](protocol.md#encryption-test-message) to confirm compatibility. Protect the process environment on machines shared with other users.

## Request a token

The encrypted token request identifies the **receiver URL**:

```bash
request=$(jq -nc --arg url "$receiver" \
  '{action:"identity",method:"post",base_url:$url}')

token_body=$(jq -nc --arg data "$(encrypt_json "$request")" \
  '{wprusdata:$data}')

token_response=$(curl --fail-with-body --silent --show-error \
  --header 'Content-Type: application/json' \
  --data "$token_body" "$receiver/wprus/token/")

token=$(printf '%s' "$token_response" | jq -er \
  --argjson now "$(date +%s)" \
  'select((.expiry | tonumber) > $now) | .nonce')
```

The response contains `nonce` (the token) and `expiry` (a Unix timestamp). POST tokens may be reused until they expire.

## Send an operation

The operation message identifies the **sender URL**. Send JSON with `wprusdata` and `token`:

```bash
identity=$(jq -nc --arg url "$sender" \
  '{username:"example-user",user_email:"user@example.net",base_url:$url}')

body=$(jq -nc \
  --arg data "$(encrypt_json "$identity")" \
  --arg token "$token" \
  '{wprusdata:$data,token:$token}')

response=$(curl --fail-with-body --silent --show-error \
  --header 'Content-Type: application/json' \
  --data "$body" "$receiver/wprus/identity/")

printf '%s\n' "$response" | jq .
```

On HTTP 200, an identity response contains `success:true` and separate `data.username_exists` and `data.email_exists` booleans. Both must be `false` to confirm availability.

For an update, request a POST token for `update`, then send the same outer JSON structure to `/wprus/update/` with an encrypted message containing the account's `username`, current `identity_email` and changed fields. A successful user update returns HTTP 200 and `{"success":true,"data":true}`; inspect non-200 status codes and their JSON errors.

For `wp-admin` requests, include both the sender's top-level `base_url` and `data.base_url` in the encrypted message.

## Browser login and logout

Request a token using `method:"get"`. Encrypt the browser operation message containing `username`, `identity_email` and sender `base_url`. URL-encode `wprusdata` and `token` into the query string of `/wprus/login/` or `/wprus/logout/`, then navigate the visiting user's browser to that URL. GET tokens are single-use.

See [Operations and responses](operations.md) for the complete request fields.
