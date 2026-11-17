# Filters

[Back to the developer guide](../../README.md#hooks-actions-and-filters)

WordPress passes a value through these filters at the stated points. A function registered for a filter must return the value that the plugin should continue using.

___

## wprus_enabled_api_endpoints

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

## wprus_api_endpoint

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

## wprus_api

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

## wprus_admin_handlers

```php
apply_filters( 'wprus_admin_handlers', array $handlers, Wprus_Api_Admin $api );
```

**Description**
Add functions or object methods that handle administrative operations received through `/wprus/admin/`.

**Parameters**
`$handlers`
> (array) Functions and object methods indexed by the value in the request's `action` field.

`$api`
> (`Wprus_Api_Admin`) The object receiving the administrative request.

Each handler receives the request's `data` array. It returns its result on success or a `WP_Error` on failure.

___

## wprus_wp_endpoints

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

## wprus_actions

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

## wprus_settings

```php
apply_filters( 'wprus_settings', (mixed) $settings );
```
**Description**  
Filter the settings' values.  

**Parameters**  
`$settings`
> (mixed) Array of all the settings.  
___

## wprus_option

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

## wprus_settings_valid

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

## wprus_settings_metaboxes

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

## wprus_sanitize_settings

```php
apply_filters( 'wprus_sanitize_settings', (mixed) $sanitized_settings );
```
**Description**  
Filter the settings after sanitatization.  

**Parameters**  
`$sanitized_settings`
> (mixed) Array of all the settings after sanitatization.  
___

## wprus_excluded_meta_keys

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

## wprus_excluded_meta_keys_like

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

## wprus_protected_meta_keys

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

## wprus_incoming_meta_exclusions

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

## wprus_init_notification_hooks

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

## wprus_fire_action_timeout

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

## wprus_request_token_timeout

```php
apply_filters( 'wprus_request_token_timeout', (int) $token_timeout );
```
**Description**  
Filter the timeout for an authentication token request.  

**Parameters**  
`$token_timeout`
> (int) The timeout for a token request expressed in seconds. Default `1`.  
___

## wprus_request_token_retry_timeout

```php
apply_filters( 'wprus_request_token_retry_timeout', (int) $token_retry_timeout );
```
**Description**  
Filter the retry timeout for an authentication token request, in case the first request failed.  

**Parameters**  
`$token_retry_timeout`
> (int) The retry timeout for token request expressed in seconds. Default `5`.  

___

## wprus_is_authorized_remote

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

## wprus_debug

```php
apply_filters( 'wprus_debug', bool $debug );
```

**Description**  
Filter wether to activate debug mode (PHP error logs, JavaScript console messages, JavaScript alerts if any).  

**Parameters**  
`$debug`
> (bool) Wether debug mode is activated - default `WP_DEBUG` constant value.  
___

## wprus_action_data

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

## wprus_async_url

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

## wprus_registered_integrations

```php
apply_filters( 'wprus_registered_integrations', $integrations );
```
**Description**  
Change the list of integrations before the plugin checks which ones are active. WooCommerce is already in the default `Wprus_Integration::$integrations` array. Its class is loaded from `inc/integration/woocommerce/`. Other plugins can load their own classes through `wprus_require_integration_file`.

**Parameters**  
`$integrations`
> (array) The integrations info. Structure:  
```php
array (
    $slug => array(                          // Unique name for this integration.
        'plugin'     => 'plugin/plugin.php', // Plugin file checked with is_plugin_active().
        'class_name' => 'Integration_Class', // Class to create when the plugin is active.
    ),
);
```
The class constructor receives `true`. The class does not need to extend `Wprus_Integration`.

___

## wprus_get_admin_template_name

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

## wprus_get_admin_template_args

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

## wprus_get_template_name

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

## wprus_get_template_args

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

## wprus_locate_template

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

## wprus_locate_admin_template

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

## wprus_locate_template_paths

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

