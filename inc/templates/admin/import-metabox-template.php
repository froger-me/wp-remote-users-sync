<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="wprus-container" data-postbox_class="wprus-users wprus-togglable">
	<table class="form-table">
		<tr id="wprus_import_file_dropzone">
			<th>
				<label for="wprus_import_file"><?php esc_html_e( 'Users File', 'wprus' ); ?></label>
			</th>
			<td>
				<input class="input-file hidden" type="file" id="wprus_import_file" value=""><label for="wprus_import_file" class="button"><?php esc_html_e( 'Upload File', 'wprus' ); ?></label> <input type="text" id="wprus_import_file_filename" placeholder="wprus-user-export-xxxx-xx-xx-xx-xx-xx.dat" value="" disabled=""> <input type="button" value="<?php esc_html_e( 'Import', 'wprus' ); ?>" class="button button-primary" id="wprus_import_file_trigger" disabled=""><div class="spinner"></div>
				<p class="howto">
					<?php esc_html_e( 'Requires a file previously exported with WP Remote Users Sync', 'wprus' ); ?>
				</p>
			</td>
		</tr>
	</table>
	<div id="wprus_import_results">
		<div class="summary"></div>
		<ul class="errors"></ul>
	</div>
</div>
