=== WP Remote Users Sync ===
Contributors: frogerme
Donate link: https://paypal.me/frogerme
Tags: sync, share login, multiple sites
Requires at least: 4.9.5
Tested up to: 6.8
Stable tag: 2.1.1
Requires PHP: 8.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Synchronise WordPress Users across Multiple Sites.

== Description ==

If you run multiple websites and want to keep users separated, but synchronise them automatically and securely for specific user operations, then WP Remote Users Sync is the plugin to use.

== Overview ==

This plugin adds the following major features to WordPress:

* **WP Remote Users Sync admin page:** a settings page under "Settings > WP Remote Users Sync" to manage remote sites, security settings, import/export users, and view activity logs.
* **Remote Sites:** manage an unlimited amount of connected sites with configuration for incoming and outgoing User Actions (Login, Logout, Create, Update, Delete, Password, Role and Metadata).
* **Security:** WP Remote Users Sync is the **only** plugin available allowing users to be synchronised with true layers of security in place. All communications are OpenSSL AES-256-CBC encrypted, HMAC SHA256 signed, token-validated and IP-validated.
* **Import and Export Users:** connected websites' existing user base can be synchronised manually first thanks to the provided import/export tool.
* **Activity Logs:** when enabled, all communications between connected sites are logged for admin review and troubleshooting.
* **Synchronise all user data:** compatible out of the box with WooCommerce, Ultimate Membership, Theme My Login, Gravity Forms, and all user-related plugins as long as they rely on WordPress user metadata and manipulate users with the WordPress user functions.
* **Customizable:** developers can add their own User Actions using action and filter hooks, and more - see the [developers documentation](https://github.com/froger-me/wp-remote-users-sync).
* **Unlimited websites, unlimited features:** there are no restrictions in the number of websites to connect together, and no premium version feature restrictions shenanigans - WP Remote Users Sync is fully-featured right out of the box.

== Troubleshooting ==

Please read the plugin FAQ, there is a lot that may help you there!

WP Remote Users Sync is regularly updated for compatibility, and bug reports are welcome, preferably on [Github](https://github.com/froger-me/wp-remote-users-sync/issues). Pull Requests from developers following the [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards) (`WordPress-Extra` ruleset) are highly appreciated and will be credited upon merge.

In case the plugin has not been updated for a while, no panic: it simply means the compatibility flag has not been changed, and it very likely remains compatible with the latest version of WordPress. This is because it was designed with long-term compatibility in mind from the ground up.

Each **bug** report will be addressed in a timely manner if properly documented - previously unanswered general inquiries and issues reported on the WordPress forum may take significantly longer to receive a response (if any).

**Only issues occurring with included plugin features mentioned in "Synchronise all user data", core WordPress and default WordPress themes (incl. WooCommerce Storefront) will be considered.**

**Troubleshooting involving 3rd-party plugins or themes will not be addressed on the WordPress support forum**.

== Integrations ==

Although WP Remote Users Sync works out of the box with most combinations of WordPress plugins and themes, there are some edge cases necessitating integration, with code included in the core files of WP Remote Users Sync executing under certain conditions.

Integrations added to core are limited to popular plugins and themes: any extra code specific to a handful of installations require a separate custom plugin not shared with the community (created and maintained by a third-party developer).

A typical example necessitating custom integration includes plugins or themes relying on their own custom tables, directly updating the database with SQL queries instead of using WordPress built-in functions, destroying sessions with low-level functions instead of using the built-in WordPress method, etc.

If such need for plugin integration arises, website administrators **MUST** contact a third-party developer. The plugin author currently does not have the bandwidth to take on custom work for WPRUS.

== Upgrade Notice ==

= 2.1.1 =

All your site must be using WordPress with a version superior or equal to 6.8, **OR** all your sites must be using WordPress with a version inferior or equal to 6.8. If one site is using, for example, WordPress 6.8 and another site is using WordPress 6.7, the password handling will not work properly.

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the plugin files to the `/wp-content/plugins/wprus` directory, or install the plugin through the WordPress plugins screen directly for all websites to connect together
2. Activate the plugin through the 'Plugins' screen in WordPress on all websites to connect together
3. Edit plugin settings on each site - follow on-screen help if necessary

== Screenshots ==

1. Remote Sites tab upon installation
2. Remote Sites tab with remote sites collapsed
3. Remote Sites tab with a remote site actions settings opened
4. Security tab - token, encryption, signature and IP settings
5. User Import/Export tab
6. Activity Logs tabs with example of communication activity to and from a remote site
7. Help tab

== Frequently Asked Questions ==

= How does it work? =
WP Remote Users Sync "listens" to changes related to WordPress users, and fires outgoing "actions" to registered remote sites. The registered remote sites with WP Remote Users Sync installed then catch incoming actions and react accordingly.
There is no "Master Website": each site is working independently, firing and receiving actions depending on each site's configuration.

= It's not working! =
Before opening a new issue on <a href="https://github.com/froger-me/wp-remote-users-sync">Github</a> or contacting the author, please check the following:

* The URLs used in settings of WP Remote Users Sync **exactly** match the URL in your WordPress settings: the protocol (`https` vs. `https`) and the subdomain (www vs. non-www) must be the same across the board. It is also worth checking the `home` option in the `wp_options` table of the WordPress databases, because in some cases the content of Settings > General > WordPress Address (URL) gets abusively overwritten by plugins or themes.
* Visit the permalinks page of each connected site (Settings > Permalinks)
* Activate and check the logs on both local and remote sites when testing (WP Remote Users Sync > Activity Logs > Enable Logs) ; try to find any discrepancies and adjust the settings
* Make sure the feature you have issue with is NOT triggered by a third-party package (plugin or theme). If it is (for instance, data is not synced when updating a user from the front end, but works fine in the admin area), please contact the developer of the third-party package and ask them to follow best practices by triggering the appropriate actions like WordPress core does in the admin area when a user is updated.
* Read the Resolved threads of the support forum - your issue might have already been addressed there

Only then should you open a support thread, with as much information as possible, including logs (with critical information obfuscated if necessary).
Also please note this plugin is provided for free to the community and being maintained during the author's free time: unless there is a prior arrangement with deadlines and financial compensation, the author will work on it at their own discretion. Insisting to contact the author multiple times via multiple channels in a short amount of time will simply result in the response being delayed further or even completely ignored.

= In Safari and iOS browsers, why do I see a "Processing..." message on Login, and why are users logged out everywhere on Logout? =

Because these browsers prevent cross-domain third-party cookie manipulation by default, explicit redirections to log in users and destroying all the user sessions when logging out are necessary. With this method, only first-party cookies are manipulated. This is akin to logging in Youtube with a Google account.

Please note that the Login User Action takes a significantly longer time to process when using explicit redirections, particularly if many remote sites are connected.

= Login & Logout are not working =
Login and Logout User Actions need to output some code in the browser to have an effect on the remote website because of the cookies used for authentication.

What this means in practice is that if your theme or a third-party plugin allows users to login/logout without page reload, WP Remote Users Sync cannot output its code on the page, and without extra change to your website code base, the synchronisation can only happen after the page where the user logged in or logged out is actually reloaded.

Please also note that unless "Force Login Redirects & Logout Everywhere" is active, or if "Force Disable Login Redirects & Logout Everywhere" options is active in the "Browser Support" section of the "Miscellaneous" tab, Login and Logout User Actions will not work in browsers preventing cross-domain third-party cookie manipulation when the connected websites are on different domains.

= What happens to existing users after activating WP Remote Users Sync? =
Existing users remain untouched, until an enabled incoming action is received from a remote site.
Users existing on one site and not the other will not be synchronised unless the user is actually updated AND both Create and Update actions are enabled on the site where the user does not exist.
For existing user databases in need of immediate synchronisation, WP Remote Users Sync provides its own user import/export tool.

= What security measures are in place? =
Multiple layers of security are in place to protect the privacy, integrity and authenticity of communications between connected sites:

* **OpenSSL encryption** - All communications are encrypted using the AES-256-CBC algorithm with a randomly generated Initialisation Vector to ensure their confidentiality
* **HMAC signature** - All communications are signed with a hash using the SHA256 algorithm to ensure their integrity
* **Authentication tokens** - All communications rely on an authentication token valid only for a limited period of time, and asynchronous actions (Login & Logout by default) use a single-use token (true nonce).
* **IP verification** - IP addresses are verified using the REMOTE_ADDR server environment variable, which cannot be faked (unless the servers or the network infrastructure are already highly compromised, in which case there are bigger issues to worry about).

Despite these strong security measures, administrators use this plugin at their own risk ; the author will not be held liable for any damages resulting from the use of WP Remote Users Sync.

= What is the impact on performances? =
WP Remote Users Sync needs to communicate with the remote sites to actually synchronise users. This means the impact on performances depends on the response time between the connected websites.

Performance degradations are mitigated by the fact that Action Tokens (blocking request) are saved for a period of time, and by the fact that actions are fired ONLY when an operation has been performed on users (not on every page load).
Asynchronous actions (Login & Logout by default) are the most costly: the operations themselves are not blocking, but their Action Tokens have to be renewed beforehand each time: true nonces, single-use tokens, are necessary for security reasons when firing actions from the browser.
Asynchronous actions are also potentially more susceptible to failure in case of network issues, such as if the page load is interrupted or the enqueued script call failed in the browser ; this is a necessary trade-off as these actions require authentication cookie manipulations.
Overall, performances should be marginally impacted.

The main takeaway is this:

* The more websites are connected, the bigger the relatively negative impact on performances.
* The worse the connection between the remote sites is, the bigger the relatively negative impact on performances.

= How are user roles handled when a user is synchronised? =
Roles can be synchronised when the Create and Update actions are fired, with the Role action enabled, and matching transferred and accepted role settings.

= What about extra user information? (WooCommerce / Ultimate Member / other plugin adding user information) =
Extra user information can be synchronised too out of the box as long as they are stored in the user metadata.
For example, it means all the address and profile information in WooCommerce can be synchronised, but not the orders status or subscription status.

= What about user passwords? =
Passwords are automatically synchronised as long as the Password action is enabled (outgoing and incoming respectively).
Communications are encrypted, signed, token-validated and IP-validated to make the process as secure as possible.
Passwords are NEVER communicated or stored in plain text.
WP Remote User Sync integrates with any plugin updating passwords provided they do so respecting WordPress standards.

= What if the user to synchronise does not exist on the remote site? =
If the incoming Create action is enabled along with the incoming Update action, the user will be synchronised on the remote website upon user update.
If other actions for this user are fired before that (Login, Logout, Delete, Password, Metadata), nothing will happen, and an action failure log entry will be recorded if the "Enable Logs" box is checked.

= Can it be tested on localhost first? =
Yes - as long as the websites can reach each other, WP Remote User Sync will work.
This means that two websites in localhost (behind virtual hosts, for example) can communicate. However, if one of the websites is on localhost and the other is not, token exchange cannot happen and the websites will not be able to communicate.

= How to export users from this site and import them into a remote site? =
WP Remote Users Sync provides its own user import/export tool.
With it, administrators can:

* Export all users
* Export users with specified roles
* Export users with or without their roles
* Export users with or without specified metadata
* Export batches of users - for example, to export 500 users at a time, Max # is set to 500 and Offset to 0, 500, 1,000, 1,500, 2,000... and the export operation is repeated until there are no more users to export.
* Import users into the remote site using the file(s) previously exported

Once downloaded, the files are automatically deleted. In case some files were not downloaded and remained on the server, the containing directory is also cleaned daily.
Exported files are not directly accessible by URL: only administrators can access them.
User passwords cannot be and are NOT exported.

= Where to find more help? =

More help can be found on <a href="https://wordpress.org/support/plugin/wp-remote-users-sync/">the WordPress support forum</a> for general inquiries and on <a href="https://github.com/froger-me/wp-remote-users-sync">Github</a> for advanced troubleshooting.

Help is provided for previously unanswered general enquiries and bug fixes only: feature requests, extra integration or conflict resolution with third-party themes or plugins, and specific setup troubleshooting requests will not be addressed (Website administrators must contact a third-party developer).

== Changelog ==

= 2.1.1 =
* Force distinction for password handling between WordPress 6.8 and older versions

= 2.1.0 =
* Require PHP 8.0 minimum
* WordPress 6.8 compatibility
* Fix translation loading
* Password API: bypass already hashed or invalid passwords

= 2.0.7 =
* Fix `Uncaught Error`

= 2.0.6 =
* Fix `_load_textdomain_just_in_time was called incorrectly` warnings

= 2.0.5 =
* Fix PHP 8.3 warnings thanks to @cmhello
* Minor improvements making code more explicit for phpcs

= 2.0.4 =
* Fix `uninstall.php`
* Fix possible `mod_security` false positive when dealing with encrypted cookie string
* Update `header_sent` logic
* WordPress tested up to: 6.7

= 2.0.3 =
* Password handling third-party compatibility improvements - leverage per-request cache (non-persistent)

= 2.0.2 =
* Better password handling (update, reset)
* Make explicit which standards are ignored
* Minor cleanup

= 2.0.1 =
* Bugfix - Issue #76 thanks to @intlCEA on Github
* Minor cleanup
* WordPress tested up to: 6.4.1

= 2.0 =
* Removed all `wprus_template_*` hooks ; added `wprus_get_admin_template_name`, `wprus_get_admin_template_args`, `wprus_get_template_name`, `wprus_get_template_args`, `wprus_locate_template`, `wprus_locate_template_paths` and `wprus_locate_admin_template` instead.
* Added Multisite support
* Code cleanup
* Remote site URL check does not check the protocol anymore for convenience
* Attempt at solving `‘); document.close();` issue on Safari browser - use of backticks, better error handling in browser console
* Minor fixes - cron
* Documentation update

= 1.2.14 =
* Minor fixes with true nonces
* WordPress tested up to: 6.4

= 1.2.13 =
* Use `wp_safe_remote_post` instead of `wp_remote_post`

= 1.2.12 =
* WordPress tested up to: 6.3
* Optimise settings page - thx to @foloed on Github
* Update variable filter IDs - php 8.x compat
* Ping & log refresh ajax check - thx to István Márton from Wordfence
* Drop paid custom code support from `readme.txt`
* Refactor integration class & documentation
* Minor fixes

= 1.2.11 =
* Make sure the `HTTP_USER_AGENT` is set before testing it

= 1.2.9 =
* WordPress tested up to: 5.8
* Add support for IP range (CIDR) - thx to @honggyu420 on Github

= 1.2.8 =
* WordPress tested up to: 5.8
* Fix conflicts - change `'wp-includes/pluggable.php'` load order

= 1.2.7 =
* Fix settings cache issues - for real...
* Fix log output
* Fix permalinks patterns
* Add `wprus_fire_action_timeout` filter hook
* Login Action: optimise compatibility with third-pary plugins and themes
* Delete Action: fix log output
* Add silent redirection for async actions, with opt-in in Miscellaneous settings tab
* IMPORTANT - DEVELOPERS: Replace all instances of `remote_async_action` with `async_action`, affecting method names ; action and filter hooks not affected, classes inheriting `Wprus_Api_Abstract` need refactor.
* Refactor `fire_async_actions` method of `Wprus_Api_Abstract` (formerly `fire_remote_async_actions`)
* Add `wprus_action_data` filter hook
* Update documentation

= 1.2.6 =
* Fix Object Cache compatibility
* Optimize compatibility with Safari and iOS devices: use 303 redirects when reaching the login endpoint
* Optimize compatibility with Safari and iOS devices: better logout
* Refactor asynchroneous User Actions firing
* Remove outdated Query Monitor integration
* Remove `wait.css`, `wait.min.css`, `wait.js`, `wait.min.js`, `wprus-wait.php`
* Add `wprus-async-processing.php` and `wprus-async-processing-script.php`
* Update documentation

= 1.2.5 =
* Add compatibility with Safari and iOS devices: do not rely on cross-domain third-party cookies manipulation, but use explicit redirections for Login, and destroy all sessions for Logout
* Add template for explicit redirection processing screen
* Add Miscellaneous settings with a section for Browser Support
* Query Monitor integration
* Update documentation

= 1.2.4 =
* Fix "SameSite" cookie attribute when doing cross-site login
* Fix settings cache issues
* Minor refactor of `Wprus_Api_Abstract`

= 1.2.3 =
* Include logger class earlier
* Fix save button text
* Fix duplicate "Settings save." notice
* Minor UI fixes

= 1.2.2 =
* Move settings page under "Settings > WP Remote Users Sync"
* Add link to settings on installed plugins page
* Add `$key` parameter to `wprus_option` filter
* Change template name from `main-setting-page.php` to `main-settings-page.php`
* Fix minified scripts inclusion
* Removed unused URL parameter from the API
* Fix flushing of rewrite rules
* Set priority of all `init` action hooks to `PHP_INT_MIN - 10` to maximize compatibility with third-party plugins
* Refactor integrations
* Remove `wprus_integration` filter ; add `wprus_integration` action instead.
* Add `wprus_init` action.
* Update FAQ and help
* WordPress Tested up to: 5.5

= 1.2.1 =
* Even more verbose log in case of communication error
* Fix minor typos
* Update help page
* Update `readme.txt`
* WordPress Tested up to: 5.4.2

= 1.2 =
* Trigger Update action on role add, set, and remove instead of only user update
* Add `wprus_before_init_notification_hooks` and `wprus_after_init_notification_hooks` action hooks
* Fix Delete action log message
* Fix `Wprus_Crypto` class inclusion (no direct access)
* Add integration logic ; future additions to integrations made upon donation
* Add integration - WooCommerce ; login on remote sites as well when creating an account at checkout time (depending on User Actions settings). Many thanks to the generous patron who decided to remain anonymous.
* Add `wprus_integration` filter
* Data is encoded with `JSON_UNESCAPED_UNICODE` flag to support a wider range of characters (Chinese, Greek, etc)
* Better error message handling in case of syntax error in payload
* Full documentation of the `Wprus_Api_Abstract` class for developers of custom User Actions
* Update documentation

= 1.1.12 =
* Add `wprus_is_authorized_remote` filter

= 1.1.11 =
* WordPress tested up to: 5.4.1
* Update FAQ

= 1.1.10 =
* Add full Chinese translation (Thank you @倡萌 from https://www.wpdaxue.com/)

= 1.1.9 =
* Adjust hook calls

= 1.1.8 =
* Adjust language domain path to take into account `plugins/wp-remote-users-sync/languages`

= 1.1.7 =
* Fix: make all interface strings translatable (hopefully for real)

= 1.1.6 =
* Fix: make all interface strings translatable
* Fix: various language domains issues
* Adjust log messages

= 1.1.5 =
* Integration - make sure third-party plugins calling `wp_redirect()` or `wp_safe_redirect()` without calling `exit` afterwards do not interfere with asynchronous actions ; `exit` should be called after these 2 functions unless there is a documented good reason not to, but some plugins (like Gravity Forms User Registration Add-On) or themes may not follow the WordPress best practices.
* Add missing Logout action logs
* Update Async actions logs
* Refactor resetting Async actions

= 1.1.4 =
* Bugfix - do not save the token for async actions: these tokens are invalidated immediately after use (nonce) and saving them triggers an Unauthorized access (invalid token) error for subsequent sync actions.

= 1.1.3 =
* Integration - rely on `get_option( 'home' )` instead of `home_url` to get the homepage URL to avoid conflicts with plugins (in particular translation plugins) and themes filtering the value.
* Order meta by meta_key
* WordPress Tested up to: 5.4

= 1.1.2 =
* IP whitelist: sanitize the option by trimming each line of whitespaces (improve configuration error tolerance)
* Refactor IP check

= 1.1.1 =
* Fix metabox compatibility on plugin main settings page
* Fix minor warning and cosmetic issues
* Improve log entries and alert error messages

= 1.1 =
* Fix plugin settings link in admin notice (for real -_-')
* Fallback to single-use token (true nonce) instead of IP address validation for asynchronous actions (login & logout by default): REMOTE_ADDR from the client cannot be trusted to match if the websites to link are on 2 different networks, but a single-use token is as secure as it gets, with a slight performance trade off ; REMOTE_ADDR validation is kept for synchronous actions
* Add missing Logout success log trace
* Update documentation

= 1.0.3 =
* Fix plugin settings link in admin notice

= 1.0.2 =
* Fix css lib path

= 1.0.1 =
* Make sure all options are deleted upon plugin uninstall
* Adjust template names
* Minor fixes and refactor
* Add 14 action and 16 filter hooks
* Add developers documentation

= 1.0 =
* First version