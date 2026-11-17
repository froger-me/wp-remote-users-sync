# Operations and responses

All paths are relative to the receiving site's full HTTPS base URL. The examples under **Request** show the JSON **before encryption**. For server-to-server requests, send `Content-Type: application/json` with an outer JSON body containing `wprusdata` and `token`. Token requests use only `wprusdata`. Browser GET requests carry the encrypted `wprusdata` and single-use `token` as URL query parameters.

## Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `POST` | `/wprus/token/` | Request a token before calling a server operation or opening a browser login or logout URL. |
| `POST` | `/wprus/identity/` | Check whether either identifier is already used before creating an account. |
| `POST` | `/wprus/create/` | Create an account on the receiving site. |
| `POST` | `/wprus/update/` | Update an existing account or create it when the incoming Create action is also permitted. |
| `POST` | `/wprus/delete/` | Delete an account, optionally transferring its content to another account. |
| `POST` | `/wprus/password/` | Set an existing account's password. |
| `POST` | `/wprus/meta/` | Add, change or delete approved account metadata. |
| `POST` | `/wprus/admin/` | Compare every incoming and outgoing Action setting between two sites. |
| `POST` | `/wprus/admin/` | Retrieve metadata keys the receiving site is configured to send to the requesting site. |
| `GET` | `/wprus/login/` | Establish a session in the visiting user's browser. |
| `GET` | `/wprus/logout/` | End a session in the visiting user's browser. |
| `POST` | `/wprus/logout/` | Apply a logout notification to the receiving session. |

## Error responses and retries

- Server-to-server errors return `{"success":false,"data":{"code":"specific_error_code"}}`.
- `data.code` identifies the failure; `data.message` is an optional explanation.
- `data.details` contains any information about changes completed before an error.
- Unauthorized POST requests return HTTP 403 with `invalid_token`, `ip_not_allowed` or `method_not_allowed`.
- Existing-account identity mismatches return HTTP 409 with `identity_mismatch` before the operation runs.
- For browser GET requests, inspect HTTP status and browser redirects; they do not return the JSON error codes shown for POST.
- Before retrying, check partial changes reported in `data.details`.

**Partial-success details:**

| Error | Details | Action |
| --- | --- | --- |
| `role_not_allowed` / `role_sync_disabled` | `details.account_saved` is `true`; `details.applied_roles` lists applied roles; `details.rejected_roles` lists rejected roles | Check the saved account and roles before retrying |
| `reassignment_user_not_found` | `details.account_deleted` is `true`; `details.content_reassigned` is `false` | Do not retry deletion; the account is already deleted |
| `metadata_partial_failure` / rejected metadata entries | `details.applied` counts successful changes; `details.rejected` identifies each rejected `operation`, `key` and `code` | Retry only unsuccessful entries, if appropriate |

## Token: POST /wprus/token/

Request a token before calling a server operation or opening a browser login or logout URL.

### Request

```json
{
  "action": "identity",
  "method": "post",
  "base_url": "https://receiver.example"
}
```

### Fields

| Field | Required | Description |
| --- | --- | --- |
| `action` | Optional | Operation name, such as `identity` or `login`; used to identify the request |
| `method` | Yes | `post` for server operations; `get` for browser login or logout |
| `base_url` | Yes | The **receiving** site's full URL |

### Success

| HTTP | Response | Meaning |
| --- | --- | --- |
| 200 | `{"nonce":"issued-token","expiry":1795000000}` | Token and expiration time as a Unix timestamp |

### Errors

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | `invalid_token_request` | Malformed token request |
| 500 | `token_creation_failed` | Could not issue a token |

## Identity: POST /wprus/identity/

Check whether either identifier is already used before creating an account. The receiver must check its own account records independently for both identifiers; if the check cannot be completed, return an error rather than reporting an identifier as available.

### Request

```json
{
  "username": "example-user",
  "user_email": "user@example.net",
  "base_url": "https://sender.example"
}
```

### Fields

