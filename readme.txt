=== WP Remote Users Sync ===
Contributors: frogerme
Tags: sync, share login, multiple sites
Requires at least: 4.9.5
Tested up to: 5.4
Stable tag: trunk
Requires PHP: 7.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Synchronise WordPress Users across Multiple Sites.

== Description ==

If you run multiple websites and want to keep users separated, but synchronise them automatically and securely for specific user operations, then WP Remote Users Sync is the plugin to use.

== Overview ==

This plugin adds the following major features to WordPress:

* **WP Remote Users Sync admin page:** to manage remote sites, security settings, import/export users, and view activity logs.
* **Remote Sites:** manage an unlimited amount of connected sites with configuration for incoming and outgoing user actions (Login, Logout, Create, Update, Delete, Password, Role and Metadata).
* **Security:** WP Remote Users Sync is the **only** plugin available allowing users to be synchronised with true layers of security in place. All communications are OpensSSL AES-256-CBC encrypted, HMAC SHA256 signed, token-validated and IP-validated.
* **Import and Export Users:** connected websites' existing user base can be synchronised manually first thanks to the provided import/export tool.
* **Activity Logs:** when enabled, all communications between connected sites are logged for admin review and troubleshooting.
* **Synchronise all user data:** compatible out of the box with WooCommerce, Ultimate Membership, Theme My Login, Gravity Forms, and all user-related plugins as long as they rely on WordPress user metadata and manipulate users with the WordPress user functions.
* **Customizable:** developers can add their own user actions using action and filter hooks, and more - see the [developers documentation](https://github.com/froger-me/wp-remote-users-sync).
* **Unlimited websites, unlimited features:** there are no restrictions in the number of websites to connect together, and no premium version feature restrictions shenanigans - WP Remote Users Sync is fully-featured right out of the box.

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
Login and Logout actions are the least costly: the operation itself is done asynchronously, and if the Action Tokens are not expired at the time of the action, the operation is virtually costless (but also more susceptible to failure if the page did not load properly for whatever reason).  
Overall, performances should be marginally impacted.  

The main takeaway is this:

* The more websites are connected, the bigger the relatively negative impact on performances.
* The worse the connection between the remote sites is, the bigger the relatively negative impact on performances.

= How are user roles handled when a user is synchronised? =
Roles can be synchronised when the Create and Update actions are fired, with the action Role enabled, and matching transferred and accepted role settings.

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

More help can be found on <a href="https://wordpress.org/support/plugin/wp-remote-users-sync/">the WordPress support forum</a> and on <a href="https://github.com/froger-me/wp-remote-users-sync">Github</a>.  
Help is provided for general enquiries and bug fixes only: feature requests, extra integration or conflict resolution with third-party themes or plugins, and specific setup troubleshooting requests will not be addressed without a fee (transfer method and amount at the discretion of the plugin author).

== Changelog ==

= 1.1.4 =
* Bugfix - do not save the token for async actions: these tokens are invalidated immediately after use (nonce) and saving them triggers an Unauthorized access (invalid token) error for subsequent sync actions.

= 1.1.3 =
* Rely on `get_option( 'home' )` instead of `home_url` to get the homepage URL to avoid conflicts with plugins (in particular translation plugins) and themes filtering the value.
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
* Made sure all options are deleted upon plugin uninstall
* Adjusted template names
* Minor fixes and refactor
* Added 14 action and 16 filter hooks
* Added developers documentation

= 1.0 =
* First version