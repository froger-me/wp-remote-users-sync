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

**Interpreting the response:**
- Check `data.username_exists` and `data.email_exists` separately. Both must be `false` before creating an account.
- Treat a failed identity check as unavailable, not as confirmation that the identifiers are free.

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | `invalid_identity_request` | Request is invalid or the sending connection is not recognized |
| 403 | `invalid_token`, `ip_not_allowed`, `method_not_allowed` | Request authorization failed |

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

For email changes:
- `username`: current login name.
- `identity_email`: previous email address.
- `user_email`: new email address.

**Identity error:** HTTP **409**, `identity_mismatch` — the login exists, but the supplied identifying email is missing or does not match. The operation is rejected before any changes are made.

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

**Success:** HTTP 200, `{"success":true,"data":true}`.

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | `invalid_create_request` | Invalid request |
| 403 | `create_not_allowed` | Incoming Create is disabled |
| 409 | `existing_user_login` | Login already exists |
| 409 | `existing_user_email` | Email already exists |
| 422 | `user_creation_failed` | Account creation failed for another reason |

Role errors are listed under [Roles in Create and Update](#roles-in-create-and-update). If role processing fails after the account is created, `details.account_saved` is `true`.

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

If the account does not exist, Update creates it only when incoming Create is permitted.

**Success:** HTTP 200, `{"success":true,"data":true}`.

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | `invalid_update_request` | Invalid request |
| 403 | `update_not_allowed` | Incoming Update is disabled |
| 404 | `user_not_found` | Account does not exist and incoming Create is disabled |
| 409 | `existing_user_login` | Login conflict |
| 409 | `existing_user_email` | Email conflict |
| 422 | `user_update_failed` | Update failed for another reason |

Role errors are listed below. A role error can occur after the account changes have already been saved; `details.account_saved` is then `true`.

### Roles in Create and Update

```json
{"roles":{"set":"subscriber","add":["customer"]}}
```

**Role fields:**
- `set`: role to set; `false` when unused.
- `add`: array of roles to add; `false` when unused.

| HTTP | Error code | Condition |
| --- | --- | --- |
| 403 | `role_sync_disabled` | Incoming role synchronization is disabled |
| 422 | `role_not_allowed` | A requested role does not exist or is not permitted |

**Both errors include these details:**

| Field | Meaning |
| --- | --- |
| `account_saved` | `true` — the account was created or updated |
| `applied_roles` | Roles successfully set or added |
| `rejected_roles` | Roles that were rejected |

Account changes and accepted roles are not rolled back when another role is rejected.

## Delete: POST /wprus/delete/

```json
{
  "username": "example-user",
  "identity_email": "user@example.net",
  "base_url": "https://sender.example",
  "reassign_username": "content-owner"
}
```

`reassign_username` identifies the account that receives the deleted account's content. Use `false` if no reassignment is requested.

**Success:** HTTP 200, `{"success":true,"data":true}`.

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | `invalid_delete_request` | Invalid request |
| 403 | `delete_not_allowed` | Incoming Delete is disabled |
| 404 | `user_not_found` | Account to delete does not exist |
| 422 | `user_deletion_failed` | Account deletion failed |
| 422 | `reassignment_user_not_found` | Account was deleted, but the requested reassignment account does not exist |

For `reassignment_user_not_found`:
- `details.account_deleted` = `true`
- `details.content_reassigned` = `false`

**Do not repeat the deletion:** the account has already been deleted.

## Password: POST /wprus/password/

```json
{
  "username": "example-user",
  "identity_email": "user@example.net",
  "base_url": "https://sender.example",
  "user_pass": "example-new-password"
}
```

`user_pass` contains the new password **inside the encrypted JSON message**. Never send the password outside `wprusdata`.

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | `invalid_password_request` | Invalid request |
| 403 | `password_not_allowed` | Incoming Password is disabled |
| 404 | `user_not_found` | Account does not exist |

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

Metadata keys must be permitted by the receiver and contain only lowercase letters, digits, underscores or hyphens.

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | `invalid_metadata_request` | Malformed request |
| 403 | `metadata_not_allowed` | Incoming Metadata is disabled |
| 403 | `metadata_not_configured` | No incoming metadata keys are configured |
| 404 | `user_not_found` | Account does not exist |
| 422 | `metadata_key_invalid` | Metadata key name is invalid; no entries were applied |
| 422 | `metadata_key_not_allowed` | Metadata key is protected or not permitted; no entries were applied |
| 422 | `metadata_change_failed` | Metadata change failed; no entries were applied |
| 422 | `metadata_partial_failure` | At least one entry was applied and at least one was rejected |
| 422 | `invalid_metadata_request` | No entries were applied and no individual entries were rejected |

**For metadata entry errors (HTTP 422):**
- `data.details.applied`: number of successful changes.
- `data.details.rejected`: array of rejected entries, each with `operation`, `key` and `code`.
- When none succeeded, `data.code` identifies the **first** rejection; the full list remains in `details.rejected`.

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

**Connection test results:**

| HTTP | Code | Condition |
| --- | --- | --- |
| 200 | — | Allowed: `{"success":true,"data":"..."}` |
| 400 | `wprus_invalid_ping` | Invalid test request |
| 403 | `wprus_ping_disabled` | Tested action or direction is disabled |

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

**Administrative errors:**

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | `wprus_invalid_admin_request` | Invalid request fields |
| 400 | `wprus_unknown_admin_action` | Unknown administrative action |
| 400 | `wprus_invalid_outgoing_meta_request` | Invalid outgoing metadata arguments |
| 400 | `wprus_unknown_outgoing_meta_site` | Sending site is not configured |
| 500 | `wprus_admin_no_result` | Operation returned no result |

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

**POST logout results:**

| HTTP | Error code | Condition |
| --- | --- | --- |
| 200 | — | Success: `{"success":true,"data":true}` |
| 400 | `invalid_logout_request` | Invalid request |
| 403 | `logout_not_allowed` | Incoming Logout is disabled |
| 404 | `user_not_found` | Account does not exist |
| 409 | `already_logged_out` | No active session |
| 409 | `session_mismatch` | Active session belongs to another account |

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

| Response field | Purpose |
| --- | --- |
| `data.code` | Required error identifier; use this to determine the cause |
| `data.message` | Optional human-readable explanation |
| `data.details` | Optional information about changes already completed |

### Errors shared by operations

| HTTP | Error code | Meaning |
| --- | --- | --- |
| 403 | `invalid_token` | The supplied token is missing, invalid or expired |
| 403 | `ip_not_allowed` | The sender's IP address is not permitted |
| 403 | `method_not_allowed` | The request method or its authorization was not accepted |
| 409 | `identity_mismatch` | An existing account's login and identifying email do not match the request |

**Request validation order:**
1. Token and sender authorization are checked.
2. Existing-account identity is checked.
3. The requested operation is processed.

Browser GET requests return HTTP status codes or redirects, not these JSON error objects.

### Error codes by operation

Each operation lists its error codes, HTTP statuses and failure conditions alongside its request example.

| Operation | Error reference |
| --- | --- |
| Token | [Token](#token-post-wprustoken) |
| Identity | [Identity](#identity-post-wprusidentity) |
| Create | [Create](#create-post-wpruscreate) |
| Update | [Update](#update-post-wprusupdate) |
| Roles | [Roles in Create and Update](#roles-in-create-and-update) |
| Delete | [Delete](#delete-post-wprusdelete) |
| Password | [Password](#password-post-wpruspassword) |
| Metadata | [Metadata](#metadata-post-wprusmeta) |
| Connection test | [Check a connection](#check-a-connection) |
| Administration | [List outgoing metadata keys](#list-outgoing-metadata-keys) |
| Logout POST | [Browser logout](#browser-logout-get-wpruslogout-or-post-wpruslogout) |

**Additional cases:**
- `user_not_found` (HTTP 404): also applies to Delete, Password, Metadata and POST Logout.
- `identity_mismatch` (HTTP 409): applies to an existing account across account-changing POST operations.

**Browser GET login and logout:**

| HTTP | Condition |
| --- | --- |
| 400 | Invalid request |
| 403 | Operation not permitted |
| 404 | Account missing |
| 409 | Identity or session conflict |
| 303 | Redirect to the browser destination when required |

Browser GET responses do not use JSON error codes.

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

- `account_saved: true`: account creation or update completed.
- `applied_roles`: roles accepted and applied.
- `rejected_roles`: roles not accepted.
- **Retry:** Check the saved account and roles before sending another request.

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

- `account_deleted: true`: the account no longer exists.
- `content_reassigned: false`: the requested content transfer failed.
- **Retry:** Do not send Delete again for that account.

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

- `details.applied`: number of successful metadata operations, **not** the number of keys.
- `details.rejected`: array of rejected operations; each entry contains `operation`, `key` and `code`.
- If nothing succeeded, `data.code` is the first rejection's code; the full list remains in `details.rejected`.
- **Retry:** Correct rejected entries without repeating successful changes.

For any uncertain result, check the existing data before retrying account creation, deletion or metadata additions.
