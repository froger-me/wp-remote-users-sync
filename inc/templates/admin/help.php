<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div id="help" class="wprus-help wprus-togglable">
	<div class="wprus-help-list">
		<div class="wprus-help">
			<h2 class="wprus-help-title"><?php esc_html_e( 'How does it work?', 'wprus' ); ?></h2>
			<div class="wprus-help-inner">
				<p>
					<?php esc_html_e( 'WP Remote Users Sync "listens" to changes related to WordPress users, and fires outgoing "actions" to registered remote sites. The registered remote sites with WP Remote Users Sync installed then catch incoming actions and react accordingly.', 'wprus' ); ?><br/>
					<?php esc_html_e( 'There is no "Master Website": each site is working independently, firing and receiving actions depending on each site\'s configuration.', 'wprus' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'List of supported actions by default:', 'wprus' ); ?>
				</p>
				<ul>
					<li>
						<strong><?php esc_html_e( 'Login', 'wprus' ); ?></strong><?php esc_html_e( ' - Fired when a user logs in. The expected result is a user logged in on all registered remote sites accepting incoming Login actions.', 'wprus' ); ?><br/>
						<?php esc_html_e( 'The page needs to load without interruption and the network between the user and the remote sites be accessible for it to take effect, because this operation relies on browser cookies (the action is fired by a script asynchronously in the footer of the page after login).', 'wprus' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Logout', 'wprus' ); ?></strong><?php esc_html_e( ' - Fired when a user logs out. The expected result is a user logged out on all registered remote sites accepting incoming Logout actions.', 'wprus' ); ?><br/>
						<?php esc_html_e( 'The page needs to load without interruption and the network between the user and the remote sites be accessible for it to take effect, because this operation relies on browser cookies (the action is fired by a script asynchronously in the footer of the page after logout).', 'wprus' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Create', 'wprus' ); ?></strong><?php esc_html_e( ' - Fired when a user is created. The expected result is a synchronised user created on all registered remote sites accepting incoming Create actions (see Password, Roles and Metadata below for caveats).', 'wprus' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Update', 'wprus' ); ?></strong><?php esc_html_e( ' - Fired when a user is updated, either by the user themselves or another user. The expected result is up to date and synchronised user information on all registered remote sites accepting incoming Update actions (see Password, Roles and Metadata below for caveats).', 'wprus' ); ?><br>
						<?php esc_html_e( 'If the user does not exist on the remote site and the incoming Create action is enabled, a synchronised user is created instead.', 'wprus' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Delete', 'wprus' ); ?></strong><?php esc_html_e( ' - Fired when a user is deleted. The expected result is the user being deleted on all registered remote sites accepting incoming Delete actions.', 'wprus' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Password', 'wprus' ); ?></strong><?php esc_html_e( ' - Fired when a user resets their password. The expected result is the password being reset for the user on all registered remote sites accepting incoming Password actions.', 'wprus' ); ?><br/>
						<?php esc_html_e( 'This action also determines whether the passwords are synchronised or should be ignored during Create and Update actions.', 'wprus' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Roles', 'wprus' ); ?></strong><?php esc_html_e( ' - Does not fire an action of its own per se ; this action determines whether roles should be synchronised or ignored during Create and Update actions, depending on specified transferred outgoing and accepted incoming roles.', 'wprus' ); ?><br/>
						<?php esc_html_e( 'Unless the "Merge with existing roles" option is selected, the roles of the user are replaced with incoming roles.', 'wprus' ); ?><br/>
						<?php esc_html_e( 'If the incoming roles do not exist on or are not accepted by the remote site, they are ignored.', 'wprus' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Metadata', 'wprus' ); ?></strong><?php esc_html_e( ' - Fired when one or several user metadata are added / created / deleted, depending on specified transferred outgoing and accepted incoming metadata keys.', 'wprus' ); ?><br/>
						<?php esc_html_e( 'This action is particularly useful to synchronise user data added by plugins (such as address information in WooCommerce).', 'wprus' ); ?>
					</li>
				</ul>	
			</div>
		</div>
		<div class="wprus-help">
			<h2 class="wprus-help-title"><?php esc_html_e( 'It\'s not working!', 'wprus' ); ?></h2>
			<div class="wprus-help-inner">
				<p>
					<?php esc_html_e( 'Before opening a new thread in the WordPress Support forum, please check the following:', 'wprus' ); ?>
				</p>
				<ul>
					<li>
						<?php
							// translators: %1$s is the bold englisg word "exactly" ; %2$s is 'http'; %3$s is 'https'; %4$s is 'home' option key in the database ; %5$s is 'wp_options' table name
							printf( __( 'The URLs used in settings of WP Remote Users Sync %1$s match the URL in your WordPress settings: the protocol (%2$s vs. %3$s) and the subdomain (www vs. non-www) must be the same across the board. It is also worth checking the %4$s option in the %5$s table of the WordPress databases, because in some cases the content of Settings > General > WordPress Address (URL) gets abusively overwritten by plugins or themes.', 'wprus' ), '<strong>' . esc_html_e( 'exactly', 'wprus' ) . '</strong>', '<code>http</code>', '<code>https</code>', '<code>home</code>', '<code>wp_options</code>' ); // @codingStandardsIgnoreLine
						?>
					</li>
					<li><?php esc_html_e( 'Visit the permalinks page of each connected site (Settings > Permalinks)', 'wprus' ); ?></li>
					<li><?php esc_html_e( 'Activate and check the logs on both local and remote sites when testing (WP Remote Users Sync > Activity Logs > Enable Logs) ; try to find any discrepancies and adjust the settings', 'wprus' ); ?></li>
					<li><?php esc_html_e( 'Read the Resolved threads of the support forum - your issue might have already been addressed there', 'wprus' ); ?></li>
				</ul>
				<p>
					<?php esc_html_e( 'Only then should you open a support thread, with as much information as possible, including logs (with critical information obfuscated if necessary).', 'wprus' ); ?>
				</p>
			</div>
		</div>
		<div class="wprus-help">
			<h2 class="wprus-help-title"><?php esc_html_e( 'Login & Logout are not working', 'wprus' ); ?></h2>
			<div class="wprus-help-inner">
				<p>
					<?php esc_html_e( 'Login and Logout user actions need to output some code in the browser to have an effect on the remote website because of the cookies used for authentication.', 'wprus' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'What this means in practice is that if your theme or a third party plugin allows users to login/logout without page reload, WP Remote Users Sync cannot output its code on the page, and without extra change to your website code base, the synchronisation can only happen after the page where the user logged in or logged out is actually reloaded.', 'wprus' ); ?>
				</p>
			</div>
		</div>
		<div class="wprus-help">
			<h2 class="wprus-help-title"><?php esc_html_e( 'What happens to existing users after activating WP Remote Users Sync?', 'wprus' ); ?></h2>
			<div class="wprus-help-inner">
				<p>
					<?php esc_html_e( 'Existing users remain untouched, until an enabled incoming action is received from a remote site.', 'wprus' ); ?><br>
					<?php esc_html_e( 'Users existing on one site and not the other will not be synchronised unless the user is actually updated AND both Create and Update actions are enabled on the site where the user does not exist.', 'wprus' ); ?><br>
					<?php esc_html_e( 'For existing user databases in need of immediate synchronisation, WP Remote Users Sync provides its own user import/export tool.', 'wprus' ); ?>
				</p>
			</div>
		</div>
		<div class="wprus-help">
			<h2 class="wprus-help-title"><?php esc_html_e( 'What security measures are in place?', 'wprus' ); ?></h2>
			<div class="wprus-help-inner">
				<p>
					<?php esc_html_e( 'Multiple layers of security are in place to protect the privacy, integrity and authenticity of communications between connected sites:', 'wprus' ); ?>
				</p>
				<ul>
					<li>
						<strong><?php esc_html_e( 'OpenSSL encryption', 'wprus' ); ?></strong><?php esc_html_e( ' - All communications are encrypted using the AES-256-CBC algorithm with a randomly generated Initialisation Vector to ensure their confidentiality ; it is recommended to use a strong, randomly generated Action Encryption Key (the same value on all the connected websites).', 'wprus' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'HMAC signature', 'wprus' ); ?></strong><?php esc_html_e( ' - All communications are signed with a hash using the SHA256 algorithm to ensure their integrity ; it is recommended to use a strong, randomly generated Action Signature Key (the same value on all the connected websites).', 'wprus' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Authentication tokens', 'wprus' ); ?></strong><?php esc_html_e( ' - All communications rely on an authentication token (randomly generated). Asynchronous actions (Login & Logout by default) use a single-use token (true nonce), and synchronous actions use a token valid only for a limited period of time  ; it is recommended to keep the Action Token Validity Duration relatively short (default is 1,800 seconds or 30 minutes, and must be the same duration on all the connected websites). ', 'wprus' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'IP verification', 'wprus' ); ?></strong><?php esc_html_e( ' - IP addresses are verified using the REMOTE_ADDR server environment variable, which cannot be faked (unless the servers or the network infrastructure are already highly compromised, in which case there are bigger issues to worry about).', 'wprus' ); ?><br>
						<?php esc_html_e( 'For all synchronous actions, because they are fired server-side, IP verification can be enabled using the IP Whitelist setting with IP addresses to be compared against the remote IP address.', 'wprus' ); ?>
					</li>
				</ul>
				<p>
					<?php esc_html_e( 'It is HIGHLY recommended to use the IP Whitelist setting.', 'wprus' ); ?><br/>
					<?php esc_html_e( 'It is HIGHLY recommended to only connect https-enabled websites.', 'wprus' ); ?><br/>
					<?php esc_html_e( 'Despite these strong security measures, administrators use this plugin at their own risk ; the author will not be held liable for any damages resulting from the use of WP Remote Users Sync.', 'wprus' ); ?>
				</p>
			</div>
		</div>
		<div class="wprus-help">
			<h2 class="wprus-help-title"><?php esc_html_e( 'What is the impact on performances?', 'wprus' ); ?></h2>
			<div class="wprus-help-inner">
				<p>
					<?php esc_html_e( 'WP Remote Users Sync needs to communicate with the remote sites to actually synchronise users.', 'wprus' ); ?><br>
					<?php esc_html_e( 'This means the impact on performances depends on the response time between the connected websites.', 'wprus' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'In the worst case scenario, three requests are made to EACH remote site when doing synchronisation:', 'wprus' ); ?>
				</p>
				<ul>
					<li>
						<?php esc_html_e( 'A token renewal request that fails (blocking: a response is needed to save the token for later user, with a 1 second timeout by default)', 'wprus' ); ?>
					</li>
					<li>
						<?php esc_html_e( 'A second token renewal request to try again (blocking as well ; token renewal is tried again only once in case the first request failed, with a 5 seconds timeout by default)', 'wprus' ); ?>
					</li>
					<li>
						<?php esc_html_e( 'An action request (non-blocking: a response is not needed)', 'wprus' ); ?>
					</li>
				</ul>
				<p>
					<?php esc_html_e( 'Performance degradations are mitigated by the fact that Action Tokens (blocking request) are saved for a period of time for synchronous actions, and by the fact that actions are fired ONLY when an operation has been performed on users (not on every page load).', 'wprus' ); ?><br/>
					<?php esc_html_e( 'Asynchronous actions (Login & Logout by default) are the most costly: the operations themselves are not blocking, but their Action Tokens have to be renewed beforehand each time: true nonces, single-use tokens, are necessary for security reasons when firing actions from the browser.', 'wprus' ); ?><br/>
					<?php esc_html_e( 'Asynchronous actions are also potentially more susceptible to failure in case of network issues, such as if the page load is interrupted or the enqueued script call failed in the browser ; this is a necessary trade-off as these actions require authentication cookie manipulations.', 'wprus' ); ?><br/>
					<?php esc_html_e( 'Overall, performances should be marginally impacted.', 'wprus' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'The main takeaway is this: ', 'wprus' ); ?>
				</p>
				<ul>
					<li>
						<?php esc_html_e( 'The more websites are connected, the bigger the relatively negative impact on performances.', 'wprus' ); ?>
					</li>
					<li>
						<?php esc_html_e( 'The worse the connection between the remote sites is, the bigger the relatively negative impact on performances.', 'wprus' ); ?>
					</li>
				</ul>
			</div>
		</div>
		<div class="wprus-help">
			<h2 class="wprus-help-title"><?php esc_html_e( 'How are user roles handled when a user is synchronised?', 'wprus' ); ?></h2>
			<div class="wprus-help-inner">
				<p>
					<?php esc_html_e( 'Roles can be synchronised when the Create and Update actions are fired, with the Role action enabled, and matching transferred and accepted role settings.', 'wprus' ); ?><br/>
					<?php esc_html_e( 'When synchronising, the following role are assigned exclusively to the user and will not be merged: administrator, editor, author, contributor, subscriber. All other roles can be merged upon incoming action if the "Merge with existing roles" box is checked.', 'wprus' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'Consider the following scenario:', 'wprus' ); ?>
				</p>
				<ul>
					<li>
						<?php esc_html_e( 'Site 1 with outgoing Create, Update and Role actions enabled, with roles "administrator", "shop_manager" and "custom_role1" in the "List of roles to transfer" field', 'wprus' ); ?>
					</li>
					<li>
						<?php esc_html_e( 'Site 2 with incoming Create, Update and Role actions enabled, with role "shop_manager" in the "List of roles to accept" field, and the "Merge with existing roles" box checked', 'wprus' ); ?>
					</li>
					<li>
						<?php esc_html_e( 'A user john_doe56 with the role "administrator", "shop_manager" and "custom_role1" on Site 1, and "author" on Site 2', 'wprus' ); ?>
					</li>
					<li>
						<?php esc_html_e( 'Both Site 1 and Site 2 have WooCommerce enabled, therefore the role "shop_manager" exists on both sites, but "custom_role1" does not exist on Site 2.', 'wprus' ); ?>
					</li>
				</ul>
				<p>
					<?php esc_html_e( 'When the user john_doe56 is updated on Site 1, the following happens on Site 2:', 'wprus' ); ?>
				</p>
				<ul>
					<li>
						<?php esc_html_e( 'Site 2 receives  "administrator", "shop_manager" and "custom_role1" along with john_doe56\'s data', 'wprus' ); ?>
					</li>
					<li>
						<?php esc_html_e( '"administrator" and "custom_role1" are not part of the accepted roles ; was the list of accepted roles left empty to accept all roles, "custom_role1" would not be a valid role on Site 2 and would not be accepted anyway', 'wprus' ); ?>
					</li>
					<li>
						<?php esc_html_e( '"shop_manager" is part of the accepted roles, and is a valid role on Site 2', 'wprus' ); ?>
					</li>
					<li>
						<?php esc_html_e( 'john_doe56\'s new synchronised roles on Site 2 are "author" and "shop_manager"', 'wprus' ); ?>
					</li>
				</ul>
			</div>
		</div>
		<div class="wprus-help">
			<h2 class="wprus-help-title"><?php esc_html_e( 'What about extra user information? (WooCommerce / Ultimate Member / other plugin adding user information)', 'wprus' ); ?></h2>
			<div class="wprus-help-inner">
				<p>
					<?php esc_html_e( 'Extra user information can be synchronised too out of the box as long as they are stored in the user metadata.', 'wprus' ); ?><br>
					<?php esc_html_e( 'For example, it means all the address and profile information in WooCommerce can be synchronised, but not the orders status or subscription status.', 'wprus' ); ?><br>
					<?php esc_html_e( 'To enable extra information synchronisation, the Metadata action is used along with specified metadata keys in the transferred and accepted metadata fields.', 'wprus' ); ?>
				</p>
			</div>
		</div>
		<div class="wprus-help">
			<h2 class="wprus-help-title"><?php esc_html_e( 'What about user passwords?', 'wprus' ); ?></h2>
			<div class="wprus-help-inner">
				<p>
					<?php esc_html_e( 'Passwords are automatically synchronised as long as the Password action is enabled (outgoing and incoming respectively).', 'wprus' ); ?><br/>
					<?php esc_html_e( 'Communications are encrypted, signed, token-validated and IP-validated to make the process as secure as possible.', 'wprus' ); ?><br/>
					<?php esc_html_e( 'Passwords are NEVER communicated or stored in plain text.', 'wprus' ); ?><br/>
					<?php esc_html_e( 'WP Remote User Sync integrates with any plugin updating passwords provided they do so respecting WordPress standards.', 'wprus' ); ?>
				</p>
			</div>
		</div>
		<div class="wprus-help">
			<h2 class="wprus-help-title"><?php esc_html_e( 'What if the user to synchronise does not exist on the remote site?', 'wprus' ); ?></h2>
			<div class="wprus-help-inner">
				<p>
					<?php esc_html_e( 'If the incoming Create action is enabled along with the incoming Update action, the user will be synchronised on the remote website upon user update.', 'wprus' ); ?><br>
					<?php esc_html_e( 'If other actions for this user are fired before that (Login, Logout, Delete, Password, Metadata), nothing will happen, and an action failure log entry will be recorded if the "Enable Logs" box is checked.', 'wprus' ); ?>
				</p>
			</div>
		</div>
		<div class="wprus-help">
			<h2 class="wprus-help-title"><?php esc_html_e( 'Can it be tested on localhost first?', 'wprus' ); ?></h2>
			<div class="wprus-help-inner">
				<p>
					<?php esc_html_e( 'Yes - as long as the websites can reach each other, WP Remote User Sync will work.', 'wprus' ); ?><br>
					<?php esc_html_e( 'This means that two websites in localhost (behind virtual hosts, for example) can communicate. However, if one of the websites is on localhost and the other is not, token exchange cannot happen and the websites will not be able to communicate.', 'wprus' ); ?><br/>
				</p>
			</div>
		</div>
		<div class="wprus-help">
			<h2 class="wprus-help-title"><?php esc_html_e( 'How to export users from this site and import them into a remote site?', 'wprus' ); ?></h2>
			<div class="wprus-help-inner">
				<p>
					<?php esc_html_e( 'WP Remote Users Sync provides its own user import/export tool.', 'wprus' ); ?><br/>
					<?php esc_html_e( 'With it, administrators can:', 'wprus' ); ?>
				</p>
				<ul>
					<li>
						<?php esc_html_e( 'Export all users', 'wprus' ); ?>
					</li>
					<li>
						<?php esc_html_e( 'Export users with specified roles', 'wprus' ); ?>
					</li>
					<li>
						<?php esc_html_e( 'Export users with or without their roles', 'wprus' ); ?>
					</li>
					<li>
						<?php esc_html_e( 'Export users with or without specified metadata', 'wprus' ); ?>
					</li>
					<li>
						<?php esc_html_e( 'Export batches of users - for example, to export 500 users at a time, Max # is set to 500 and Offset to 0, 500, 1,000, 1,500, 2,000... and the export operation is repeated until there are no more users to export.', 'wprus' ); ?>
					</li>
					<li>
						<?php esc_html_e( 'Import users into the remote site using the file(s) previously exported', 'wprus' ); ?>
					</li>
				</ul>
				<p>
					<?php esc_html_e( 'Once downloaded, the files are automatically deleted. In case some files were not downloaded and remained on the server, the containing directory is also cleaned daily.', 'wprus' ); ?><br/>
					<?php esc_html_e( 'Exported files are not directly accessible by URL: only administrators can access them.', 'wprus' ); ?><br/>
					<?php esc_html_e( 'User passwords cannot be and are NOT exported.', 'wprus' ); ?>
				</p>
			</div>
		</div>
		<div class="wprus-help">
			<h2 class="wprus-help-title"><?php esc_html_e( 'Where to find more help?', 'wprus' ); ?></h2>
			<div class="wprus-help-inner">
				<p>
					<?php
					echo sprintf(
						// translators: %1$s is the WordPress support forum URL, %2$s is the Github URL
						esc_html__( 'More help can be found on %1$s for general inquiries and on %2$s for advanced troubleshooting.', 'wprus' ),
						'<a href="https://wordpress.org/support/plugin/wp-remote-users-sync/">' . esc_html_e( 'the WordPress support forum', 'wprus' ) . '</a>',
						'<a href="https://github.com/froger-me/wp-remote-users-sync">' . esc_html( 'Github' ) . '</a>'
					);
					?>
					<br>
					<?php esc_html_e( 'Help is provided for general enquiries and bug fixes only: feature requests, extra integration or conflict resolution with third-party themes or plugins, and specific setup troubleshooting requests will not be addressed without a fee (transfer method and amount at the discretion of the plugin author).', 'wprus' ); ?>
				</p>
			</div>
		</div>
	</div>
</div>
