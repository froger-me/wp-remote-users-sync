<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="wprus-container" data-postbox_class="wprus-misc wprus-togglable">
	<p><strong><?php esc_html_e( 'WARNING: Make sure these settings are the same for all the websites to synchronise.', 'wprus' ); ?></strong></p>
	<p class="howto">
		<?php esc_html_e( 'Because Safari and browsers on iOS devices do not allow cross-domain cookie manipulation by default for privacy reasons, Login User Action in these browsers is done with explicit redirections, and Logout User Action logs out the user in all browsers.', 'wprus' ); ?><br>
	</p>
	<table class="form-table wprus-browser-support-settings">
		<tr>
			<th>
				<label for="wprus_force_login_redirect"><?php esc_html_e( 'Force Login Redirects & Logout Everywhere', 'wprus' ); ?></label>
			</th>
			<td>
				<input type="checkbox" id="wprus_force_login_redirect" name="wprus[browser_support][force_login_logout_strict]" <?php checked( (bool) $browser_support_settings['force_login_logout_strict'], true ); ?>>
				<p class="howto">
					<?php esc_html_e( 'If checked, all browsers will behave like Safari and browsers on iOS devices.', 'wprus' ); ?><br>
					<?php esc_html_e( 'Useful to make sure Login and Logout User Actions work in all browsers, even if cross-domain cookies have been manually turned off in the browser\'s settings.', 'wprus' ); ?><br>
					<?php esc_html_e( 'Note: the Login User Action takes a significantly longer time to process when using explicit redirections, particularly if many remote sites are connected.', 'wprus' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th>
				<label for="wprus_disable_login_redirect"><?php esc_html_e( 'Force Disable Login Redirects & Logout Everywhere', 'wprus' ); ?></label>
			</th>
			<td>
				<input type="checkbox" id="wprus_disable_login_redirect" name="wprus[browser_support][force_disable_login_logout_strict]" <?php checked( (bool) $browser_support_settings['force_disable_login_logout_strict'], true ); ?>>
				<p class="howto">
					<p class="howto">
					<?php esc_html_e( 'If checked and if the connected remote sites are on different top level domains, Login and Logout User Action in Safari and browsers on iOS devices will not work unless users have manually accepted cross-site cookie tracking in their browser settings.', 'wprus' ); ?>
				</p>
				</p>
			</td>
		</tr>
		<tr>
			<th>
				<label for="wprus_silent_login_logout_strict"><?php esc_html_e( 'Use Silent Redirection', 'wprus' ); ?></label>
			</th>
			<td>
				<input type="checkbox" id="wprus_silent_login_logout_strict" name="wprus[browser_support][silent_login_logout_strict]" <?php checked( (bool) $browser_support_settings['silent_login_logout_strict'], true ); ?>>
				<p class="howto">
					<p class="howto">
					<?php esc_html_e( 'If checked, strict redirects will be done silently without message displayed to the end user.', 'wprus' ); ?><br/>
					<?php esc_html_e( 'Displaying a message makes the redirection take a longer time, but it provides information to the end user for a better user experience. Recommended to check this option if only few Remote Sites are contacted during login.', 'wprus' ); ?><br/>
					<?php esc_html_e( 'Silent redirection is faster, but if many Remote Sites need to be contacted at login, the end user does not get any information as to why the current operation is taking time.', 'wprus' ); ?>
				</p>
				</p>
			</td>
		</tr>
	</table>
</div>
