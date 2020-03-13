<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="wprus-container" data-postbox_class="wprus-security wprus-togglable">
	<table class="form-table">
		<tr>
			<th>
				<label for="wprus_proxy"><?php esc_html_e( 'Proxy IP Address', 'wprus' ); ?></label>
			</th>
			<td>
				<input class="regular-text" type="text" id="wprus_proxy" name="wprus[proxy]" value="<?php echo esc_attr( $proxy ); ?>">
				<p class="description"><?php esc_html_e( 'The IP address the current site exposes to the internet.', 'wprus' ); ?>
					<br>
					<strong><?php esc_html_e( 'Used by the remote sites to determine the origin of incoming actions. Leave empty if the server IP address is not altered before reaching the remote sites.', 'wprus' ); ?></strong>
				</p>
			</td>
		</tr>
		<tr>
			<th>
				<label for="wprus_auth_anonymous"><?php esc_html_e( 'Allow Anonymous Sync Operations', 'wprus' ); ?></label>
			</th>
			<td>
				<input type="checkbox" id="wprus_auth_anonymous" name="wprus[auth_anonymous]" value="1" <?php checked( $auth_anonymous ); ?>>
				<p class="description"><?php esc_html_e( 'Disable checking the origin of the actions received by the current site before synchronising.', 'wprus' ); ?>
					<br>
					<strong><?php esc_html_e( 'WARNING: this option can potentially open the website and its assciated connected sites to malicious operations. This is to be used ONLY in case of advanced maintenance that would invalidate server information on the remote site, such as prior to using the WP-CLI tool to manipulate user data.', 'wprus' ); ?></strong>
				</p>
			</td>
		</tr>
	</table>
</div>
