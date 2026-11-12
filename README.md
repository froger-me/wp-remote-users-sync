# WP Remote Users Sync - Synchronise WordPress Users across Multiple Sites

Current version: **2.4.0**.

* [General Description](#user-content-general-description)
    * [Overview](#user-content-overview)
    * [Trust Model](#user-content-trust-model)
* [Adding User Actions](#user-content-adding-user-actions)
* [Administrative Requests](#user-content-administrative-requests)
* [Hooks - actions & filters](#user-content-hooks---actions--filters)
    * [Actions](#user-content-actions)
    * [Filters](#user-content-filters)
* [Changelog](#user-content-changelog)

## General Description

If you run multiple websites and want to keep users separated, but synchronise them automatically and securely for specific user operations, then WP Remote Users Sync is the plugin to use.

### Overview

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

Install the same version on every connected site. Older versions cannot answer the account identity check or provide the identifying email address with outgoing actions. An updated site therefore rejects ordinary registration when it cannot check an older connected site, and rejects actions from an older site that target existing users.

Other registration systems can handle validation by registering a callback for `wprus_validate_user_creation`. The plugin checks connected sites and passes the result to the callback with the username, email address, and user data. The callback must stop creation through its registration system when the result reports a conflict or a failed check. Without such a callback, an ordinary user creation outside WordPress and WooCommerce registration is rejected with a standard WordPress error page.

___

## Administrative Requests

`/wprus/wp-admin/` is always available when the plugin's security settings are valid. The user action settings do not turn it off. It uses the plugin's existing security token, encryption, signature, and connected-site checks.

On activation, the plugin saves its version in the `wprus_plugin_version` WordPress option. After registering its request addresses, it checks whether WordPress has saved a rule for each destination. It checks destinations because other plugins may add language prefixes to the address patterns. If a destination is missing, the version changed, or connection settings request a refresh, it saves the complete set of rules again and records the current version. This also makes `/wprus/wp-admin/` available on sites upgrading from an older version.

The decrypted request keeps `username`, `base_url`, and any fields added by existing filters at the top level. Two additional top-level fields belong to this address: `action` names the administrative operation, and `data` holds the fields that operation needs. When `data` repeats `base_url`, it must equal the top-level value. For a connection test, `action` is `ping` and `data` contains `base_url`, `target_action` (such as `create`), and `direction` (`incoming` or `outgoing`).

Add another administrative operation by registering a function with `wprus_wp_admin_handlers`:

```php
function my_plugin_handle_status( $data ) {
    return array( 'status' => 'ok' );
}

add_filter( 'wprus_wp_admin_handlers', function ( $handlers ) {
    $handlers['status'] = 'my_plugin_handle_status';
    return $handlers;
} );
```

The function receives only `data`. It must return a result on success or a `WP_Error` on failure. Returning `null` produces an HTTP 500 error. The `wprus_after_handle_action_notification` hook receives the result; the plugin sends the final JSON response from that hook at priority 10. Code that needs to inspect the result first should use a lower priority.

The existing `wprus_ping_fired`, `wprus_ping_success`, and `wprus_ping_failure` hooks still receive the user action being tested as their first argument. The next argument contains the connection test's `data` fields. A disabled user action produces a failed connection test with HTTP 200.

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
        $valid =
            parent::validate( $data ) &&
            username_exists( $data['username'] ) &&
            is_string( $data['example'] );

        return $valid;
    }

    protected function sanitize( $data ) {
        $data['example'] = 'sanitized ' . $data['example'];

        return $data;
    }

}
```
___

## Hooks - actions & filters

WP Remote Users Sync gives developers the possibilty to customise its behavior with a series of custom actions and filters. 

### Actions

Actions index:
* [wprus_init](#user-content-wprus_init)
* [wprus_loaded](#user-content-wprus_loaded)
* [wprus_ready](#user-content-wprus_ready)
* [wprus_validate_user_creation](#user-content-wprus_validate_user_creation)
* [wprus_unauthorized_access](#user-content-wprus_unauthorized_access)
* [wprus_authorized_access](#user-content-wprus_authorized_access)
* [wprus_ping_fired](#user-content-wprus_ping_fired)
* [wprus_ping_success](#user-content-wprus_ping_success)
* [wprus_ping_failure](#user-content-wprus_ping_failure)
* [wprus_before_firing_async_actions](#user-content-wprus_before_firing_async_actions)
* [wprus_after_firing_async_actions](#user-content-wprus_after_firing_async_actions)
* [wprus_before_firing_action](#user-content-wprus_before_firing_action)
* [wprus_after_firing_action](#user-content-wprus_after_firing_action)
* [wprus_before_handle_action_notification](#user-content-wprus_before_handle_action_notification)
* [wprus_after_handle_action_notification](#user-content-wprus_after_handle_action_notification)
* [wprus_before_init_notification_hooks](#user-content-wprus_before_init_notification_hooks)
* [wprus_after_init_notification_hooks](#user-content-wprus_after_init_notification_hooks)
* [wprus_integration](#user-content-wprus_integration)
* [wprus_integration_run](#user-content-wprus_integration_run)

___

#### wprus_validate_user_creation

```php
do_action( 'wprus_validate_user_creation', $username, $email, $data, $result );
```

An integration for another registration system can register this action to handle registration errors before WordPress inserts the account. The callback receives the final username, email address, user data, and the plugin's connected-site check result. The result is `true` when the username and email address are available everywhere. Otherwise it is a `WP_Error` with code `wprus_username_exists`, `wprus_email_exists`, or `wprus_identity_unavailable`. The callback must use its registration system's error handling to stop creation for any of these errors. A registered callback replaces the plugin's default rejection for that registration system.

___

#### wprus_loaded

```php
do_action( 'wprus_init' );
```

**Description**  
Fired before initializing the plugin's settings.   

___

#### wprus_loaded

```php
do_action( 'wprus_loaded' );
```

**Description**  
Fired when all the required files have been loaded and the plugin settings are valid.   

___

#### wprus_ready

```php
do_action( 'wprus_ready', (mixed) $wprus, (mixed) $api, (mixed) $settings, (mixed) $logger );
```
**Description**  
Fired when the plugin apis have been fully instantiated, plugin settings are valid, and the plugin is ready to run.  

**Parameters**  
`$wprus`
> (mixed) An instance of the Wprus class.  

`$api`
> (mixed) Array of API handlers.  

`$settings`
> (mixed) Array of settings.  

`$wprus_logger`
> (mixed) An instance of the Wprus_Logger class.  
___

#### wprus_unauthorized_access

```php
do_action( 'wprus_unauthorized_access', (string) $endpoint, (mixed) $remote_data, (string) $token, (mixed) $wprus_api_object );
```

**Description**  
Fired when an action is received and the token could not be validated.  

**Parameters**  
`$endpoint`
> (string) The API endpoint receiving the action.  

`$remote_data`
> (mixed) Decrypted data received from the remote site. Should not be trusted.  

`$token`
> (string) The token used to attempt to authorise the request. May contain encrypted data in the case of an asynchronous request.  

`$wprus_api_object`
> (mixed) The `Wprus_Api_Abstract` object used to handle the request.  
___

#### wprus_authorized_access

```php
do_action( 'wprus_authorized_access', (string) $endpoint, (mixed) $remote_data, (string) $token, (mixed) $wprus_api_object );
```

**Description**  
Fired when an action is received and the token was successfully validated.  

**Parameters**  
`$endpoint`
> (string) The API endpoint receiving the action.  

`$remote_data`
> (mixed) Decrypted data received from the remote site.  

`$token`
> (string) The token used to authorise the request. Contains encrypted data in the case of an asynchronous request.  

`$wprus_api_object`
> (mixed) The `Wprus_Api_Abstract` object used to handle the request.  
___

#### wprus_ping_fired

```php
do_action( 'wprus_ping_fired', (string) $endpoint, (mixed) $ping_data, (mixed) $response );
```

**Description**  
Fired when a connection test was sent to a remote site.

**Parameters**  
`$endpoint`
> (string) The user action being tested.

`$ping_data`
> (mixed) The connection test's `base_url`, `target_action`, and `direction`.

`$response`
> (mixed) The response received from the remote site.  
___

#### wprus_ping_success

```php
do_action( 'wprus_ping_success', (string) $endpoint, (mixed) $ping_remote_data, (string) $remote_addr );
```

**Description**  
Fired when a test ping was received from a remote site and was successful.   

**Parameters**  
`$endpoint`
> (string) The user action being tested.

`$ping_remote_data`
> (mixed) The connection test's `base_url`, `target_action`, and `direction`.

`$remote_addr`
> (string) The IP address of the remote site.  
___

#### wprus_ping_failure

```php
do_action( 'wprus_ping_failure', (string) $endpoint, (mixed) $ping_remote_data, (string) $remote_addr );
```

**Description**  
Fired when a test ping was received from a remote site and failed.  

**Parameters**  
`$endpoint`
> (string) The user action being tested.

`$ping_remote_data`
> (mixed) The connection test's `base_url`, `target_action`, and `direction`.

`$remote_addr`
> (string) The IP address of the remote site.  

___

#### wprus_before_firing_async_actions

```php
do_action( 'wprus_before_firing_async_actions', (string) $endpoint, (mixed) $actions, (int) $user_id );
```

**Description**  
Fired before outputting the asynchronous scripts sending requests to remote sites in the front end.  

**Parameters**  
`$endpoint`
> (string) The API endpoint the requests will be sent to.  

`$actions`
> (mixed) An array of request data to send to the remote sites. Structure:  
```php
array (
    $uniq_id => array(                             // $uniq_id is the unique ID of the action
        'username' => 'username',                  // The user name of the user to act on
        'base_url' => 'https://local-website.com', // The URL of the local site sending the request
        'url'      => 'https://remote-site.com/',  // The URL of the remote site supposed to receive the request
        [...]                                      // Other data sent to perform the action
    ),
    [...]                                          // More data for other requests to other remote sites if any
);
```

`$user_id`
> (int) The ID of the user for which the actions are to be performed.  
___

#### wprus_after_firing_async_actions

```php
do_action( 'wprus_after_firing_async_actions', (string) $endpoint, (mixed) $actions, (int) $user_id );
```

**Description**  
Fired after outputting the asynchronous scripts sending requests to remote sites in the front end.  

**Parameters**  
`$endpoint`
> (string) The API endpoint the requests were sent to.  

`$actions`
> (mixed) An array of request data to send to the remote sites. Structure:  
```php
array (
    $uniq_id => array(                             // $uniq_id is the unique ID of the action
        'username' => 'username',                  // The user name of the user to act on
        'base_url' => 'https://local-website.com', // The URL of the local site sending the request
        'url'      => 'https://remote-site.com/',  // The URL of the remote site supposed to receive the request
        [...]                                      // Other data sent to perform the action
    ),
    [...]                                          // More data for other requests to other remote sites if any
);
``` 

`$user_id`
> (int) The ID of the user for which the actions were performed.  
___

#### wprus_before_firing_action

```php
do_action( 'wprus_before_firing_action', (string) $endpoint, (string) $url, (mixed) $data );
```

**Description**  
Fired before sending a synchronous request to a remote site.  

**Parameters**  
`$endpoint`
> (string) The API endpoint the request will be sent to.  

`$url`
> (string) The URL to send the request to.  

`$data`
> (mixed) The data sent to the remote site. Structure:
```php
array(
    'username' => 'username',                  // The user name of the user to act on
    'base_url' => 'https://local-website.com', // The URL of the local site sending the request
    [...]                                      // Other data sent to perform the action
);
```
___

#### wprus_after_firing_action

```php
do_action( 'wprus_after_firing_action', (string) $endpoint, (string) $url, (mixed) $data, (mixed) $response );
```

**Description**  
Fired after sending a synchronous request to a remote site.  

`$endpoint`
> (string) The API endpoint the request will be sent to.  

`$url`
> (string) The URL to send the request to.  

`$data`
> (mixed) The data sent to the remote site. Structure:
```php
array(
    'username' => 'username',                  // The user name of the user to act on
    'base_url' => 'https://local-website.com', // The URL of the local site sending the request
    [...]                                      // Other data sent to perform the action
);
```

`$response`
> (mixed) Array containing `headers`, `body`, `response`, `cookies`, `filename`. A `WP_Error` instance upon error.  
___

#### wprus_before_handle_action_notification

```php
do_action( 'wprus_before_handle_action_notification', (string) $endpoint, (mixed) $data );
```

**Description**  
Fired before handling a notification received from a remote site.   

**Parameters**  
`$endpoint`
> (string) The API endpoint receiving the notification request.  

`$data`
> (mixed) The data received from the remote site. Structure:
```php
array(
    'username' => 'username',                   // The user name of the user to act on
    'base_url' => 'https://remote-website.com', // The URL of the remote site sending the request
    [...]                                       // Other data sent to perform the action
);
```
___

#### wprus_after_handle_action_notification

```php
do_action( 'wprus_after_handle_action_notification', (string) $endpoint, (mixed) $data, (mixed) $result );
```

**Description**  
Fired after handling a request received from a connected site. For `wp-admin`, this hook receives the returned result before the plugin sends its JSON response at priority 10.

**Parameters**  
`$endpoint`
> (string) The API endpoint receiving the notification request.  

`$data`
> (mixed) The data received from the remote site. Structure:
```php
array(
    'username' => 'username',                   // The user name of the user to act on
    'base_url' => 'https://remote-website.com', // The URL of the remote site sending the request
    [...]                                       // Other data sent to perform the action
);
``` 

`$result`
> (mixed) The result returned by the request handler. Administrative handlers return a value on success or a `WP_Error` on failure.
___


#### wprus_before_init_notification_hooks

```php
do_action( 'wprus_before_init_notification_hooks', (string) $endpoint, (mixed) $wprus_api_object );
```

**Description**  
Fired before adding hooks used to notify remote sites.  

**Parameters**  
`$endpoint`
> (string) The API endpoint name of the object adding the notification hooks.  

`$wprus_api_object`
> (mixed) The `Wprus_Api_Abstract` object adding the notification hooks.  
___

#### wprus_after_init_notification_hooks

```php
do_action( 'wprus_after_init_notification_hooks', (string) $endpoint, (mixed) $wprus_api_object );
```

**Description**  
Fired after adding hooks used to notify remote sites.  

**Parameters**  
`$endpoint`
> (string) The API endpoint name of the object adding the notification hooks.  

`$wprus_api_object`
> (mixed) The `Wprus_Api_Abstract` object adding the notification hooks.  
___

#### wprus_require_integration_file

```php
do_action( 'wprus_require_integration_file', (string) $plugin_slug, (string) $class_name );
```
**Description**  
Fired when an integration with a third-party plugin needs to load a class file inheriting `Wprus_Integration`.  

**Parameters**  
`$plugin_slug`
> (string) The slug of the plugin integrated.  

`$class_name`
> (string) The name of the integration class - must inherit `Wprus_Integration`.

___

#### wprus_integration

```php
do_action( 'wprus_integration', (mixed) $wprus_integration_obj, (string) $plugin_slug );
```
**Description**  
Fired when an integration with a third-party plugin is active and loaded.  

**Parameters**  
`$wprus_integration_obj`
> (mixed) The `Wprus_Integration` object used to provide features integration.  

`$plugin_slug`
> (string) The slug of the plugin integrated.

___

#### wprus_integration_run

```php
do_action( 'wprus_integration_run', (mixed) $wprus_integration_obj );
```
**Description**  
Fired when an integration with a third-party plugin hooks is fully initialized.  

**Parameters**  
`$wprus_integration_obj`
> (mixed) The `Wprus_Integration` object used to provide features integration.  

___

### Filters

Filters index:

* [WP Remote Users Sync - Synchronise WordPress Users across Multiple Sites](#wp-remote-users-sync---synchronise-wordpress-users-across-multiple-sites)
    * [General Description](#general-description)
        * [Overview](#overview)
        * [Trust Model](#trust-model)
    * [Administrative Requests](#administrative-requests)
    * [Adding User Actions](#adding-user-actions)
        * [Implementing filter and actions hooks and including a custom User Action API class - example](#implementing-filter-and-actions-hooks-and-including-a-custom-user-action-api-class---example)
        * [Implementing a custom User Action class - example](#implementing-a-custom-user-action-class---example)
    * [Hooks - actions \& filters](#hooks---actions--filters)
        * [Actions](#actions)
            * [wprus\_validate\_user\_creation](#wprus_validate_user_creation)
            * [wprus\_loaded](#wprus_loaded)
            * [wprus\_loaded](#wprus_loaded-1)
            * [wprus\_ready](#wprus_ready)
            * [wprus\_unauthorized\_access](#wprus_unauthorized_access)
            * [wprus\_authorized\_access](#wprus_authorized_access)
            * [wprus\_ping\_fired](#wprus_ping_fired)
            * [wprus\_ping\_success](#wprus_ping_success)
            * [wprus\_ping\_failure](#wprus_ping_failure)
            * [wprus\_before\_firing\_async\_actions](#wprus_before_firing_async_actions)
            * [wprus\_after\_firing\_async\_actions](#wprus_after_firing_async_actions)
            * [wprus\_before\_firing\_action](#wprus_before_firing_action)
            * [wprus\_after\_firing\_action](#wprus_after_firing_action)
            * [wprus\_before\_handle\_action\_notification](#wprus_before_handle_action_notification)
            * [wprus\_after\_handle\_action\_notification](#wprus_after_handle_action_notification)
            * [wprus\_before\_init\_notification\_hooks](#wprus_before_init_notification_hooks)
            * [wprus\_after\_init\_notification\_hooks](#wprus_after_init_notification_hooks)
            * [wprus\_require\_integration\_file](#wprus_require_integration_file)
            * [wprus\_integration](#wprus_integration)
            * [wprus\_integration\_run](#wprus_integration_run)
        * [Filters](#filters)
            * [wprus\_enabled\_api\_endpoints](#wprus_enabled_api_endpoints)
            * [wprus\_api\_endpoint](#wprus_api_endpoint)
            * [wprus\_api](#wprus_api)
            * [wprus\_wp\_endpoints](#wprus_wp_endpoints)
            * [wprus\_actions](#wprus_actions)
            * [wprus\_settings](#wprus_settings)
            * [wprus\_option](#wprus_option)
            * [wprus\_settings\_valid](#wprus_settings_valid)
            * [wprus\_settings\_metaboxes](#wprus_settings_metaboxes)
            * [wprus\_sanitize\_settings](#wprus_sanitize_settings)
            * [wprus\_excluded\_meta\_keys](#wprus_excluded_meta_keys)
            * [wprus\_excluded\_meta\_keys\_like](#wprus_excluded_meta_keys_like)
            * [wprus\_protected\_meta\_keys](#wprus_protected_meta_keys)
            * [wprus\_incoming\_meta\_exclusions](#wprus_incoming_meta_exclusions)
            * [wprus\_init\_notification\_hooks](#wprus_init_notification_hooks)
            * [wprus\_fire\_action\_timeout](#wprus_fire_action_timeout)
            * [wprus\_request\_token\_timeout](#wprus_request_token_timeout)
            * [wprus\_request\_token\_retry\_timeout](#wprus_request_token_retry_timeout)
            * [wprus\_is\_authorized\_remote](#wprus_is_authorized_remote)
            * [wprus\_debug](#wprus_debug)
            * [wprus\_action\_data](#wprus_action_data)
            * [wprus\_async\_url](#wprus_async_url)
            * [wprus\_registered\_integration](#wprus_registered_integration)
            * [wprus\_get\_admin\_template\_name](#wprus_get_admin_template_name)
            * [wprus\_get\_admin\_template\_args](#wprus_get_admin_template_args)
            * [wprus\_get\_template\_name](#wprus_get_template_name)
            * [wprus\_get\_template\_args](#wprus_get_template_args)
            * [wprus\_locate\_template](#wprus_locate_template)
            * [wprus\_locate\_admin\_template](#wprus_locate_admin_template)
            * [wprus\_locate\_template\_paths](#wprus_locate_template_paths)
    * [Changelog](#changelog)
        * [2.4.0](#240)

___

#### wprus_enabled_api_endpoints

```php
apply_filters( 'wprus_enabled_api_endpoints', (mixed) $endpoints );
```
**Description**  
Filter the enabled endpoints.  

**Parameters**  
`$endpoints`
> (array) Array of enabled API endpoints. Default: 
```php
array(
    'login',
    'logout',
    'create',
    'update',
    'delete',
    'password',
    'role',
    'meta',
);
```
___

#### wprus_api_endpoint

```php
apply_filters( 'wprus_api_endpoint', (mixed) $endpoint_handler, (string) $api_endpoint, (mixed) $settings );
```
**Description**  
Filter the handler object for a custom endpoint.  

**Parameters**  
`$endpoint_handler`
> (mixed) The object instance of a class inherhiting the `Wprus_Api_Abstract` API class. Default `false`.    

`$api_endpoint`
> (string) The custom API endpoint.

`$settings`
> (mixed) Array of all the settings.  
___

#### wprus_api

```php
apply_filters( 'wprus_api', (mixed) $api );
```
**Description**  
Filter the collection of API handlers.  

**Parameters**  
`$api`
> (mixed) Array of API handlers. Default:
```php
array(
    'login'    => $wprus_api_login,    // An instance of Wprus_Api_Login inheriting Wprus_Api_Abstract
    'logout'   => $wprus_api_logout,   // An instance of Wprus_Api_Logout inheriting Wprus_Api_Abstract
    'create'   => $wprus_api_create,   // An instance of Wprus_Api_Create inheriting Wprus_Api_Abstract
    'update'   => $wprus_api_update,   // An instance of Wprus_Api_Update inheriting Wprus_Api_Abstract
    'delete'   => $wprus_api_delete,   // An instance of Wprus_Api_Delete inheriting Wprus_Api_Abstract
    'password' => $wprus_api_password, // An instance of Wprus_Api_Password inheriting Wprus_Api_Abstract
    'role'     => $wprus_api_role,     // An instance of Wprus_Api_Role inheriting Wprus_Api_Abstract
    'meta'     => $wprus_api_meta,     // An instance of Wprus_Api_Meta inheriting Wprus_Api_Abstract
);
```
___

#### wprus_wp_endpoints

```php
apply_filters( 'wprus_wp_endpoints', (mixed) $wprus_endpoints );
```
**Description**  
Filter the endpoints to add to WordPress.  
Documented here for the sake of completeness and in case some developers find it useful in very specific cases - adding endpoints to the list should be handled automatically by a class inheriting the `Wprus_Api_Abstract` class instead of using this filter.

**Parameters**  
`$wprus_endpoints`
> (mixed) Array of endpoints to add. Default:  
```php
array(
    'token'    => 'token/?',
    'login'    => 'login/?',
    'logout'   => 'logout/?',
    'create'   => 'create/?',
    'update'   => 'update/?',
    'delete'   => 'delete/?',
    'password' => 'password/?',
    'role'     => 'role/?',
    'meta'     => 'meta/?',
);
```
___

#### wprus_actions

```php
apply_filters( 'wprus_actions', (mixed) $actions );
```
**Description**  
Filter the supported actions.  

**Parameters**  
`$actions`
> (mixed) Array of actions - key is the action key, value is the action display value. Default:
```php
array(
    'login'    => __( 'Login', 'wprus' ),
    'logout'   => __( 'Logout', 'wprus' ),
    'create'   => __( 'Create', 'wprus' ),
    'update'   => __( 'Update', 'wprus' ),
    'delete'   => __( 'Delete', 'wprus' ),
    'password' => __( 'Password', 'wprus' ),
    'role'     => __( 'Roles', 'wprus' ),
    'meta'     => __( 'Metadata', 'wprus' ),
);
```
___

#### wprus_settings

```php
apply_filters( 'wprus_settings', (mixed) $settings );
```
**Description**  
Filter the settings' values.  

**Parameters**  
`$settings`
> (mixed) Array of all the settings.  
___

#### wprus_option

```php
apply_filters( 'wprus_option', (mixed) $value, (string) $key );
```
**Description**  
Filter a single setting's option value.  

**Parameters**  
`$value`
> (mixed) the value of the option  
`$key`
> (string) the key used to retrieve the option value
___

#### wprus_settings_valid

```php
apply_filters( 'wprus_settings_valid', (bool) $valid, (mixed) $settings );
```
**Description**  
Filter wether the settings are valid ; called before running any other part of the plugin.  

**Parameters**  
`$valid`
> (bool) Wether the settings are valid.  

`$settings`
> (mixed ) Array of all the settings.  
___

#### wprus_settings_metaboxes

```php
apply_filters( 'wprus_settings_metaboxes', (mixed) $metaboxes );
```
**Description**  
Filter the setting's metaboxes of the plugin's screen.  

**Parameters**  
`$metaboxes`
> (mixed) Array representing the metaboxes. Structure:
```php
array(
    'id'     => array(                                 // Each item is index by its metabox ID
        'title'    => __( 'Metabox title', 'domain' ), // The title to display
        'callback' => 'metabox_callback_function',     // The callback to output the metabox
        'position' => 'nomal',                         // The position on the screen - one of 'normal', 'side' or 'advanced'
        'priority' => 'default',                       // The display priority - one of 'default', 'high' or 'low'
        'data'     => $data,                           // The data to be passed to the callback
    ),
    [...]                                              // Other metabox items
);
```
___

#### wprus_sanitize_settings

```php
apply_filters( 'wprus_sanitize_settings', (mixed) $sanitized_settings );
```
**Description**  
Filter the settings after sanitatization.  

**Parameters**  
`$sanitized_settings`
> (mixed) Array of all the settings after sanitatization.  
___

#### wprus_excluded_meta_keys

```php
apply_filters( 'wprus_excluded_meta_keys', (mixed) $excluded_meta_keys );
```
**Description**  
Filter the meta keys excluded from selection for synchronization (by default, keys referring to redundant or site-specific data). These keys are also ignored in incoming Metadata actions.

**Parameters**  
`$excluded_meta_keys`
> (mixed) Array of exluded meta keys. Default:
```php
array(
    'user_url',
    'user_email',
    'display_name',
    'nickname',
    'first_name',
    'last_name',
    'description',
    'primary_blog',
    'use_ssl',
    'comment_shortcuts',
    'admin_color',
    'rich_editing',
    'syntax_highlighting',
    'show_admin_bar_front',
    'locale',
    'community-events-location',
    'show_try_gutenberg_panel',
    'closedpostboxes_post',
    'metaboxhidden_post',
    'closedpostboxes_dashboard',
    'metaboxhidden_dashboard',
    'dismissed_wp_pointers',
    'session_tokens',
    '_application_passwords',
    'source_domain',
);
```  
___

#### wprus_excluded_meta_keys_like

```php
apply_filters( 'wprus_excluded_meta_keys_like', (mixed) $excluded_meta_keys_like_expressions );
```
**Description**  
Filter the meta keys `LIKE` clauses used to exclude groups of meta keys from selection for synchronization (by default, keys referring to redundant or site-specific data). Incoming Metadata actions use regular expressions made from these patterns to ignore the same keys.

**Parameters**  
`$excluded_meta_keys_like_expressions`
> (mixed) Array of meta keys `LIKE` expressions. Default:
```php
array(
    '%capabilities',
    '%user_level',
    '%user-settings',
    '%user-settings-time',
    '%dashboard_quick_press_last_post_id',
    'wprus%',
    '%wprus',
);
```  
___

#### wprus_protected_meta_keys

```php
apply_filters( 'wprus_protected_meta_keys', (array) $keys );
```

Add metadata keys that incoming Metadata actions must never change. The filter receives an empty array; the plugin adds its protected WordPress keys afterward, so they cannot be removed through this filter.

```php
add_filter( 'wprus_protected_meta_keys', function ( $keys ) {
    $keys[] = 'private_key';
    return $keys;
} );
```

#### wprus_incoming_meta_exclusions

```php
apply_filters( 'wprus_incoming_meta_exclusions', (array) $excluded );
```

**Description**
Change which metadata keys an incoming Metadata action ignores. The settings page's exact exclusions and wildcard exclusions, including changes made with `wprus_excluded_meta_keys` and `wprus_excluded_meta_keys_like`, supply the defaults. The wildcard exclusions are converted to regular expressions before checking a request.

Incoming Metadata actions accept only keys explicitly selected for the connected site. An empty selection accepts no metadata. Keys must remain unchanged by WordPress's `sanitize_key()` function: by default, this means lowercase letters, numbers, underscores, and hyphens only. Before updating to version 2.3.0, select the keys needed on each receiving site.

All settings page exclusions apply to incoming requests, including keys ending in `capabilities` or `user_level`. WordPress role and permission keys are also checked against the exact prefix of each site, and `_application_passwords` is always excluded. These keys cannot be allowed through this filter. Exact key checks and the default regular expressions ignore letter case.

**Parameters**
`$excluded` is an array with two lists:

* `keys`: complete metadata key names to ignore.
* `regex`: complete PHP regular expressions to match keys that should be ignored.

Return the modified array from the filter. For example:

```php
add_filter( 'wprus_incoming_meta_exclusions', function ( $excluded ) {
    $excluded['keys'][] = 'private_note';
    $excluded['regex'][] = '/\Acustom_/i';

    return $excluded;
} );
```

___

#### wprus_init_notification_hooks

```php
apply_filters( 'wprus_init_notification_hooks', (bool) $init_notification_hooks );
```
**Description**  
Filter wether to initialise the notification hooks for the current request.  
**Warning:** Must be added **before** WordPress runs the `init` action.  

**Parameters**  
`$init_notification_hooks`
> (bool) If truthy, hooks will be initialised and notifications will be sent to remote sites upon user changes. Set to a falsy value to prevent notifications from being sent. Default `true` except in the case of user import.  
___

#### wprus_fire_action_timeout

```php
apply_filters( 'wprus_fire_action_timeout', (int) $timeout, (string) $endpoint, (string) $url, (bool) $blocking );
```
**Description**  
Filter the timeout for a synchronous action request.  

**Parameters**  
`$timeout`
> (int) The timeout for a synchronous action request expressed in seconds. Default `1`.  

`$endpoint`
> (string) The destination endpoint.  

`$url`
> (string) The destination website's URL.  

`$blocking`
> (bool) Whether the request needs to wait for a response.  
___

#### wprus_request_token_timeout

```php
apply_filters( 'wprus_request_token_timeout', (int) $token_timeout );
```
**Description**  
Filter the timeout for an authentication token request.  

**Parameters**  
`$token_timeout`
> (int) The timeout for a token request expressed in seconds. Default `1`.  
___

#### wprus_request_token_retry_timeout

```php
apply_filters( 'wprus_request_token_retry_timeout', (int) $token_retry_timeout );
```
**Description**  
Filter the retry timeout for an authentication token request, in case the first request failed.  

**Parameters**  
`$token_retry_timeout`
> (int) The retry timeout for token request expressed in seconds. Default `5`.  

___

#### wprus_is_authorized_remote

```php
apply_filters( 'wprus_is_authorized_remote', (bool) $is_authorized_remote, (string) $method, (string) $remote_addr, (mixed) $ip_whitelist );
```
**Description**  
Filter wether the received request should be authorised.  

**Parameters**  
`$is_authorized_remote`
> (bool) Whether the request is authorised.  

$method
> (string) The request's method - `post` or `get`.

`$remote_addr`
> (string) The IP address received in the `REMOTE_ADDR` header.  

`$ip_whitelist`
> (mixed) An array of strings as defined in the "IP Whitelist" settings.

___

#### wprus_debug

```php
apply_filters( 'wprus_debug', bool $debug );
```

**Description**  
Filter wether to activate debug mode (PHP error logs, JavaScript console messages, JavaScript alerts if any).  

**Parameters**  
`$debug`
> (bool) Wether debug mode is activated - default `WP_DEBUG` constant value.  
___

#### wprus_action_data

```php
apply_filters( 'wprus_action_data', array $data, string $endpoint, string $url );
```
**Description**  
Filter the data sent to a remote site.  

**Parameters**  
`$data`
> (array) The data sent to the remote site.  

`$endpoint`
> (string) The API endpoint receiving the action.  

`$url`
> (string) The URL of the remote site.  

___

#### wprus_async_url

```php
apply_filters( 'wprus_async_url', (string) $async_url, (string) $endpoint );
```
**Description**
Filter the full URL used to send an asynchronous action to a remote site.

**Parameters**
`$async_url`
> (string) The full URL used to send the action, including the encrypted action data and authentication token.

`$endpoint`
> (string) The API endpoint receiving the action.

___

#### wprus_registered_integration

```php
apply_filters( 'wprus_registered_integration', array $integrations );
```
**Description**  
Filter integrations. See `inc/integration/class-wprus-integration.php`.  

**Parameters**  
`$integrations`
> (array) The integrations info. Structure:  
```php
array (
    $slug => array(                          // $slug is the unique integration slug
        'plugin'     => 'plugin_name',       // name of the plugin being integrated ; passed to is_plugin_active() to determine if the integration needs to be loaded
        'class_name' => 'Integration_Class', // Class name of the integration class ; the class must be included using the action wprus_require_integration_file, and must inherit the Wprus_Integration class
    ),
);
```

___

#### wprus_get_admin_template_name

```php
apply_filters( 'wprus_get_admin_template_name', string $template_name, array $args );
```
**Description**  
Filter the file name of the template to include.  

**Parameters**  
`$template_name`
> (string) The file name of the template to include.  

`$args`
> (array) The variables passed to the template.  

___

#### wprus_get_admin_template_args

```php
apply_filters( 'wprus_get_admin_template_args', array $args, string $template_name );
```
**Description**  
Filter the variables passed to the template.  

**Parameters**  
`$args`
> (array) The variables passed to the template.  

`$template_name`
> (string) The file name of the template.   

___

#### wprus_get_template_name

```php
apply_filters( 'wprus_get_template_name', string $template_name, array $args );
```
**Description**  
Filter the file name of the template to include.  

**Parameters**  
`$template_name`
> (string) The file name of the template to include.  

`$args`
> (array) The variables passed to the template.  

___

#### wprus_get_template_args

```php
apply_filters( 'wprus_get_template_args', array $args, string $template_name );
```
**Description**  
Filter the variables passed to the template.  

**Parameters**  
`$args`
> (array) The variables passed to the template.  

`$template_name`
> (string) The file name of the template.  

___

#### wprus_locate_template

```php
apply_filters( 'wprus_locate_template', string $template_file, string $template_name, string $template_path );
```
**Description**  
Filter the fully qualified path of the template file.  

**Parameters**  
`$template_file`
> (string) The fully qualified path of the template file.  

`$template_name`
> (string) The file name.  

`$template_path`
> (string) The file path.  

___

#### wprus_locate_admin_template

```php
apply_filters( 'wprus_locate_admin_template', string $template_file, string $template_name, string $template_path );
```
**Description**  
Filter the fully qualified path of the admin template file.  

**Parameters**  
`$template_file`
> (string) The fully qualified path of the admin template file.  

`$template_name`
> (string) The file name.  

`$template_path`
> (string) The file path.  

___

#### wprus_locate_template_paths

```php
apply_filters( 'wprus_locate_template_paths', array $paths );
```
**Description**  
Filter the list of possible theme paths for the template file.  

**Parameters**  
`$paths`
> (array) The list of possible theme paths for the template file. Structure:  
```php
// Paths relative to WordPress theme's folder ; $name is the template's file name
array(
    'plugins/wprus/templates/' . $name,
    'plugins/wprus/' . $name,
    'wprus/templates/' . $name,
    'wprus/' . $name,
);
```

___

## Changelog

### 2.4.0

* Reject ordinary user creation when a connected site already uses the username or email address, or when a connected site cannot be checked. Check again after creation and skip the outgoing Create action if that check fails.
* Reject the entire incoming synchronization action for an existing user when the sender's username or identifying email address does not match. This protects profile details, passwords, roles, metadata, sessions, and deletion.
* Check email changes against the sender's previous email address so valid changes can still synchronize.
* Show clear registration errors in WordPress and WooCommerce, and a standard WordPress error page for unsupported registration methods.
* Pass the connected-site check result to other registration systems through `wprus_validate_user_creation` so they can show errors in their own forms.
