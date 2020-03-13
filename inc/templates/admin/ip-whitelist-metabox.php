<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="wprus-container" data-postbox_class="wprus-security wprus-togglable">
	<textarea id="wprus_ip_whitelist" name="wprus[ip_whitelist]"><?php echo esc_html( $ips ); ?></textarea>
	<p class="howto">
		<?php esc_html_e( 'List of IP addresses of remote sites to authorise (one per line).', 'wprus' ); ?> <br/>
		<?php esc_html_e( 'To find the IP addresses, use the "Test" button on remote sites while Logs are enabled and check the local log results.', 'wprus' ); ?><br/>
		<?php esc_html_e( 'Leave blank to accept any IP address (not recommended).', 'wprus' ); ?>
	</p>
</div>
