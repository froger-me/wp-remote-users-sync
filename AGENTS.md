# WP Remote Users Sync

## Overview

This directory contains the complete WP Remote Users Sync WordPress plugin. It keeps separate WordPress user accounts aligned across trusted sites while leaving each site responsible for its own users, settings, roles, metadata, and browser sessions.

`wprus.php` is the plugin entry point. It defines the plugin paths, loads the required files, registers activation, deactivation, and removal, and creates the settings, logging, integration, and request-handling objects. Keep this file limited to that coordination work.

All sites connected through WPRUS must run the same plugin version. A change to the request format, security checks, response format, available actions, or account identity rules must be treated as a change to every connected site.

Preserve user changes already present in the working tree. Read the surrounding code before editing, make the smallest complete change, and do not reorganize unrelated files.

## Architecture

The plugin uses explicit file loading and WordPress actions and filters. It does not use a Composer loader for production classes.

- `wprus.php` starts the plugin and creates the main objects after `plugins_loaded`.
- `Wprus_Settings` owns the saved settings, their defaults and cleaning rules, the settings page, templates, and administrator assets.
- `Wprus` owns request addresses, WordPress rewrite rules, request routing, plugin activation, and plugin database tables.
- `Wprus_Api_Abstract` owns the common encryption, security token, sending, receiving, authorization, and JSON response behavior.
- Classes in `inc/api/` implement individual user operations. Keep operation-specific validation and account changes in the matching class.
- Classes in `inc/api/admin` implement the admin orchestrator and individual admin operations. Keep operation-specific validation and specific operation logic in the matching class.
- `Wprus_Api_Identity` performs account identity checks used before account creation and before changes to an existing account.
- `Wprus_Api_Admin` handles administrative communication between connected sites, including connection checks and metadata-setting requests.
- `Wprus_Logger`, `Wprus_Nonce`, and `Wprus_Crypto` own activity records, security tokens, and encrypted message handling respectively.
- `Wprus_Integration` loads integrations registered in its public static `$integrations` array and passes the main plugin objects to them after startup.

Keep these responsibilities separate. Reuse the common request and security methods instead of copying them into individual operation classes. Add public extension points through clearly named WordPress actions or filters only when another plugin has a real need to alter the behavior.

Use the existing `Wprus_` class prefix, `wprus_` function and hook prefix, `WPRUS_` constant prefix, `wprus` translation domain, and the existing WordPress PHP coding style. Prefer direct modern PHP calls. Do not introduce `call_user_func()` or use `is_callable()` as a substitute for a clear method or function check and a direct call.
Always respect WordPress Coding Standards.

## Planning and implementation rules

When writing a plan:

- List every file to create, every file to update, and every file to delete. State that a list is empty when it has no entries.
- Include the critical code snippets that define required method and function signatures, data fields, order of operations, or other behavior that must be implemented exactly.
- Describe the concrete architecture: name the responsible classes or files, explain how they work together, and state where data enters, changes, and leaves.
- Define acceptance criteria and clear exit conditions that can be verified when the work is complete.
- Record decisions, not a menu of options. The plan must be directly actionable without leaving important design choices to the implementation stage.
- Focus on the work to perform. Mention work to avoid only when that restriction prevents an unwanted side effect.
- Plan the smallest complete set of changes that achieves the goal with maintainable code. Do not propose broad rewrites or new abstractions that the goal does not require.
- When updating an existing plan file, change only the affected parts. Do not rewrite the complete file unless the user explicitly requests it.

When implementing a plan:

- Read the complete plan before changing any file.
- Follow every stated decision and do not substitute a different design.
- If the plan is internally inconsistent, conflicts with the current code or the user's instructions, or cannot satisfy its own acceptance criteria, do not begin implementation. Explain the inconsistency to the user and wait for direction.
- If an inconsistency becomes apparent after implementation has started, stop immediately, preserve the work already completed, explain the inconsistency to the user, and wait for direction.
- Finish by removing code, files, imports, documentation, and tests made obsolete by the planned change. Keep this cleanup within the plan's scope.

## Database

Store plugin configuration in the `wprus` WordPress option and pass it through `Wprus_Settings`. Add defaults and cleaning rules there so all reads and writes use the same structure. Store the installed plugin version in `wprus_plugin_version`.

Use the existing tables only for data that needs table-level access:

- `wprus_nonce` stores issued security tokens and expiration times.
- `wprus_logs` stores activity log entries.

Create or change these tables through `Wprus::maybe_create_or_upgrade_db()` and WordPress `dbDelta()`. Resolve every plugin table name through `Wprus::get_table()` so single-site, multisite, and network activation use the correct WordPress table prefix. Do not write fixed table prefixes.

