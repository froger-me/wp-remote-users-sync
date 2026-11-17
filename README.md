# WP Remote Users Sync - Synchronise WordPress Users across Multiple Sites

If you run multiple websites and want to keep users separated, but synchronise them automatically and securely for specific user operations, then WP Remote Users Sync is the plugin to use.

All sites connected through WPRUS **must** use the same plugin version.

[Integrating other applications with WPRUS: protocol, OpenAPI specification, and client examples](docs/api/README.md)

* [Overview](#overview)
    * [Trust Model](#trust-model)
* [Remote Action Requests](#remote-action-requests)
* [Administrative Requests](#administrative-requests)
* [Adding User Actions](#adding-user-actions)
    * [Implementing filter and action functions and including a custom User Action API class](#implementing-filter-and-actions-hooks-and-including-a-custom-user-action-api-class---example)
    * [Implementing a custom User Action class](#implementing-a-custom-user-action-class---example)
* [Hooks: actions and filters](#hooks-actions-and-filters)
    * [Actions](docs/hooks/actions.md)
    * [Filters](docs/hooks/filters.md)

## Overview

This plugin adds the following major features to WordPress:

* **WP Remote Users Sync admin page:** a settings page under "Settings > WP Remote Users Sync" to manage remote sites, security settings, and activity logs.
* **Remote Sites:** manage an unlimited amount of connected sites with configuration for incoming and outgoing User Actions (Login, Logout, Create, Update, Delete, Password, Role and Metadata).
* **Security:** WP Remote Users Sync is the **only** plugin available allowing users to be synchronised with true layers of security in place. All communications are OpenSSL AES-256-CBC encrypted, HMAC SHA256 signed, token-validated and IP-validated.
* **Account identity checks:** before an ordinary visitor can register, every connected site must confirm that the username and email address are unused. A matching account or an unavailable site prevents registration. After registration, the plugin checks again and does not send a Create action if a conflict or failed check appears. Users with permission to manage other users can create local accounts without these remote checks.
* **Protected incoming actions:** a connected site can change, delete, log in, or log out an existing local account only when its username and identifying email address match that account. A mismatch rejects the entire received action, including any profile, password, role, or metadata changes. Email changes are checked against the sender's previous email address.
* **Existing Users:** administrators can use a third-party user import and export tool to transfer existing users between sites before automatic synchronisation begins.
* **Activity Logs:** when enabled, all communications between connected sites is logged for admin review and troubleshooting.
* **Synchronise all user data:** compatible out of the box with WooCommerce, Ultimate Membership, Theme My Login, Gravity Forms, and all user-related plugins as long as they rely on WordPress user metadata and manipulate users with the WordPress user functions.
* **Customizable:** developers can add their own User Actions using action and filter hooks, and more.
* **Unlimited websites, unlimited features:** there are no restrictions in the number of websites to connect together, and no premium version feature restrictions shenanigans - WP Remote Users Sync is fully-featured right out of the box.

### Trust Model

WP Remote Users Sync is designed for mutually trusted WordPress installations. All connected sites share the same encryption and signature credentials, establishing trust across the group rather than identifying individual sites independently.

Per-site action settings control synchronization behavior, not access permissions. All connected sites must therefore be equally trusted.

Other registration systems can handle validation by registering a callback for `wprus_validate_user_creation`. The plugin checks connected sites and passes the result to the callback with the username, email address, and user data. The callback must stop creation through its registration system when the result reports a conflict or a failed check. Without such a callback, an ordinary user creation outside WordPress and WooCommerce registration is rejected with a standard WordPress error page.

___

## Remote Action Requests

Requests sent from one site to another use an HTTP POST with `Content-Type: application/json`. The JSON object contains the encrypted `wprusdata` string and, for an Action, its `token` string. Token requests contain `wprusdata` without a token. Form submissions to these addresses are rejected. Login and logout requests started in a browser continue to use their existing GET address and query values.

The site receiving a request returns these HTTP codes:

* **200:** The Action succeeded.
* **400:** The request data is invalid, including a token request sent without JSON data.
* **403:** Access was denied, the Action is disabled, or an Action did not include a valid JSON token.
* **404:** The target user does not exist.
* **409:** The account identity conflicts or the requested session state already exists.
* **422:** A valid Action could not be completed.
* **500:** The Action did not return a result, or the server could not create a token.

If a request asks for several metadata or role changes and any are refused, the response reports a failure even when other changes took effect. Action responses contain a JSON `success` value; callers should check both that value and the HTTP code.

___

## Administrative Requests

`/wprus/admin/` is always available when the plugin's security settings are valid. The user action settings do not turn it off. It uses the plugin's existing security token, encryption, signature, and connected-site checks.

On activation, the plugin saves its version in the `wprus_plugin_version` WordPress option. After registering its request addresses, it checks whether WordPress has saved a rule for each destination. It checks destinations because other plugins may add language prefixes to the address patterns. If a destination is missing, the version changed, or connection settings request a refresh, it saves the complete set of rules again and records the current version.

The decrypted request keeps `username`, `base_url`, and any fields added by existing filters at the top level. Two additional top-level fields belong to this address: `action` names the administrative operation, and `data` holds the fields that operation needs. When `data` repeats `base_url`, it must equal the top-level value. The settings page sends one connection test with `action` set to `test` and all incoming and outgoing Action settings in `data.actions`. The response lists every setting that differs between the two sites. The built-in `outgoing_meta` action takes the requesting site's address in `data.base_url` and returns the metadata keys that the remote site has selected to send to that site. Both sites must support this action.

The plugin loads each included `inc/api/admin/class-wprus-admin-<name>.php` file and creates the matching `Wprus_Admin_<Name>` class. Another plugin can load its files during `wprus_require_admin_handler_files`, then create its handler objects during `wprus_init_admin_handlers`.

For a saved connection, **Get remote Outgoing metadata keys** appears below the incoming metadata list. It retrieves the keys selected for that connection on the remote site, including keys that do not yet exist on this site. This site checks the keys against its existing metadata exclusions, then adds and selects those it accepts. Existing selections stay selected, and you can remove any key before saving. Matching does not save settings or change any user information. Save the settings page when you are satisfied with the selection.

Add another administrative operation by registering a function with `wprus_admin_handlers`:

```php
function my_plugin_handle_status( $data ) {
    return array( 'status' => 'ok' );
}

add_filter( 'wprus_admin_handlers', function ( $handlers ) {
    $handlers['status'] = 'my_plugin_handle_status';

    return $handlers;
} );
```

The function receives only `data`. It must return a result on success or a `WP_Error` on failure. Returning `false` produces HTTP 422, and returning `null` produces HTTP 500. The `wprus_after_handle_action_notification` hook receives the result before the plugin sends the final JSON response.

___

## Adding User Actions

Developers can extend the plugin and add their own custom User Actions by using a few filter and action hooks as well as a class inheriting `Wprus_Api_Abstract`.  
Below is a simple example of implementation of an `Example` action calling the `example` API endpoint, firing 1 synchronous request and 1 asynchronous request whenever the `wp` action hook is called by WordPress, and logs the received data (not to be used in production environment!).  

### Implementing filter and actions hooks and including a custom User Action API class - example

In this example, we are first creating a simple plugin to implement the action and filter hooks, and include the User Action API class.

```php
<?php
/*
Plugin Name: Example of User Action Extension for WP Remote Users Sync
Version: 1.0
Text Domain: my-domain
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! defined( 'WPRUS_EXTEND_PLUGIN_PATH' ) ) {
    define( 'WPRUS_EXTEND_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WPRUS_EXTEND_PLUGIN_URL' ) ) {
    define( 'WPRUS_EXTEND_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}


add_action( 'wprus_loaded', 'wprus_example', 10, 0 );
function wprus_example() {
    require WPRUS_EXTEND_PLUGIN_PATH . 'class-wprus-api-example.php';
}

add_filter( 'wprus_enabled_api_endpoints', 'wprus_enabled_api_endpoints_example', 10, 1 );
function wprus_enabled_api_endpoints_example( $endpoints ) {
    $endpoints[] = 'example';

    return $endpoints;
}

add_filter( 'wprus_api_endpoint', 'wprus_api_endpoint_example', 10, 3 );
function wprus_api_endpoint_example( $endpoint_handler, $api_endpoint, $settings ) {

    if ( ! $endpoint_handler && 'example' === $api_endpoint ) {
        return new Wprus_Api_Example( $api_endpoint, $settings, true );
    }

    return $endpoint_handler;
}

add_filter( 'wprus_actions', 'wprus_actions_example', 10, 1 );
function wprus_actions_example( $actions ) {
    $actions['example'] = __( 'Example', 'example-domain' );

    return $actions;
}
```

### Implementing a custom User Action class - example
The User Action's logic is then implemented in the file `class-wprus-api-example.php` included by the plugin.

```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Wprus_Api_Example extends Wprus_Api_Abstract {

    /*******************************************************************
     * Public methods
     *******************************************************************/

    public function init_notification_hooks() {
        add_action( 'wp', array( $this, 'notify_remote' ), 10, 0 );
    }

    public function has_async_actions() {
        return true;
    }

    public function handle_notification() {
        $result = false;
        $data   = $this->get_data();

        if ( ! $this->validate( $data ) ) {
            Wprus_Logger::log(
                __( 'Example action failed - received invalid data.', 'example-domain' ),
                'alert',
                'db_log'
            );

            return $result;
        }

        $data = $this->sanitize( $data );
        $site = $this->get_active_site_for_action( $this->endpoint, $data['base_url'] );

        if ( $site ) {
            $user = get_user_by( 'login', $data['username'] );

            if ( $user ) {
                $result = true;

                wprus_log(
                    array(
                        'message' => sprintf(
                            // translators: %1$s is the username, %2$s is the caller
                            __( 'Example action - successfully received data for user "%1$s" from %2$s.', 'example-domain' ),
                            $data['username'],
                            $site['url']
                        ),
                        'data'    => $data,
                    ),
                    'success',
                    'db_log'
                );
            } else {
                wprus_log(
                    sprintf(
                        // translators: %1$s is the username, %2$s is the caller
                        __( 'Example action aborted - user "%1$s" from %2$s does not exist locally.', 'example-domain' ),
                        $data['username'],
                        $site['url']
                    ),
                    'warning',
                    'db_log'
                );
            }
        } else {
            wprus_log(
                sprintf(
                    // translators: %s is the url of the caller
                    __( 'Example action failed - incoming example action not enabled for %s', 'example-domain' ),
                    $data['base_url']
                ),
                'alert',
                'db_log'
            );
        }

        return $result;
    }

    public function notify_remote() {

        if ( ! is_user_logged_in() ) {
            return;
        }

        $user  = get_user_by( 'ID', get_current_user_id() );
        $sites = $this->settings->get_sites( $this->endpoint, 'outgoing' );

        if ( $user && ! empty( $sites ) ) {
            $data = array( 'username' => $user->user_login );

            foreach ( $sites as $index => $site ) {
                $data['example'] = 'example data - asynchronous action';

                $this->add_async_action( $site['url'], $data );

                $data['example'] = 'example data - synchronous action';

                $this->fire_action( $site['url'], $data );
            }
        }
    }

    /*******************************************************************
     * Protected methods
     *******************************************************************/

    protected function validate( $data ) {
        return parent::validate( $data ) &&
            username_exists( $data['username'] ) &&
            is_string( $data['example'] );
    }

    protected function sanitize( $data ) {
        $data['example'] = 'sanitized ' . $data['example'];

        return $data;
    }

}
```
___

## Hooks: actions and filters

WP Remote Users Sync provides WordPress actions and filters that let other code respond to plugin events or change values used by the plugin.

- [Actions](docs/hooks/actions.md)
- [Filters](docs/hooks/filters.md)