| Field | Required | Description |
| --- | --- | --- |
| `username` | Yes | Account login name at the receiving site |
| `user_email` | Yes | Email address to check |
| `base_url` | Yes | Registered sender URL (not the token request's receiver URL) |

### Success

| HTTP | Response | Meaning |
| --- | --- | --- |
| 200 | `{"success":true,"data":{"username_exists":false,"email_exists":false}}` | Each boolean reports existence independently; both must be false to allow creation |

### Errors

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | `invalid_identity_request` | Missing or invalid username, email or registered sender |
| 403 | `invalid_token` | Missing, invalid or expired token |
| 403 | `ip_not_allowed` | Sending IP address not permitted |
| 403 | `method_not_allowed` | Request method or authorization rejected |

## Create: POST /wprus/create/

Create an account on the receiving site.

### Request

```json
{
  "username": "example-user",
  "base_url": "https://sender.example",
  "user_email": "user@example.net",
  "display_name": "Example User",
  "first_name": "Example",
  "last_name": "User",
  "roles": {
    "set": "subscriber",
    "add": false
  }
}
```

### Fields

| Field | Required | Description |
| --- | --- | --- |
| `username` | Yes | Account login name at the receiving site |
| `base_url` | Yes | Registered sender URL (not the token request's receiver URL) |
| `user_email` | Yes | Email address of the new account |
| `Profile fields` | Optional | `display_name`, `first_name`, `last_name`, `nickname`, `user_nicename`, `user_url`, `description`, `locale` and other allowed profile settings; see `UserFields` in the [OpenAPI specification](openapi.yaml) |
| `user_pass` | Optional | Password inside encrypted message; requires password permission |
| `roles` | Optional | Object with `set` (role name or false) and `add` (list of role names or false); requires role permission |

### Success

| HTTP | Response | Meaning |
| --- | --- | --- |
| 200 | `{"success":true,"data":true}` | Requested operation completed |

### Errors

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | `invalid_create_request` | Invalid request fields |
| 403 | `invalid_token` | Missing, invalid or expired token |
| 403 | `ip_not_allowed` | Sending IP address not permitted |
| 403 | `method_not_allowed` | Request method or authorization rejected |
| 403 | `create_not_allowed` | Incoming Create disabled |
| 403 | `role_sync_disabled` | Account saved; role synchronization disabled. Check `details.account_saved`, `details.applied_roles`, and `details.rejected_roles`. |
| 409 | `identity_mismatch` | Login and identifying email do not match an existing account |
| 409 | `existing_user_login` | Login already exists |
| 409 | `existing_user_email` | Email already exists |
| 422 | `user_creation_failed` | Account creation failed for another reason |
| 422 | `role_not_allowed` | Account saved; one or more requested roles rejected. Check `details.account_saved`, `details.applied_roles`, and `details.rejected_roles`. |

## Update: POST /wprus/update/

Update an existing account or create it when the incoming Create action is also permitted.

### Request

```json
{
  "username": "example-user",
  "identity_email": "old@example.net",
  "base_url": "https://sender.example",
  "user_email": "new@example.net",
  "display_name": "Updated Example"
}
```

### Fields

| Field | Required | Description |
| --- | --- | --- |
| `username` | Yes | Account login name at the receiving site |
| `identity_email` | For an existing account | Current account email on the receiver; previous email when changing address |
| `base_url` | Yes | Registered sender URL (not the token request's receiver URL) |
| `Profile fields` | Optional | Any accepted profile field, such as `user_email` for a new email address, `display_name` or `user_activation_key`; see `UserFields` in the [OpenAPI specification](openapi.yaml) |
| `user_pass` | Optional | New password; requires password permission |
| `roles` | Optional | Object with `set` and `add`; requires role permission |

### Success

| HTTP | Response | Meaning |
| --- | --- | --- |
| 200 | `{"success":true,"data":true}` | Requested operation completed |

### Errors

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | `invalid_update_request` | Invalid request fields |
| 403 | `invalid_token` | Missing, invalid or expired token |
| 403 | `ip_not_allowed` | Sending IP address not permitted |
| 403 | `method_not_allowed` | Request method or authorization rejected |
| 403 | `update_not_allowed` | Incoming Update disabled |
| 403 | `role_sync_disabled` | Account saved; role synchronization disabled. Check `details.account_saved`, `details.applied_roles`, and `details.rejected_roles`. |
| 404 | `user_not_found` | Account missing and incoming Create disabled |
| 409 | `identity_mismatch` | Login and identifying email do not match an existing account |
| 409 | `existing_user_login` | Login already exists |
| 409 | `existing_user_email` | Email already exists |
| 422 | `user_update_failed` | Update failed for another reason |
| 422 | `role_not_allowed` | Account saved; one or more requested roles rejected. Check `details.account_saved`, `details.applied_roles`, and `details.rejected_roles`. |

## Delete: POST /wprus/delete/

Delete an account, optionally transferring its content to another account.

### Request

```json
{
  "username": "example-user",
  "identity_email": "user@example.net",
  "base_url": "https://sender.example",
  "reassign_username": "content-owner"
}
```

### Fields

| Field | Required | Description |
| --- | --- | --- |
| `username` | Yes | Account login name at the receiving site |
| `identity_email` | For an existing account | Current account email on the receiver; previous email when changing address |
| `base_url` | Yes | Registered sender URL (not the token request's receiver URL) |
| `reassign_username` | Optional | Existing login to receive the deleted account's content; `false` means no reassignment |

### Success

| HTTP | Response | Meaning |
| --- | --- | --- |
| 200 | `{"success":true,"data":true}` | Requested operation completed |

### Errors

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | `invalid_delete_request` | Invalid request |
| 403 | `invalid_token` | Missing, invalid or expired token |
| 403 | `ip_not_allowed` | Sending IP address not permitted |
| 403 | `method_not_allowed` | Request method or authorization rejected |
| 403 | `delete_not_allowed` | Incoming Delete disabled |
| 404 | `user_not_found` | Account to delete does not exist |
| 409 | `identity_mismatch` | Login and identifying email do not match an existing account |
| 422 | `user_deletion_failed` | Account deletion failed |
| 422 | `reassignment_user_not_found` | Account deleted; reassignment account not found. `details.account_deleted` is `true`; `details.content_reassigned` is `false`. |

## Password: POST /wprus/password/

Set an existing account's password.

### Request

```json
{
  "username": "example-user",
  "identity_email": "user@example.net",
  "base_url": "https://sender.example",
  "user_pass": "example-new-password"
}
```

### Fields

| Field | Required | Description |
| --- | --- | --- |
| `username` | Yes | Account login name at the receiving site |
| `identity_email` | For an existing account | Current account email on the receiver; previous email when changing address |
| `base_url` | Yes | Registered sender URL (not the token request's receiver URL) |
| `user_pass` | Yes | New password, **only inside** the encrypted message; do not log or send it in the outer HTTP body |

### Success

| HTTP | Response | Meaning |
| --- | --- | --- |
| 200 | `{"success":true,"data":true}` | Requested operation completed |

### Errors

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | `invalid_password_request` | Invalid request |
| 403 | `invalid_token` | Missing, invalid or expired token |
| 403 | `ip_not_allowed` | Sending IP address not permitted |
| 403 | `method_not_allowed` | Request method or authorization rejected |
| 403 | `password_not_allowed` | Incoming Password disabled |
| 404 | `user_not_found` | Account does not exist |
| 409 | `identity_mismatch` | Login and identifying email do not match an existing account |

## Metadata: POST /wprus/meta/

Add, change or delete approved account metadata.

### Request

```json
{
  "username": "example-user",
  "identity_email": "user@example.net",
  "base_url": "https://sender.example",
  "add": {
    "public_note": [
      {
        "meta_value": "First value",
        "unique": false
      }
    ]
  },
  "update": {
    "public_note": [
      {
        "meta_value": "Second value",
        "prev_value": "First value"
      }
    ]
  },
  "delete": {
    "public_note": [
      {
        "meta_value": "Second value",
        "delete_all": false
      }
    ]
  }
}
```

### Fields

| Field | Required | Description |
| --- | --- | --- |
| `username` | Yes | Account login name at the receiving site |
| `identity_email` | For an existing account | Current account email on the receiver; previous email when changing address |
| `base_url` | Yes | Registered sender URL (not the token request's receiver URL) |
| `add` | Optional | Map of metadata keys to arrays of entries with `meta_value` and `unique` (boolean) |
| `update` | Optional | Map of metadata keys to arrays of entries with `meta_value` and `prev_value` |
| `delete` | Optional | Map of metadata keys to arrays of entries with `meta_value` and `delete_all` (boolean) |
| `Metadata keys` | For each entry | Only lowercase letters, digits, underscores and hyphens; key must also be allowed by the receiver |

### Success

| HTTP | Response | Meaning |
| --- | --- | --- |
| 200 | `{"success":true,"data":true}` | Requested operation completed |

### Errors

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | `invalid_metadata_request` | Invalid request |
| 403 | `invalid_token` | Missing, invalid or expired token |
| 403 | `ip_not_allowed` | Sending IP address not permitted |
| 403 | `method_not_allowed` | Request method or authorization rejected |
| 403 | `metadata_not_allowed` | Incoming Metadata disabled |
| 403 | `metadata_not_configured` | No allowed incoming metadata keys configured |
| 404 | `user_not_found` | Account does not exist |
| 409 | `identity_mismatch` | Login and identifying email do not match an existing account |
| 422 | `metadata_key_invalid` | Invalid metadata key; no changes succeeded |
| 422 | `metadata_key_not_allowed` | Protected or unapproved key; no changes succeeded |
| 422 | `metadata_change_failed` | Add, update or delete failed; no changes succeeded |
| 422 | `metadata_partial_failure` | Some entries applied and some rejected |
| 422 | `invalid_metadata_request` | Nothing applied and no individual rejection recorded |

## Administration: connection test — POST /wprus/admin/

Compare every incoming and outgoing Action setting between two connected sites. The receiver compares the sender's incoming settings with its own outgoing settings, and the sender's outgoing settings with its own incoming settings. Reject the request if `base_url` and `data.base_url` do not identify the same registered sender.

### Request

```json
{
  "username": "example-user",
  "base_url": "https://sender.example",
  "action": "test",
  "data": {
    "base_url": "https://sender.example",
    "actions": {
      "incoming": {
        "login": true,
        "create": true
      },
      "outgoing": {
        "logout": true,
        "update": false
      }
    }
  }
}
```

### Fields

| Field | Required | Description |
| --- | --- | --- |
| `username` | Yes | Account login name at the receiving site |
| `base_url` | Yes | Registered sender URL (not the token request's receiver URL) |
| `action` | Yes | `test` |
| `data.base_url` | Yes | Same registered sender URL as the top-level `base_url` |
| `data.actions.incoming` | Yes | Object containing the sender's incoming Action names and their enabled or disabled values |
| `data.actions.outgoing` | Yes | Object containing the sender's outgoing Action names and their enabled or disabled values |

### Success

| HTTP | Response | Meaning |
| --- | --- | --- |
| 200 | `{"success":true,"data":{"failures":[]}}` | Every corresponding Action setting matches |

### Errors

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | `wprus_invalid_admin_request` | Invalid top-level request |
| 400 | `wprus_invalid_test` | Missing site or Action settings |
| 400 | `wprus_unknown_admin_action` | Unknown action name |
| 403 | `invalid_token` | Missing, invalid or expired token |
| 403 | `ip_not_allowed` | Sending IP address not permitted |
| 403 | `method_not_allowed` | Request method or authorization rejected |
| 409 | `wprus_configuration_mismatch` | One or more corresponding Action settings differ; `details.failures` lists every difference |
| 500 | `wprus_admin_no_result` | Administrative request returned no result |

## Administration: outgoing metadata — POST /wprus/admin/

Retrieve metadata keys the receiving site is configured to send to the requesting site. Reject the request if `base_url` and `data.base_url` do not identify the same registered sender.

### Request

```json
{
  "username": "example-user",
  "base_url": "https://sender.example",
  "action": "outgoing_meta",
  "data": {
    "base_url": "https://sender.example"
  }
}
```

### Fields

| Field | Required | Description |
| --- | --- | --- |
| `username` | Yes | Account login name at the receiving site |
| `base_url` | Yes | Registered sender URL (not the token request's receiver URL) |
| `action` | Yes | `outgoing_meta` |
| `data.base_url` | Yes | Same registered sender URL as top-level `base_url` |

### Success

| HTTP | Response | Meaning |
| --- | --- | --- |
| 200 | `{"success":true,"data":["public_note","customer_preference"]}` | Array of metadata keys; an empty array means none configured |

### Errors

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | `wprus_invalid_admin_request` | Invalid top-level request or mismatched sender URLs |
| 400 | `wprus_unknown_admin_action` | Unknown action name |
| 400 | `wprus_invalid_outgoing_meta_request` | Missing or invalid nested sender URL |
| 400 | `wprus_unknown_outgoing_meta_site` | Nested sender is not configured |
| 403 | `invalid_token` | Missing, invalid or expired token |
| 403 | `ip_not_allowed` | Sending IP address not permitted |
| 403 | `method_not_allowed` | Request method or authorization rejected |
| 500 | `wprus_admin_no_result` | Administrative request returned no result |

## Browser login: GET /wprus/login/

Establish a session in the visiting user's browser.

### Request

```json
{
  "username": "example-user",
  "identity_email": "user@example.net",
  "base_url": "https://sender.example",
  "remember": true,
  "callback_url": "https://receiver.example/account"
}
```

### Fields

| Field | Required | Description |
| --- | --- | --- |
| `username` | Yes | Account login name at the receiving site |
| `identity_email` | For an existing account | Current account email on the receiver; previous email when changing address |
| `base_url` | Yes | Registered sender URL (not the token request's receiver URL) |
| `remember` | Optional | Whether the browser should remember the login |
| `callback_url` | When a redirect is required | Full browser destination URL |

### Success

| HTTP | Response | Meaning |
| --- | --- | --- |
| 200 | Browser response; session cookie set | Login handled without a redirect |
| 303 | Redirect response with `Location` header | Browser navigates to the destination; may also occur when the request is not successful |

### Errors

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | — | Invalid request or required redirect URL missing |
| 403 | — | Invalid token, blocked or disabled login |
| 404 | — | Account not found during processing |
| 409 | — | Existing-account identity mismatch |

## Browser logout: GET /wprus/logout/

End a session in the visiting user's browser.

### Request

```json
{
  "username": "example-user",
  "identity_email": "user@example.net",
  "base_url": "https://sender.example"
}
```

### Fields

| Field | Required | Description |
| --- | --- | --- |
| `username` | Yes | Account login name at the receiving site |
| `identity_email` | For an existing account | Current account email on the receiver; previous email when changing address |
| `base_url` | Yes | Registered sender URL (not the token request's receiver URL) |

### Success

| HTTP | Response | Meaning |
| --- | --- | --- |
| 200 | Browser response; session updated | Logout completed without a redirect |
| 303 | Redirect response with `Location` header | Browser navigates to the destination; may also occur when the request is not successful |

### Errors

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | — | Invalid request |
| 403 | — | Invalid token, blocked or disabled logout |
| 404 | — | Account not found during processing |
| 409 | — | Identity mismatch, already logged out or active session belongs to another account |

## Logout notification: POST /wprus/logout/

Apply a logout notification to the receiving session.

### Request

```json
{
  "username": "example-user",
  "identity_email": "user@example.net",
  "base_url": "https://sender.example"
}
```

### Fields

| Field | Required | Description |
| --- | --- | --- |
| `username` | Yes | Account login name at the receiving site |
| `identity_email` | For an existing account | Current account email on the receiver; previous email when changing address |
| `base_url` | Yes | Registered sender URL (not the token request's receiver URL) |

### Success

| HTTP | Response | Meaning |
| --- | --- | --- |
| 200 | `{"success":true,"data":true}` | Requested operation completed |

### Errors

| HTTP | Error code | Condition |
| --- | --- | --- |
| 400 | `invalid_logout_request` | Invalid request |
| 403 | `invalid_token` | Missing, invalid or expired token |
| 403 | `ip_not_allowed` | Sending IP address not permitted |
| 403 | `method_not_allowed` | Request method or authorization rejected |
| 403 | `logout_not_allowed` | Incoming Logout disabled |
| 404 | `user_not_found` | Account does not exist |
| 409 | `identity_mismatch` | Login and identifying email do not match an existing account |
| 409 | `already_logged_out` | No active receiving session |
| 409 | `session_mismatch` | Active session belongs to another account |
