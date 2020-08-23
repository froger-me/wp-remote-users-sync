<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="wrap">
	<h1><?php esc_html_e( 'WP Remote Users Sync', 'wprus' ); ?></h1>
	<h2 class="nav-tab-wrapper">
		<a href="#" data-toggle="wprus-site" class="nav-tab nav-tab-active">
			<span class='dashicons dashicons-networking'></span> <?php esc_html_e( 'Remote Sites', 'wprus' ); ?>
		</a>
		<a href="#" data-toggle="wprus-security" class="nav-tab">
			<span class='dashicons dashicons-shield'></span> <?php esc_html_e( 'Security', 'wprus' ); ?>
		</a>
		<a href="#" data-toggle="wprus-users" class="nav-tab">
			<span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'Import / Export', 'wprus' ); ?>
		</a>
		<a href="#" data-toggle="wprus-logs" class="nav-tab">
			<span class='dashicons dashicons-visibility'></span> <?php esc_html_e( 'Activity Logs', 'wprus' ); ?>
		</a>
		<a href="#" data-toggle="wprus-help" class="nav-tab">
			<span class='dashicons dashicons-editor-help'></span> <?php esc_html_e( 'Help', 'wprus' ); ?>
		</a>
	</h2>
	<div class="wprus-meta-box-wrap wprus-ui-wait">
		<form id="wprus-form" method="post" action="options.php">
			<?php settings_fields( 'wprus' ); ?>
			<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
			<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
			<?php wp_nonce_field( 'wprus_import_export_nonce', 'wprus_import_export_nonce' ); ?>
			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-<?php echo 1 === get_current_screen()->get_columns() ? '1' : '2'; ?>">
					<div id="postbox-container-1" class="postbox-container">
						<?php do_meta_boxes( $hook_suffix, 'side', null ); ?>
					</div>
					<div id="postbox-container-2" class="postbox-container">
						<?php require WPRUS_PLUGIN_PATH . 'inc/templates/admin/help.php'; ?>
						<div id="sites_placeholder">
							<span><?php esc_html_e( 'Start with adding a Remote Site', 'wprus' ); ?></span>
						</div>
						<?php do_meta_boxes( $hook_suffix, 'normal', null ); ?>
						<?php do_meta_boxes( $hook_suffix, 'advanced', null ); ?>
					</div>
				</div>
				<br class="clear">
			</div>
		</form>
	</div>
</div>