Use WordPress user metadata for short-lived browser-operation state that belongs to a user. Use the `wprus` non-persistent cache group only for values that may be recalculated. Use the existing `wprus_flush` transient to request a rewrite-rule refresh; do not create endpoint-specific refresh flags.

Every new stored value needs a clear owner, cleaning on write, safe handling when absent, and removal in `uninstall.php` when it is plugin-owned. Deactivation removes temporary data only. Uninstallation removes plugin options, scheduled events, user metadata, and plugin tables. Keep unrelated WordPress and third-party data untouched.

Never use the working site's database for automated PHP tests. Tests must use a separate disposable WordPress database. Browser tests may use the working test site only through a runner that saves and restores its database and protected files.

## Endpoints

Register request addresses through the existing endpoint lists and `wprus_wp_endpoints`. Let `Wprus::add_endpoints()` build every rewrite rule and let its single rewrite-rule check decide whether WordPress needs to save the complete rule set again. Do not add special rewrite flushing for one address.

Server-to-server requests use HTTP POST with `Content-Type: application/json`. The outer JSON object contains `wprusdata` and, except for token requests, `token`. Do not accept form fields for these requests. Browser-started Login and Logout requests keep their HTTP GET address and query values because they operate on browser cookies.

All remote requests must use the shared encryption, signature, security token, connected-site, allowed-address, and account identity checks. Do not create a second transport path for one operation. The sender's `base_url` must identify an exactly configured connected site, allowing only the normalization already performed by the relevant handler.

Return JSON from every completed server-to-server path. Use the shared response methods and a status that describes the result: successful requests use a 200-range status, malformed requests use 400, denied requests use 403, missing accounts use 404, account or state conflicts use 409, valid requests that cannot be completed use 422, and internal failures use 500. Supply a specific stable error code and useful details when part of a request was already applied. A method that sends a JSON response ends the request; never place required work after that call.

Add a user operation by following the existing `inc/api/class-wprus-api-<name>.php` file and `Wprus_Api_<Name>` class pattern, adding the operation to the enabled endpoint list, and implementing only its local notification hooks and incoming operation behavior. Put an included administrative handler in `inc/api/admin/class-wprus-admin-<name>.php` and name its class `Wprus_Admin_<Name>` so `Wprus_Api_Admin` loads and creates it automatically. Outside code may load handler files during `wprus_require_admin_handler_files`, create their objects during `wprus_init_admin_handlers`, and add their action to the list provided by `wprus_admin_handlers`. Each action receives only its `data` array and returns a result or `WP_Error`.

Any endpoint change must update request validation, response status and error codes, PHP and browser tests, the readable documentation, and the OpenAPI description in the same change.

## Source and Developer Documentation

Write comments and documentation in plain English. Explain the behavior, the reason for a restriction, and the exact shape of data where that information is not clear from the code. Do not add comments that merely repeat a statement.

Use PHPDoc for every class, function, and method, including parameter and return types that match actual behavior. Keep hook documentation aligned with the exact hook name, argument order, and point at which the hook runs.

`README.md` is the developer guide. Keep the action reference in `docs/hooks/actions.md` and the filter reference in `docs/hooks/filters.md`, linked from the guide. Document current behavior, not the history of how that behavior changed. These files describe only behavior and extension points that exist in the current source. They must not contain a changelog, migration history, removed hooks, removed settings, or explanations of behavior kept only for an earlier release.

`readme.txt` is the WordPress.org description. Keep its overview, installation instructions, frequently asked questions, upgrade notice, and changelog concise and accurate. Put release history only in its changelog. Update the in-plugin help when an administrator needs the same information while configuring the plugin.

When a displayed or logged translation string changes, update only the affected entries in `languages/wprus.pot`, including their source references. Do not rebuild the file from every string unless the task explicitly requires a complete translation refresh. Keep translated files untouched unless their translations are also being updated.

Do not describe planned behavior as implemented. Do not retain documentation for removed code merely to explain that it once existed.

## Included classes, assets, and integrations

Place production PHP classes below `inc/` and name class files `class-wprus-<name>.php`. Follow the nearby class layout, direct-access guard, file description, class description, and explicit `require_once` pattern. Add a new abstraction only when several existing classes need the same behavior; do not add a wrapper for a single call.

Keep administrator HTML in `inc/templates/admin/` and front-end output in `inc/templates/`. Prepare and clean data in classes before loading a template. Escape output at the point where it is rendered and verify administrator permissions and WordPress request verification values before processing administrator actions.

Keep first-party administrator CSS in `css/admin/main.css` and JavaScript in `js/admin/main.js`. Keep `main.min.css` and `main.min.js` functionally identical to their readable files. The plugin deliberately loads readable files while WordPress debugging is enabled and minified files otherwise, so both forms are required and both must be tested. Load assets only through the existing `admin_enqueue_scripts()` method, only on the plugin settings page. Pass server values and translated text through the existing localized `WPRUS` JavaScript object. Do not edit bundled Select2 files for plugin-specific behavior.

