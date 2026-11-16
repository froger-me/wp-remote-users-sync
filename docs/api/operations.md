# Operations and responses

All paths below are relative to the receiving site's HTTPS base URL. Server-to-server calls use `POST` with `Content-Type: application/json`. Send encrypted data in a JSON body containing `wprusdata` and `token`. For the token request itself, send only `wprusdata`.

The JSON examples below show the **message before encryption**. See [Message format and tokens](protocol.md) for the HTTP body and encryption steps.

## Available operations

| Method and path | Action | Successful response |
| --- | --- | --- |
| `POST /wprus/token/` | Issue a security token | HTTP 200, `nonce` and `expiry` |
| `POST /wprus/identity/` | Check username and email existence | HTTP 200, two boolean results |
| `POST /wprus/create/` | Create an account | HTTP 200, `{"success":true,"data":true}` |
| `POST /wprus/update/` | Update an account | HTTP 200, `{"success":true,"data":true}` |
| `POST /wprus/delete/` | Delete an account | HTTP 200, `{"success":true,"data":true}` |
| `POST /wprus/password/` | Set an account password | HTTP 200, `{"success":true,"data":true}` |
| `POST /wprus/meta/` | Add, update or delete user metadata | HTTP 200, `{"success":true,"data":true}` |
| `POST /wprus/wp-admin/` | Check permissions or list outgoing metadata keys | HTTP 200, `success` and `data` |
| `GET /wprus/login/` | Establish a session in a browser | HTTP 200 or a 303 redirect |
| `GET /wprus/logout/` | End a session in a browser | HTTP 200 or a 303 redirect |
| `POST /wprus/logout/` | Process a logout notification | HTTP 200, `{"success":true,"data":true}` |

Roles are synchronized using the `roles` field in Create and Update, with the corresponding permission enabled.

## Token: POST /wprus/token/

Encrypt the following object, then send `{"wprusdata":"<encrypted>"}` in the JSON request body:

```json
{
  "action": "identity",
  "method": "post",
  "base_url": "https://receiver.example"
}
```

Set `base_url` to the **receiving** site. A successful HTTP 200 response contains:

```json
{"nonce":"issued-token","expiry":1794800000}
```

Use `method:"get"` when requesting a token for browser login or logout. Token requests containing invalid data return HTTP 400 and an error object, such as `{"success":false,"data":{"code":"invalid_token_request"}}`.

## Identity: POST /wprus/identity/

Check availability before creating an account:

```json
{
  "username": "example-user",
  "user_email": "user@example.net",
  "base_url": "https://sender.example"
}
```

A successful HTTP 200 response contains:

```json
{
  "success": true,
  "data": {
    "username_exists": false,
    "email_exists": false
  }
}
```

The sender URL must identify a configured connection. Check that both properties are actual booleans. If either is `true`, that identifier is already taken. An invalid request returns HTTP 400 with `data.code` set to `invalid_identity_request`. Treat any failed check as unavailable until a valid response is received.

## Fields shared by account operations

For operations involving an account, include:

```json
{
  "username": "example-user",
  "base_url": "https://sender.example",
  "identity_email": "user@example.net"
}
```

- `username` is the account's login name on the receiver.
- `base_url` is the **sender's** registered base URL. This differs from the URL used in a token request.
- `identity_email` identifies an **existing** account by its current email. Matching is case-insensitive. Include it when changing, deleting or otherwise acting on an existing account.

For an email change, send the previous email address in `identity_email` and the desired new address in `user_email`. An identity mismatch produces HTTP 409. Use the account's current login name for all account operations.

## Create: POST /wprus/create/

```json
{
  "username": "example-user",
  "base_url": "https://sender.example",
  "user_email": "user@example.net",
  "display_name": "Example User",
  "first_name": "Example",
  "last_name": "User",
  "roles": {"set": "subscriber", "add": false}
}
```

Supply `username` and `user_email` for a new account. The receiving site accepts the profile fields listed in the [OpenAPI definition](openapi.yaml), including names, display name, profile URL, locale and preferences.

Include `user_pass` within the encrypted operation message when password synchronization is permitted. Include `roles` when role synchronization is permitted. The receiver may limit which roles are accepted.

A successful creation returns HTTP 200 with `data:true`. An existing username or email may return HTTP 409; another invalid field may return HTTP 422. A disabled incoming action returns HTTP 403.

## Update: POST /wprus/update/

```json
{
  "username": "example-user",
  "identity_email": "old@example.net",
  "base_url": "https://sender.example",
  "user_email": "new@example.net",
  "display_name": "Updated Example"
}
```

The receiver identifies the account by `username` and checks `identity_email`. Supply only the profile fields to change. Password changes in `user_pass` and role changes in `roles` depend on the corresponding permissions. The Update operation also accepts `user_activation_key`.

When the account is missing, Update can create it if the sender is also permitted to create accounts. Otherwise the receiver returns HTTP 404. Conflicting identifiers return HTTP 409.

