# Actions

[Back to the developer guide](../../README.md#hooks-actions-and-filters)

WordPress runs these actions at the stated points. A function registered for an action can respond without replacing the plugin's value or result.

___

## wprus_validate_user_creation

```php
do_action( 'wprus_validate_user_creation', $username, $email, $data, $result );
```

An integration for another registration system can register this action to handle registration errors before WordPress inserts the account. The callback receives the final username, email address, user data, and the plugin's connected-site check result. The result is `true` when the username and email address are available everywhere. Otherwise it is a `WP_Error` with code `wprus_username_exists`, `wprus_email_exists`, or `wprus_identity_unavailable`. The callback must use its registration system's error handling to stop creation for any of these errors. A registered callback replaces the plugin's default rejection for that registration system.

___

## wprus_init

```php
do_action( 'wprus_init' );
```

**Description**
Fired before initializing the plugin's settings.   

___

## wprus_loaded

```php
do_action( 'wprus_loaded' );
```

**Description**  
Fired when all the required files have been loaded and the plugin settings are valid.   

___

## wprus_ready

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

## wprus_unauthorized_access

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

## wprus_authorized_access

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

## wprus_test_fired

```php
do_action( 'wprus_test_fired', (array) $test_data, (mixed) $response );
```

**Description**  
Fired when a connection test was sent to a remote site.

**Parameters**  
`$test_data`
> (array) The requesting site's address and all its incoming and outgoing Action settings.

`$response`
> (mixed) The response received from the remote site.  
___

## wprus_before_firing_async_actions

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

## wprus_after_firing_async_actions

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

## wprus_before_firing_action

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

## wprus_after_firing_action

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

## wprus_before_handle_action_notification

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

## wprus_after_handle_action_notification

```php
do_action( 'wprus_after_handle_action_notification', (string) $endpoint, (mixed) $data, (mixed) $result );
```

**Description**  
Fired after handling a request received from a connected site. The hook receives the returned result before the plugin sends its JSON response.

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


## wprus_before_init_notification_hooks

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

## wprus_after_init_notification_hooks

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

## wprus_require_admin_handler_files

```php
do_action( 'wprus_require_admin_handler_files', $api );
```

This action lets another plugin load files containing administrative handler classes. `$api` is the `Wprus_Api_Admin` object used to send and receive administrative requests.

___

## wprus_init_admin_handlers

```php
do_action( 'wprus_init_admin_handlers', $api, $settings, $register_local_requests );
```

This action lets another plugin create the administrative handler classes it loaded. `$api` sends and receives administrative requests, `$settings` contains the current plugin settings, and `$register_local_requests` states whether the handler should register requests started from the local settings page.

___

## wprus_require_integration_file

```php
do_action( 'wprus_require_integration_file', (string) $plugin_slug, (string) $class_name );
```
**Description**  
Fired when a registered plugin is active but its integration class has not been loaded. A callback can load the class here. If the class is still missing, the plugin also looks for `inc/integration/<slug>/class-wprus-integration-<slug>.php`.

**Parameters**  
`$plugin_slug`
> (string) The slug of the plugin integrated.  

`$class_name`
> (string) The name of the integration class.

___

## wprus_did_register_integration

```php
do_action( 'wprus_did_register_integration', $plugin_integration, $plugin_slug );
```
**Description**  
Fired after an active plugin's integration class has been loaded and created.

**Parameters**  
`$plugin_integration`
> The object created from the registered integration class.

`$plugin_slug`
> (string) The registered integration slug.

___

## wprus_did_register_integrations

```php
do_action( 'wprus_did_register_integrations', $integration );
```
**Description**

Fired after the plugin has checked every registered integration. It runs when the integration list is not empty, even if none of the listed plugins is active.

**Parameters**

`$integration`
> The shared `Wprus_Integration` object. It receives the plugin objects when `wprus_ready` runs.

___

## wprus_integration_run

```php
do_action( 'wprus_integration_run', $integration );
```
**Description**  
Fired after the plugin settings have been validated and the shared integration object has received the plugin objects.

**Parameters**  
`$integration`
> The shared `Wprus_Integration` object. Its public `wprus`, `api`, `settings`, and `wprus_logger` properties contain the corresponding plugin objects.

___


