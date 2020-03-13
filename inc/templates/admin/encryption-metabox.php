<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="wprus-container" data-postbox_class="wprus-security wprus-togglable">
	<table class="form-table">
		<tr>
			<th>
				<label for="wprus_nonce_expiry"><?php esc_html_e( 'Action Token Validity Duration', 'wprus' ); ?></label>
			</th>
			<td>
				<input class="small-text toggle" type="number" id="wprus_nonce_expiry" min="0" step="1" name="wprus[encryption][token_expiry]" value="<?php echo esc_attr( $encryption_settings['token_expiry'] ); ?>">
				<p class="description"><?php esc_html_e( 'Expressed in seconds, the duration after which generated action tokens expire and need to be renewed.', 'wprus' ); ?>
					<br>
					<strong><?php esc_html_e( 'WARNING: Make sure this value is the same for all the websites to synchronise.', 'wprus' ); ?></strong>
				</p>
			</td>
		</tr>
		<tr>
			<th>
				<label for="wprus_aes_key"><?php esc_html_e( 'Action Encryption Key', 'wprus' ); ?></label>
			</th>
			<td>
				<input class="regular-text toggle" autocomplete="new-password" type="password" id="wprus_aes_key" name="wprus[encryption][aes_key]" value="<?php echo esc_attr( $encryption_settings['aes_key'] ); ?>">
				<p class="description"><?php esc_html_e( 'Ideally a random string, used to encrypt remote operations data.', 'wprus' ); ?>
					<br>
					<strong><?php esc_html_e( 'WARNING: Make sure this value is the same for all the websites to synchronise.', 'wprus' ); ?></strong>
					<br>
					<strong class="alert"><?php esc_html_e( 'IMPORTANT: For security reasons, keep this value ABSOLUTELY SECRET.', 'wprus' ); ?></strong>
				</p>
			</td>
		</tr>
		<tr>
			<th>
				<label for="wprus_hmac_key"><?php esc_html_e( 'Action Signature Key', 'wprus' ); ?></label>
			</th>
			<td>
				<input class="regular-text toggle" autocomplete="new-password" type="password" id="wprus_hmac_key" name="wprus[encryption][hmac_key]" value="<?php echo esc_attr( $encryption_settings['hmac_key'] ); ?>">
				<p class="description"><?php esc_html_e( 'Ideally a random string, used to authenticate remote operations data.', 'wprus' ); ?>
					<br>
					<strong><?php esc_html_e( 'WARNING: Make sure this value is the same for all the websites to synchronise.', 'wprus' ); ?></strong>
					<br>
					<strong class="alert"><?php esc_html_e( 'IMPORTANT: For security reasons, keep this value ABSOLUTELY SECRET.', 'wprus' ); ?></strong>
				</p>
			</td>
		</tr>
	</table>
</div>