### Roles in Create and Update

```json
{"roles":{"set":"subscriber","add":["customer"]}}
```

`set` specifies a role to set; `add` specifies an array of roles to add. Either may be `false` when unused. Only roles allowed by the receiving site are accepted. Disallowed role changes may return HTTP 403 or 422.

## Delete: POST /wprus/delete/

```json
{
  "username": "example-user",
  "identity_email": "user@example.net",
  "base_url": "https://sender.example",
  "reassign_username": "content-owner"
}
```

`reassign_username` names an account that should receive the deleted account's content. Use `false` when no reassignment is needed. A missing reassignment account may produce HTTP 422 even if the account was deleted. An unknown account returns HTTP 404.

## Password: POST /wprus/password/

```json
{
  "username": "example-user",
  "identity_email": "user@example.net",
  "base_url": "https://sender.example",
  "user_pass": "example-new-password"
}
```

`user_pass` is the new password in plaintext **inside the encrypted JSON message**. Protect the original value and never send it outside `wprusdata`. The receiver must permit incoming password synchronization. A missing account returns HTTP 404.

## Metadata: POST /wprus/meta/

```json
{
  "username": "example-user",
  "identity_email": "user@example.net",
  "base_url": "https://sender.example",
  "add": {
    "public_note": [
      {"meta_value": "First value", "unique": false}
    ]
  },
  "update": {
    "public_note": [
      {"meta_value": "Second value", "prev_value": "First value"}
    ]
  },
  "delete": {
    "public_note": [
      {"meta_value": "Second value", "delete_all": false}
    ]
  }
}
```

Each of `add`, `update` and `delete` is optional. Each metadata key maps to an **array** of entries. Supply the operation-specific fields shown in the example. Values can contain strings, numbers, booleans, lists or JSON objects.

Metadata keys must be permitted by the receiver and use lowercase letters, digits, underscores or hyphens. Protected and unapproved keys are rejected. A request may change some keys and reject others; such requests can return HTTP 422. A missing account returns HTTP 404. A disabled action or missing incoming metadata configuration returns HTTP 403.

## Administration: POST /wprus/wp-admin/

Use the same encrypted POST format as other operations. Both the outer `base_url` and nested `data.base_url` identify the sender.

### Check a connection

```json
{
  "username": "example-user",
  "base_url": "https://sender.example",
  "action": "ping",
  "data": {
    "base_url": "https://sender.example",
    "target_action": "create",
    "direction": "incoming"
  }
}
```

`target_action` can be `login`, `logout`, `create`, `update`, `delete`, `password`, `role` or `meta`. The `direction` is `incoming` or `outgoing` **on the receiving site**.

For a permitted action, the receiver returns HTTP 200 and `{"success":true,"data":"..."}`. A disabled action returns HTTP 403 with an error message.

### List outgoing metadata keys

```json
{
  "username": "example-user",
  "base_url": "https://sender.example",
  "action": "outgoing_meta",
  "data": {"base_url": "https://sender.example"}
}
```

A successful HTTP 200 response contains the keys selected to be sent **from the receiver to the requester**:

```json
{"success":true,"data":["public_note","customer_preference"]}
```

Invalid or unknown administrative requests return HTTP 400; unsuccessful administrative results usually provide a string error in `data`. A blocked connection test returns HTTP 403.

## Browser login: GET /wprus/login/

Obtain a one-use token using `method:"get"` and encrypt a message such as:

```json
{
  "username": "example-user",
  "identity_email": "user@example.net",
  "base_url": "https://sender.example",
  "remember": true,
  "callback_url": "https://receiver.example/account"
}
```

Supply the encrypted message in the URL query parameter `wprusdata` and the issued token in `token`. Navigate the **visiting user's browser** to that URL so the receiver can establish the browser's session. `callback_url` is required when the receiver expects a redirect.

## Browser logout: GET /wprus/logout/ or POST /wprus/logout/

For GET, obtain a new one-use GET token and place the encrypted message and token in the URL parameters. Open the address in the visiting user's browser.

For POST, use the same JSON body as other server-to-server operations with a POST token. A logout notification has the shared account fields:

```json
{
  "username": "example-user",
  "identity_email": "user@example.net",
  "base_url": "https://sender.example"
}
```

The logout result depends on the receiving session. A successful POST returns HTTP 200 with `data:true`; an account already logged out or a session mismatch can return HTTP 409.

## Errors and retries

An unsuccessful server-to-server operation returns HTTP 400–599 and typically:

```json
{"success":false,"data":{"code":"action_failed"}}
```

Some errors return a more specific `code`, optional `message`, or a text message in `data`. Use the HTTP status to determine success and inspect `success` and `data` for details. A successful user-changing POST normally returns HTTP 200 with `{"success":true,"data":true}`.

If a metadata operation partly succeeds, or an account deletion is reported unsuccessful after a reassignment problem, check the resulting data before retrying.
