# WP Remote Users Sync - Synchronise WordPress Users across Multiple Sites

* [General Description](#user-content-general-description)
    * [Overview](#user-content-overview)
* [Adding User Actions](#user-content-adding-user-actions)
* [Hooks - actions & filters](#user-content-hooks---actions--filters)
    * [Actions](#user-content-actions)
    * [Filters](#user-content-filters)

## General Description

If you run multiple websites and want to keep users separated, but synchronise them automatically and securely for specific user operations, then WP Remote Users Sync is the plugin to use.

### Overview

This plugin adds the following major features to WordPress:

* **WP Remote Users Sync admin page:** a settings page under "Settings > WP Remote Users Sync" to manage remote sites, security settings, import/export users, and view activity logs.
* **Remote Sites:** manage an unlimited amount of connected sites with configuration for incoming and outgoing user actions (Login, Logout, Create, Update, Delete, Password, Role and Metadata).
* **Security:** WP Remote Users Sync is the **only** plugin available allowing users to be synchronised with true layers of security in place. All communications are OpenSSL AES-256-CBC encrypted, HMAC SHA256 signed, token-validated and IP-validated.
* **Import and Export Users:** connected websites' existing user base can be synchronised manually first thanks to the provided import/export tool.
* **Activity Logs:** when enabled, all communications between connected sites is logged for admin review and troubleshooting.
* **Synchronise all user data:** compatible out of the box with WooCommerce, Ultimate Membership, Theme My Login, Gravity Forms, and all user-related plugins as long as they rely on WordPress user metadata and manipulate users with the WordPress user functions.
* **Customizable:** developers can add their own user actions using action and filter hooks, and more.
* **Unlimited websites, unlimited features:** there are no restrictions in the number of websites to connect together, and no premium version feature restrictions shenanigans - WP Remote Users Sync is fully-featured right out of the box.

___

## Adding User Actions

Developers can extend the plugin and add their own custom user actions by using a few filter and action hooks as well as a class inheriting `Wprus_Api_Abstract`.  
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

    public function has_remote_async_actions() {

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

                $this->add_remote_async_action( $site['url'], $data );

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
$wprus
> (mixed) An instance of the Wprus class.  

$api
> (mixed) Array of API handlers.  

$settings
> (mixed) Array of settings.  

$wprus_logger
> (mixed) An instance of the Wprus_Logger class.  
___

#### wprus_unauthorized_access

```php
do_action( 'wprus_unauthorized_access', (string) $endpoint, (mixed) $remote_data, (string) $token, (mixed) $wprus_api_object );
```

**Description**  
Fired when an action is received and the token could not be validated.  

**Parameters**  
$endpoint
> (string) The API endpoint receiving the action.  

$remote_data
> (mixed) Decrypted data received from the remote site. Should not be trusted.  

$token
> (string) The token used to attempt to authorise the request. May contain encrypted data in the case of an asynchronous request.  

$wprus_api_object
> (mixed) The `Wprus_Api_Abstract` object used to handle the request.  
___

#### wprus_authorized_access

```php
do_action( 'wprus_authorized_access', (string) $endpoint, (mixed) $remote_data, (string) $token, (mixed) $wprus_api_object );
```

**Description**  
Fired when an action is received and the token was successfully validated.  

**Parameters**  
$endpoint
> (string) The API endpoint receiving the action.  

$remote_data
> (mixed) Decrypted data received from the remote site.  

$token
> (string) The token used to authorise the request. Contains encrypted data in the case of an asynchronous request.  

$wprus_api_object
> (mixed) The `Wprus_Api_Abstract` object used to handle the request.  
___

#### wprus_ping_fired

```php
do_action( 'wprus_ping_fired', (string) $endpoint, (mixed) $ping_data, (mixed) $response );
```

**Description**  
Fired when a test ping was sent to a remote site.  

**Parameters**  
$endpoint
> (string) The API endpoint the test ping was sent to.  

$ping_data
> (mixed) The data sent to perform the ping.  

$response
> (mixed) The response received from the remote site.  
___

#### wprus_ping_success

```php
do_action( 'wprus_ping_success', (string) $endpoint, (mixed) $ping_remote_data, (string) $remote_addr );
```

**Description**  
Fired when a test ping was received from a remote site and was successful.   

**Parameters**  
$endpoint
> (string) The API endpoint receiving the test ping.  

$ping_remote_data
> (mixed) The data received from the remote site to perform the ping.  

$remote_addr
> (string) The IP address of the remote site.  
___

#### wprus_ping_failure

```php
do_action( 'wprus_ping_failure', (string) $endpoint, (mixed) $ping_remote_data, (string) $remote_addr );
```

**Description**  
Fired when a test ping was received from a remote site and failed.  

**Parameters**  
$endpoint
> (string) The API endpoint receiving the test ping.  

$ping_remote_data
> (mixed) The data received from the remote site to perform the ping.  

$remote_addr
> (string) The IP address of the remote site.  

___

#### wprus_before_firing_async_actions

```php
do_action( 'wprus_before_firing_async_actions', (string) $endpoint, (mixed) $actions );
```

**Description**  
Fired before outputting the asynchronous scripts sending requests to remote sites in the front end.  

**Parameters**  
$endpoint
> (string) The API endpoint the requests will be sent to.  

$actions
> (mixed) An array of request data to send to the remote sites. Structure:  
```php
array (
    0 => array(
        'username' => 'username',                  // The user name of the user to act on
        'base_url' => 'https://local-website.com', // The URL of the local site sending the request
        'url'      => 'https://remote-site.com/',  // The URL of the remote site supposed to receive the request
        [...]                                      // Other data sent to perform the action
    ),
    [...]                                          // More data for other requests to other remote sites if any
);
```
___

#### wprus_after_firing_async_actions

```php
do_action( 'wprus_after_firing_async_actions', (string) $endpoint, (mixed) $actions );
```

**Description**  
Fired after outputting the asynchronous scripts sending requests to remote sites in the front end.  

**Parameters**  
$endpoint
> (string) The API endpoint the requests were sent to.  

$actions
> (mixed) An array of request data to send to the remote sites. Structure:  
```php
array (
    0 => array(
        'username' => 'username',                  // The user name of the user to act on
        'base_url' => 'https://local-website.com', // The URL of the local site sending the request
        'url'      => 'https://remote-site.com/',  // The URL of the remote site supposed to receive the request
        [...]                                      // Other data sent to perform the action
    ),
    [...]                                          // More data for other requests to other remote sites if any
);
``` 
___

#### wprus_before_firing_action

```php
do_action( 'wprus_before_firing_action', (string) $endpoint, (string) $url, (mixed) $data );
```

**Description**  
Fired before sending a synchronous request to a remote site.  

**Parameters**  
$endpoint
> (string) The API endpoint the request will be sent to.  

$url
> (string) The URL to send the request to.  

$data
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

$endpoint
> (string) The API endpoint the request will be sent to.  

$url
> (string) The URL to send the request to.  

$data
> (mixed) The data sent to the remote site. Structure:
```php
array(
    'username' => 'username',                  // The user name of the user to act on
    'base_url' => 'https://local-website.com', // The URL of the local site sending the request
    [...]                                      // Other data sent to perform the action
);
```

$response
> (mixed) Array containing `headers`, `body`, `response`, `cookies`, `filename`. A `WP_Error` instance upon error.  
___

#### wprus_before_handle_action_notification

```php
do_action( 'wprus_before_handle_action_notification', (string) $endpoint, (mixed) $data );
```

**Description**  
Fired before handling a notification received from a remote site.   

**Parameters**  
$endpoint
> (string) The API endpoint receiving the notification request.  

$data
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
do_action( 'wprus_after_handle_action_notification', (string) $endpoint, (mixed) $data, (bool) $result );
```

**Description**  
Fired after handling a notification received from a remote site.   

**Parameters**  
$endpoint
> (string) The API endpoint receiving the notification request.  

$data
> (mixed) The data received from the remote site. Structure:
```php
array(
    'username' => 'username',                   // The user name of the user to act on
    'base_url' => 'https://remote-website.com', // The URL of the remote site sending the request
    [...]                                       // Other data sent to perform the action
);
``` 

$result
> (bool) Wether handling the notification was successful or failed ; any change to user data is considered successful even if warnings were raised (for example, if the Update action was received and a user was created instead of updated).  
___


#### wprus_before_init_notification_hooks

```php
do_action( 'wprus_before_init_notification_hooks', (string) $endpoint, (mixed) $wprus_api_object );
```

**Description**  
Fired before adding hooks used to notify remote sites.  

**Parameters**  
$endpoint
> (string) The API endpoint name of the object adding the notification hooks.  

$wprus_api_object
> (mixed) The `Wprus_Api_Abstract` object adding the notification hooks.  
___

#### wprus_after_init_notification_hooks

```php
do_action( 'wprus_after_init_notification_hooks', (string) $endpoint, (mixed) $wprus_api_object );
```

**Description**  
Fired after adding hooks used to notify remote sites.  

**Parameters**  
$endpoint
> (string) The API endpoint name of the object adding the notification hooks.  

$wprus_api_object
> (mixed) The `Wprus_Api_Abstract` object adding the notification hooks.  
___

#### wprus_integration

```php
do_action( 'wprus_integration', (mixed) $wprus_integration_obj, (string) $plugin_slug );
```
**Description**  
Fired when an integration with a third-party plugin is active and loaded.  

**Parameters**  
$wprus_integration_obj
> (mixed) The `Wprus_Integration` object used to provide features integration.  

$plugin_slug
> (string) The slug of the plugin integrated.

___

#### wprus_integration

```php
do_action( 'wprus_integration_run', (mixed) $wprus_integration_obj );
```
**Description**  
Fired when an integration with a third-party plugin' hooks are fully initialized.  

**Parameters**  
$wprus_integration_obj
> (mixed) The `Wprus_Integration` object used to provide features integration.  

___

### Filters

Filters index:

* [wprus_enabled_api_endpoints](#user-content-wprus_enabled_api_endpoints)
* [wprus_api_endpoint](#user-content-wprus_api_endpoint)
* [wprus_api](#user-content-wprus_api)
* [wprus_wp_endpoints](#user-content-wprus_wp_endpoints)
* [wprus_actions](#user-content-wprus_actions)
* [wprus_settings](#user-content-wprus_settings)
* [wprus_option](#user-content-wprus_option)
* [wprus_settings_valid](#user-content-wprus_settings_valid)
* [wprus_settings_metaboxes](#user-content-wprus_settings_metaboxes)
* [wprus_template](#user-content-wprus_template)
* [wprus_sanitize_settings](#user-content-wprus_sanitize_settings)
* [wprus_excluded_meta_keys](#user-content-wprus_excluded_meta_keys)
* [wprus_excluded_meta_keys_like](#user-content-wprus_excluded_meta_keys_like)
* [wprus_init_notification_hooks](#user-content-wprus_init_notification_hooks)
* [wprus_request_token_timeout](#user-content-wprus_request_token_timeout)
* [wprus_request_token_retry_timeout](#user-content-wprus_request_token_retry_timeout)
* [wprus_is_authorized_remote](#user-content-wprus_is_authorized_remote)

___

#### wprus_enabled_api_endpoints

```php
apply_filters( 'wprus_enabled_api_endpoints', (mixed) $endpoints );
```
**Description**  
Filter the enabled endpoints.  

**Parameters**  
$endpoints
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
$endpoint_handler
> (mixed) The object instance of a class inherhiting the `Wprus_Api_Abstract` API class. Default `false`.    

$api_endpoint
> (string) The custom API endpoint.

$settings
> (mixed) Array of all the settings.  
___

#### wprus_api

```php
apply_filters( 'wprus_api', (mixed) $api );
```
**Description**  
Filter the collection of API handlers.  

**Parameters**  
$api
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
$wprus_endpoints
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
$actions
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
$settings
> (mixed) Array of all the settings.  
___

#### wprus_option

```php
apply_filters( 'wprus_option', (mixed) $value, (string) $key );
```
**Description**  
Filter a single setting's option value.  

**Parameters**  
$value
> (mixed) the value of the option  
$key
> (string) the key used to retrieve the option value
___

#### wprus_settings_valid

```php
apply_filters( 'wprus_settings_valid', (bool) $valid, (mixed) $settings );
```
**Description**  
Filter wether the settings are valid ; called before running any other part of the plugin.  

**Parameters**  
$valid
> (bool) Wether the settings are valid.  

$settings
> (mixed ) Array of all the settings.  
___

#### wprus_settings_metaboxes

```php
apply_filters( 'wprus_settings_metaboxes', (mixed) $metaboxes );
```
**Description**  
Filter the setting's metaboxes of the plugin's screen.  

**Parameters**  
$metaboxes
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

#### wprus_template

```php
apply_filters( 'wprus_template_' . $template_slug, (string) $template_path );
```
**Description**  
Filter the template path of the main page and other elements of the plugin.  
This is actually a combination of filters, where the full name of the filter is determined by the `$template_slug` variable.
Possible values for `$template_slug`:
* `submit-settings-metabox`
* `add-site-metabox`
* `encryption-metabox`
* `ip-whitelist-metabox`
* `logs-metabox`
* `site-metabox`
* `site-metabox-template`
* `export-metabox`
* `import-metabox`
* `main-settings-page`
* `log-row`

**Parameters**  
$template_path
> (string) The path of the template.  
___

#### wprus_sanitize_settings

```php
apply_filters( 'wprus_sanitize_settings', (mixed) $sanitized_settings );
```
**Description**  
Filter the settings after sanitatization.  

**Parameters**  
$sanitized_settings
> (mixed) Array of all the settings after sanitatization.  
___

#### wprus_excluded_meta_keys

```php
apply_filters( 'wprus_excluded_meta_keys', (mixed) $excluded_meta_keys );
```
**Description**  
Filter the meta keys excluded from selection for synchronization (by default, keys referring to redundant or site-specific data).  

**Parameters**  
$excluded_meta_keys
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
    'source_domain',
);
```  
___

#### wprus_excluded_meta_keys_like

```php
apply_filters( 'wprus_excluded_meta_keys_like', (mixed) $excluded_meta_keys_like_expressions );
```
**Description**  
Filter the meta keys `LIKE` clauses used to exclude groups of meta keys from selection for synchronization (by default, keys referring to redundant or site-specific data).    

**Parameters**  
$excluded_meta_keys_like_expressions
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

#### wprus_init_notification_hooks

```php
apply_filters( 'wprus_init_notification_hooks', (bool) $init_notification_hooks );
```
**Description**  
Filter wether to initialise the notification hooks for the current request.  
**Warning:** Must be added **before** WordPress runs the `init` action.  

**Parameters**  
$init_notification_hooks
> (bool) If truthy, hooks will be initialised and notifications will be sent to remote sites upon user changes. Set to a falsy value to prevent notifications from being sent. Default `true` except in the case of user import.  
___

#### wprus_request_token_timeout

```php
apply_filters( 'wprus_request_token_timeout', (int) $token_timeout );
```
**Description**  
Filter the timeout for an authentication token request.  

**Parameters**  
$token_timeout
> (int) The timeout for a token request expressed in seconds. Default `1`.  
___

#### wprus_request_token_retry_timeout

```php
apply_filters( 'wprus_request_token_retry_timeout', (int) $token_retry_timeout );
```
**Description**  
Filter the retry timeout for an authentication token request, in case the first request failed.  

**Parameters**  
$token_retry_timeout
> (int) The retry timeout for token request expressed in seconds. Default `5`.  

___

#### wprus_is_authorized_remote

```php
apply_filters( 'wprus_is_authorized_remote', (bool) $is_authorized_remote, (string) $method, (string) $remote_addr, (mixed) $ip_whitelist );
```
**Description**  
Filter wether the received request should be authorised.  

**Parameters**  
$is_authorized_remote
> (bool) Whether the request is authorised.  

$method
> (string) The request's method - `post` or `get`.

$remote_addr
> (string) The IP address received in the `REMOTE_ADDR` header.  

$ip_whitelist
> (mixed) And array of strings as defined in the "IP Whitelist" settings.

___
