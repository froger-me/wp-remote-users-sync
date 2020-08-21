<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div data-url="<?php echo esc_attr( $site['url'] ); ?>" class="wprus-container" data-postbox_class="wprus-site wprus-togglable">
	<input type="hidden" name="wprus[sites][<?php echo esc_attr( $site_id ); ?>][url]" value="<?php echo esc_attr( $site['url'] ); ?>" >
	<div class="outgoing_actions">
		<h4><?php esc_html_e( 'Outgoing Actions', 'wprus' ); ?></h4>
		<p class="howto">
			<?php esc_html_e( 'Synchronise user data for actions happening on the current site, sent to the remote site.', 'wprus' ); ?>
		</p>
		<table class="wprus-actions">
			<?php foreach ( $site['outgoing_actions'] as $action_type => $action_value ) : ?>
				<tr>
					<td class="action-checkbox">
						<input type="checkbox" name="wprus[sites][<?php echo esc_attr( $site_id ); ?>][outgoing_actions][<?php echo esc_attr( $action_type ); ?>]" <?php checked( (bool) $action_value, true ); ?> value="1">
					</td>
					<td class="action-label">
						<label for=""><?php echo esc_html( $labels[ $action_type ] ); ?></label>
					</td>
					<td class="action-test-result">
						<span class="dashicons dashicons-yes-alt success"></span>
						<span class="dashicons dashicons-dismiss failure"></span>
					</td>
					<td data-direction="outgoing" data-action="<?php echo esc_attr( $action_type ); ?>" class="action-test">
						<button <?php echo ( (bool) $action_value ) ? '' : 'disabled="disabled"'; ?> class="button"><?php esc_html_e( 'Test', 'wprus' ); ?></button>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<hr/>
		<div class="row-outgoing-metadata wprus-site-row">
			<label for=""><?php esc_html_e( 'List of metadata to transfer (leave empty to transfer none):', 'wprus' ); ?></label>
			<select class="wprus-select" name="wprus[sites][<?php echo esc_attr( $site_id ); ?>][outgoing_meta][]" multiple>
			<?php if ( ! empty( $meta_keys ) ) : ?>
				<?php foreach ( $meta_keys as $meta_key ) : ?>
					<option<?php echo ( in_array( $meta_key, $site['outgoing_meta'], true ) ) ? esc_attr( ' selected="selected"' ) : ''; // @codingStandardsIgnoreLine ?> value="<?php echo esc_attr( $meta_key ); ?>"><?php echo esc_html( $meta_key ); ?></option>
				<?php endforeach; ?>
			<?php endif; ?>
			</select>
		</div>
		<div class="row-outgoing-roles wprus-site-row">
			<label for=""><?php esc_html_e( 'List of roles to transfer (leave empty to transfer none):', 'wprus' ); ?></label>
			<select class="wprus-select" name="wprus[sites][<?php echo esc_attr( $site_id ); ?>][outgoing_roles][]" multiple>
			<?php if ( ! empty( $roles ) ) : ?>
				<?php foreach ( $roles as $user_role ) : ?>
					<option<?php echo ( in_array( $user_role, $site['outgoing_roles'], true ) ) ? esc_attr( ' selected="selected"' ) : ''; // @codingStandardsIgnoreLine ?> value="<?php echo esc_attr( $user_role ); ?>"><?php echo esc_html( $user_role ); ?></option>
				<?php endforeach; ?>
			<?php endif; ?>
			</select>
		</div>
	</div>
	<div class="incoming_actions">
		<h4><?php esc_html_e( 'Incoming Actions', 'wprus' ); ?></h4>
		<p class="howto">
			<?php esc_html_e( 'Synchronise user data for actions happening on the remote site, received by the current site.', 'wprus' ); ?>
		</p>
		<table class="wprus-actions">
			<?php foreach ( $site['incoming_actions'] as $action_type => $action_value ) : ?>
				<tr>
					<td class="action-checkbox">
						<input type="checkbox" name="wprus[sites][<?php echo esc_attr( $site_id ); ?>][incoming_actions][<?php echo esc_attr( $action_type ); ?>]" <?php checked( (bool) $action_value, true ); ?> value="1">
					</td>
					<td class="action-label">
						<label for=""><?php echo esc_html( $labels[ $action_type ] ); ?></label>
					</td>
					<td class="action-test-result">
						<span class="dashicons dashicons-yes-alt success"></span>
						<span class="dashicons dashicons-dismiss failure"></span>
					</td>
					<td data-direction="incoming" data-action="<?php echo esc_attr( $action_type ); ?>" class="action-test">
						<button <?php echo ( (bool) $action_value ) ? '' : 'disabled="disabled"'; ?> class="button"><?php esc_html_e( 'Test', 'wprus' ); ?></button>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<hr/>
		<div class="row-incoming-metadata wprus-site-row">
			<label for=""><?php esc_html_e( 'List of metadata to accept (leave empty to accept all):', 'wprus' ); ?></label>
			<select class="wprus-select-tag" name="wprus[sites][<?php echo esc_attr( $site_id ); ?>][incoming_meta][]" multiple>
			<?php $meta_keys = array_unique( array_merge( $meta_keys, $site['incoming_meta'] ) ); ?>
			<?php if ( ! empty( $meta_keys ) ) : ?>
				<?php foreach ( $meta_keys as $meta_key ) : ?>
					<option<?php echo ( in_array( $meta_key, $site['incoming_meta'], true ) ) ? esc_attr( ' selected="selected"' ) : ''; // @codingStandardsIgnoreLine ?> value="<?php echo esc_attr( $meta_key ); ?>"><?php echo esc_html( $meta_key ); ?></option>
				<?php endforeach; ?>
			<?php endif; ?>
			</select>
		</div>
		<div class="row-incoming-roles wprus-site-row">
			<label for=""><?php esc_html_e( 'List of roles to accept (leave empty to accept all):', 'wprus' ); ?></label>
			<select class="wprus-select" name="wprus[sites][<?php echo esc_attr( $site_id ); ?>][incoming_roles][]" multiple>
			<?php if ( ! empty( $roles ) ) : ?>
				<?php foreach ( $roles as $user_role ) : ?>
					<option<?php echo ( in_array( $user_role, $site['incoming_roles'], true ) ) ? esc_attr( ' selected="selected"' ) : ''; // @codingStandardsIgnoreLine ?> value="<?php echo esc_attr( $user_role ); ?>"><?php echo esc_html( $user_role ); ?></option>
				<?php endforeach; ?>
			<?php endif; ?>
			</select>
			<div class="incoming-roles-options">
				<input type="checkbox" name="wprus[sites][<?php echo esc_attr( $site_id ); ?>][incoming_roles_merge]" <?php checked( (bool) $site['incoming_roles_merge'], true ); ?> value="1"><label><?php esc_html_e( 'Merge with existing roles', 'wprus' ); ?></label>
			</div>
		</div>
	</div>
	<div class="wprus-site-footer">
		<a class="deletion" href="#"><?php esc_html_e( 'Remove site configuration', 'wprus' ); ?></a>
	</div>
</div>
