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

For an email change, send the previous email address in `identity_email` and the desired new address in `user_email`. Use the account's current login name for all account operations. **When that login already exists but the supplied identifying email is missing or different, the receiver returns HTTP 409 with `identity_mismatch` before running the requested operation.**

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

A successful creation returns HTTP 200 with `data:true`. An existing username returns HTTP **409** with `existing_user_login`; an existing email returns HTTP **409** with `existing_user_email`. An invalid request returns **400** with `invalid_create_request`; another account creation failure returns **422** with `user_creation_failed`. A disabled incoming Create action returns **403** with `create_not_allowed`. If creating the account succeeds but a requested role is rejected, the response identifies the role error and includes `details.account_saved: true`.

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

When the account is missing, Update creates it if incoming Create is also permitted. Otherwise the receiver returns **404** with `user_not_found`. Invalid requests return **400** with `invalid_update_request`; disabled incoming Update returns **403** with `update_not_allowed`. Existing username or email conflicts return **409** with `existing_user_login` or `existing_user_email`. Other update failures return **422** with `user_update_failed`. Role errors include `details.account_saved: true` when the profile changes have already been saved.

### Roles in Create and Update

```json
{"roles":{"set":"subscriber","add":["customer"]}}
```

`set` specifies a role to set; `add` specifies an array of roles to add. Either can be `false` when unused. If role synchronization is disabled, the response is **403** with `role_sync_disabled`. If role synchronization is enabled but a requested role does not exist or is not permitted, the response is **422** with `role_not_allowed`. Both errors include `details.account_saved`, `details.applied_roles` and `details.rejected_roles`. Any accepted changes to the account or its roles can already have taken effect.

## Delete: POST /wprus/delete/

```json
{
  "username": "example-user",
  "identity_email": "user@example.net",
  "base_url": "https://sender.example",
  "reassign_username": "content-owner"
}
```

`reassign_username` names the account that receives the deleted account's content. Use `false` when no reassignment is needed. If the requested reassignment account is missing but the account being deleted exists, the receiver **deletes the account** and returns **422** with `reassignment_user_not_found`, `details.account_deleted: true` and `details.content_reassigned: false`. If deletion itself fails, the response is **422** with `user_deletion_failed`. An unknown account returns **404** with `user_not_found`. Invalid requests return **400** with `invalid_delete_request`; a disabled Delete action returns **403** with `delete_not_allowed`.

## Password: POST /wprus/password/

```json
{
  "username": "example-user",
  "identity_email": "user@example.net",
  "base_url": "https://sender.example",
  "user_pass": "example-new-password"
}
```

`user_pass` is the new password in plaintext **inside the encrypted JSON message**. Protect the original value and never send it outside `wprusdata`. Invalid requests return **400** with `invalid_password_request`; disabled incoming Password returns **403** with `password_not_allowed`; an unknown account returns **404** with `user_not_found`.

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

Metadata keys must be permitted by the receiver and use lowercase letters, digits, underscores or hyphens. A malformed request returns **400** with `invalid_metadata_request`. A disabled Metadata action returns **403** with `metadata_not_allowed`; missing incoming metadata configuration returns **403** with `metadata_not_configured`; an unknown account returns **404** with `user_not_found`.

Rejected entries return **422**. If some metadata entries succeeded, `data.code` is `metadata_partial_failure`; if none succeeded, it is the code of the first rejection: `metadata_key_invalid`, `metadata_key_not_allowed` or `metadata_change_failed`. In both cases, `data.details.applied` gives the number of successful changes and `data.details.rejected` lists each rejected operation, key and error code. A request with no successful changes and no rejection entries returns **422** with `invalid_metadata_request`.

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

For a permitted action, the receiver returns HTTP 200 and `{"success":true,"data":"..."}`. A disabled action returns **403** with `wprus_ping_disabled`. Invalid connection tests return **400** with `wprus_invalid_ping`.

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

Administrative failures return structured `data.code` values. Invalid request fields return **400** with `wprus_invalid_admin_request`; an unknown administrative action returns **400** with `wprus_unknown_admin_action`. Invalid outgoing metadata arguments return **400** with `wprus_invalid_outgoing_meta_request`; an unrecognized requesting site returns **400** with `wprus_unknown_outgoing_meta_site`. An administrative operation with no result returns **500** with `wprus_admin_no_result`.

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

The POST logout result depends on the receiving session. Success returns HTTP 200 with `data:true`. If no session is active, the response is **409** with `already_logged_out`; if the active session belongs to a different account, it is **409** with `session_mismatch`. Invalid requests return **400** with `invalid_logout_request`, disabled Logout returns **403** with `logout_not_allowed`, and an unknown account returns **404** with `user_not_found`.

## Error responses and retries

Every unsuccessful server-to-server action returns an HTTP status and a JSON response. Read both `data.code` and `data.details`:

```json
{
  "success": false,
  "data": {
    "code": "role_not_allowed",
    "details": {
      "account_saved": true,
      "applied_roles": ["editor"],
      "rejected_roles": ["administrator"]
    }
  }
}
```

`code` is a stable identifier for the failure. `message` can also be present for a human-readable explanation. `details` is present when the result requires more information. A message is not needed to determine the error.

### Errors shared by operations

| HTTP | Error code | Meaning |
| --- | --- | --- |
| 403 | `invalid_token` | The supplied token is missing, invalid or expired |
| 403 | `ip_not_allowed` | The sender's IP address is not permitted |
| 403 | `method_not_allowed` | The request method or its authorization was not accepted |
| 409 | `identity_mismatch` | An existing account's login and identifying email do not match the request |

