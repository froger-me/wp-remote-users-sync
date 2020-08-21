<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="wprus-container" data-postbox_class="wprus-users wprus-togglable">
	<table class="form-table">
		<tr>
			<th>
				<label for="wprus_export_max"><?php esc_html_e( 'Max. #', 'wprus' ); ?></label>
			</th>
			<td>
				<input class="small-text" type="number" min="0" value="0" steps="1" id="wprus_export_max">
				<p class="description"><?php esc_html_e( 'Maximum number of users to export. Leave at 0 to export all users.', 'wprus' ); ?>
					<br>
					<?php esc_html_e( 'Remark: on sites with a large number of users, it is recommended to export users in multiple batches with the help of the "Offset" option below.', 'wprus' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th>
				<label for="wprus_export_offset"><?php esc_html_e( 'Offset', 'wprus' ); ?></label>
			</th>
			<td>
				<input class="small-text" type="number" min="0" steps="1" value="0" id="wprus_export_offset">
				<p class="description"><?php esc_html_e( 'Offset results - users are ordered by login name by default ; this option is useful when exporting users in batches.', 'wprus' ); ?>
				</p>
			</td>
		</tr>
	</table>
	<div class="row-export-roles wprus-export-row">
		<label for=""><?php esc_html_e( 'Filter users by role (leave empty to export all users):', 'wprus' ); ?></label>
		<select id="wprus_roles_export_select" class="wprus-select" multiple>
		<?php if ( ! empty( $roles ) ) : ?>
			<?php foreach ( $roles as $user_role ) : ?>
				<option value="<?php echo esc_attr( $user_role ); ?>"><?php echo esc_html( $user_role ); ?></option>
			<?php endforeach; ?>
		<?php endif; ?>
		</select>
		<div class="export-roles-options">
			<input type="checkbox" id="wprus_export_keep_roles" value="1"><label><?php esc_html_e( 'Export users with their roles', 'wprus' ); ?></label>
		</div>
	</div>
	<div class="row-export-metadata wprus-export-row">
		<label for=""><?php esc_html_e( 'List of metadata to export (leave empty to export none):', 'wprus' ); ?></label>
		<select id="wprus_metadata_export_select" class="wprus-select" multiple>
		<?php if ( ! empty( $meta_keys ) ) : ?>
			<?php foreach ( $meta_keys as $meta_key ) : ?>
				<option value="<?php echo esc_attr( $meta_key ); ?>"><?php echo esc_html( $meta_key ); ?></option>
			<?php endforeach; ?>
		<?php endif; ?>
		</select>
	</div>
	<div class="wprus-export-actions">
		<input type="button" id="wprus_export_trigger" class="button" value="<?php esc_html_e( 'Export ', 'wprus' ); ?>"><div class="spinner"></div>
		<div class="export-result">
			<span class="export-result-icons">
				<span class="dashicons dashicons-yes-alt success"></span>
				<span class="dashicons dashicons-dismiss failure"></span>
				<span class="dashicons dashicons-warning warning"></span>
			</span> <span id="export_message"></span> <a href="#"><?php esc_html_e( 'Download ', 'wprus' ); ?></a>
		</div>
	</div>
</div>
