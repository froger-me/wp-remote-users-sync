# Test coverage in progress

The following checks are present and have been run using the separate WordPress test site and browser test tools.

| Feature | Checks currently present |
| --- | --- |
| Encryption | Round trip, random values, wrong signature, binary encoding. |
| Create | Profile creation, unwanted role and metadata, supplied ID, duplicate login, disabled action. |
| Update | Existing login, correct target, unwanted ID/role/metadata, missing user with Create on and off, disabled action. |
| Delete | Delete with reassignment, disabled action. |
| Password | Valid change, disabled action, Update with Password disabled. |
| Role | Accepted, disallowed, and disabled incoming role changes. |
| Metadata | Add/update/delete, selected keys, default exclusions, protected permission fields after filters are emptied. |
| Login and Logout | Incoming login and disabled login; mismatched and missing logout users. |
| Tokens | Reusable and single-use tokens, missing and unknown values. |
| Settings | Incoming/outgoing selection, peer lookup, default metadata exclusions. |
| Multisite | Another site's capability and user-level fields after filters are emptied. |
| Outgoing communication | Create request, token request, encrypted data, and independent simulated user. |
| Browser | Settings tabs and editor access; incoming Update and Create over HTTP; invalid tokens; subscriber access to logs and connection tests; administrator log refresh and clear; user creation sent to the simulated site. |

Additional checks cover outgoing actions, session and redirect browser flows, connection tests, activity logs, plugin removal, and invalid requests. These tests do not yet cover every feature and edge case.