The receiver verifies tokens and sender permissions before processing the operation. Browser GET errors use HTTP statuses and can redirect or return a page rather than this JSON response format.

### Error codes by operation

| Operation | HTTP | Error code | Condition |
| --- | --- | --- | --- |
| Token | 400 | `invalid_token_request` | Missing or invalid token request |
| Token | 500 | `token_creation_failed` | Token could not be issued |
| Identity | 400 | `invalid_identity_request` | Missing, invalid or unregistered identity check details |
| Create | 400 | `invalid_create_request` | Invalid account creation request |
| Create | 403 | `create_not_allowed` | Incoming Create permission disabled |
| Create / Update | 409 | `existing_user_login` | Login name already belongs to an account |
| Create / Update | 409 | `existing_user_email` | Email address already belongs to an account |
| Create | 422 | `user_creation_failed` | Account creation failed for another reason |
| Update | 400 | `invalid_update_request` | Invalid account update request |
| Update | 403 | `update_not_allowed` | Incoming Update permission disabled |
| Update | 404 | `user_not_found` | Account is missing and Create is not permitted |
| Update | 422 | `user_update_failed` | Account update failed for another reason |
| Create / Update | 403 | `role_sync_disabled` | Role synchronization is disabled |
| Create / Update | 422 | `role_not_allowed` | A requested role does not exist or is not permitted |
| Delete | 400 | `invalid_delete_request` | Invalid deletion request |
| Delete | 403 | `delete_not_allowed` | Incoming Delete permission disabled |
| Delete | 422 | `user_deletion_failed` | Account could not be deleted |
| Delete | 422 | `reassignment_user_not_found` | Account deleted; requested reassignment account missing |
| Password | 400 | `invalid_password_request` | Invalid password request |
| Password | 403 | `password_not_allowed` | Incoming Password permission disabled |
| Metadata | 400 / 422 | `invalid_metadata_request` | Invalid metadata request or no valid changes to process |
| Metadata | 403 | `metadata_not_allowed` | Incoming Metadata permission disabled |
| Metadata | 403 | `metadata_not_configured` | No incoming metadata keys configured |
| Metadata | 422 | `metadata_key_invalid` | Metadata key has an invalid name |
| Metadata | 422 | `metadata_key_not_allowed` | Metadata key is protected or not permitted |
| Metadata | 422 | `metadata_change_failed` | A metadata addition, update or deletion failed |
| Metadata | 422 | `metadata_partial_failure` | Some metadata changes succeeded and others failed |
| Logout POST | 400 | `invalid_logout_request` | Invalid logout request |
| Logout POST | 403 | `logout_not_allowed` | Incoming Logout permission disabled |
| Logout POST | 409 | `already_logged_out` | The receiving session is already logged out |
| Logout POST | 409 | `session_mismatch` | The receiving session belongs to another account |
| Administrative | 400 | `wprus_invalid_admin_request` | Invalid administrative request |
| Administrative | 400 | `wprus_unknown_admin_action` | Unrecognized administrative action |
| Administrative | 400 | `wprus_invalid_ping` | Invalid connection test |
| Administrative | 403 | `wprus_ping_disabled` | The tested action or direction is disabled |
| Administrative | 400 | `wprus_invalid_outgoing_meta_request` | Invalid outgoing metadata request |
| Administrative | 400 | `wprus_unknown_outgoing_meta_site` | Requesting site is not configured |
| Administrative | 500 | `wprus_admin_no_result` | Administrative action returned no result |

`user_not_found` (HTTP 404) is also returned by Delete, Password, Metadata and POST Logout when the account cannot be found. The shared `identity_mismatch` error applies to an existing account across the account-changing POST operations. **Browser GET login and logout return HTTP statuses rather than JSON error codes:** invalid input returns 400, a disabled operation returns 403, a missing account returns 404, and an identity or session conflict returns 409. When the request requires a redirect, the browser can instead receive HTTP 303 with a destination URL.

### Role changes after the account was saved

When Create or Update saves the account before a role error, both `role_sync_disabled` and `role_not_allowed` include:

```json
{
  "success": false,
  "data": {
    "code": "role_not_allowed",
    "details": {
      "account_saved": true,
      "applied_roles": ["editor"],
      "rejected_roles": ["administrator"]
    }
  }
}
```

`applied_roles` lists accepted changes and `rejected_roles` lists rejected role names. The account and accepted roles have already been saved. Do not resend the entire request without checking its effects.

### Deletion with unsuccessful reassignment

A missing reassignment account returns:

```json
{
  "success": false,
  "data": {
    "code": "reassignment_user_not_found",
    "details": {
      "account_deleted": true,
      "content_reassigned": false
    }
  }
}
```

The account has been deleted. The requested content transfer did not take place. Do not repeat the Delete request as if the account still exists.

### Metadata with rejected entries

For metadata changes, the response describes the number of applied changes and identifies each rejection:

```json
{
  "success": false,
  "data": {
    "code": "metadata_partial_failure",
    "details": {
      "applied": 2,
      "rejected": [
        {
          "operation": "add",
          "key": "restricted_key",
          "code": "metadata_key_not_allowed"
        }
      ]
    }
  }
}
```

The `applied` value is the number of successful metadata operations, not the number of keys. `rejected` lists entries with `operation`, `key` and `code`. When none succeeded, the top-level `code` identifies the first rejected entry, and the full `rejected` list remains available.

Use the returned error code and details to decide whether to correct the request, update local records or retry. Account creation, account deletion and metadata additions must not be retried blindly after an uncertain result.