Register included integrations in `Wprus_Integration::$integrations`. Put an included integration at `inc/integration/<slug>/class-wprus-integration-<slug>.php`, use the class name declared in the array, and let `Wprus_Integration::init()` load it. Do not add a separate registration file or wire an integration directly in `wprus.php`. Third-party code changes the list with `wprus_registered_integrations` and may load its class with `wprus_require_integration_file`. An integration class does not need to extend `Wprus_Integration`; it receives the current plugin objects through the integration hooks.

Keep security-sensitive values out of HTML, JavaScript, ordinary error messages, and logs. Passwords, encryption keys, signature keys, security tokens, and unfiltered remote request bodies must never be exposed.

## Testing & building

Tests describe current supported behavior, not the history of past defects. Remove tests whose only purpose is preserving a removed feature or an earlier request format. Do not add a test merely because a bug once existed. Add such a regression test only when the user explicitly requests it or when it is essential to protect important supported behavior that existing tests cannot cover. Do not add a separate test for every small correction. Extend the existing feature test that already owns the behavior, and add a new test only for a distinct supported feature or an important security boundary.

PHP tests belong in `tests/phpunit/`; browser tests belong in `tests/e2e/`; shared simulated-site data belongs in `tests/support/`. Keep test-only helpers out of production files. Test observable results, saved data, outgoing requests, HTTP status and error codes, permissions, and user-visible behavior rather than private implementation details.

In a WordPress workspace with `.anyape-wp-test-tools`, run its public Composer commands from the WordPress root:

```bash
composer test:plugin -- wp-remote-users-sync
composer test:php -- --filter TestClassName
composer test:e2e -- --profile=plugin wp-remote-users-sync
composer test:e2e -- --grep "test name"
composer test:multisite
composer lint:wpcs
composer test
```

Use the narrowest relevant command while developing. Run the complete plugin PHP tests after a PHP behavior change. Run the focused browser tests for settings, JavaScript, requests made through a browser, and visible results. Run multisite tests for table-prefix, network activation, role, or user-metadata protection changes. Use `composer test` for a broad cross-plugin change or release check. Treat a browser assertion failure and a later database or file restoration failure as separate results.

Without `.anyape-wp-test-tools`, the test suite cannot run as written because its PHP base classes, HTTP replacement, browser imports, fixtures, database separation, and restoration are supplied by that toolkit. Do not invent a test command or change tests to bypass those protections. Run the suite in a separate WordPress workspace that contains the toolkit. Until that environment is available, run PHP syntax checks, the containing project's WordPress coding-style check, and JavaScript syntax checks, then state exactly which PHP or browser tests could not run.

Before completion, run `git diff --check`, check every changed PHP file with `php -l`, and verify that readable and minified assets remain aligned. Do not run broad tests repeatedly when one focused command answers the question.

The release script is the supported packaging path:

```bash
./deploy.sh --help
./deploy.sh <wordpress.org-user> --branch <branch>
```

It runs without publishing by default, checks that the plugin header and `readme.txt` versions match, prepares the release package, and prepares the WordPress.org files. `--deploy` commits, pushes, creates the release, and publishes to WordPress.org; use it only with explicit release authority. Release packages exclude hidden files, shell scripts, `tests/`, and `AGENTS.md`.

## API Documentation

The public communication documentation lives in `docs/api/`:

- `README.md` introduces connection settings and links the reference files.
- `protocol.md` defines encryption, signatures, security tokens, outer request bodies, and browser requests.
- `operations.md` describes each operation's decrypted fields, results, failures, and retry considerations.
- `openapi.yaml` is the OpenAPI 3.1 description of paths, outer request and response bodies, decrypted message shapes, and error responses.
- `client-example*.md` contains implementations in several languages.

Update these files together when the public communication contract changes. The OpenAPI path must show the real HTTP method, outer JSON body, success statuses, failure statuses, stable error codes, and response body. Define reusable fields and responses under `components`. Keep each `operationId` unique.

Use `x-wprus-decrypted-schema` only to describe the JSON encrypted inside `wprusdata`. It is documentation, not a field sent over HTTP and not code read by the plugin. Keep the outer encrypted envelope and the decrypted message clearly separate.

Describe browser Login and Logout as GET requests with query values. Describe every server-to-server request as JSON POST. Keep examples, required identity fields, partial-success details, and retry advice consistent with the PHP handlers. Validate `docs/api/openapi.yaml` as OpenAPI 3.1 and check every documented status and error code against the current source before completing an endpoint change.
