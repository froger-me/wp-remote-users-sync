<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="wprus-container" data-postbox_class="wprus-logs wprus-togglable">
	<table class="form-table wprus-logs-settings">
		<tr>
			<th>
				<label for="wprus_enable_logs"><?php esc_html_e( 'Enable Logs', 'wprus' ); ?></label>
			</th>
			<td>
				<input type="checkbox" id="wprus_enable_logs" name="wprus[logs][enable]" <?php checked( (bool) $logs_settings['enable'], true ); ?>>
			</td>
		</tr>
		<tr>
			<th>
				<label for="wprus_logs_min_num"><?php esc_html_e( 'Number of Log Entries', 'wprus' ); ?></label>
			</th>
			<td>
				<input class="regular-text" type="number" id="wprus_logs_min_num" name="wprus[logs][min_num]" value="<?php echo esc_attr( $logs_settings['min_num'] ); ?>"> <input type="button" value="
				<?php
				echo esc_html(
					// translators: %d is the current number of log entries
					sprintf( __( 'Clear All (%d entries)', 'wprus' ), $num_logs )
				);
				?>
				" class="button logs-clean-trigger">
				<p class="howto">
					<?php esc_html_e( 'Number of log entries to display, and the minimum number of rows to keep in the database during cleanup. Logs are cleaned up automatically every hour. The number indicated in the "Clear All" button is the real current number of rows in the database, and clicking it deletes all of them.', 'wprus' ); ?>
				</p>
			</td>
		</tr>
	</table>
	<div class="logs-container">
		<div id="logs_view">
			<?php echo $logs; // @codingStandardsIgnoreLine ?>
		</div>
		<button class="button" id="wprus_log_refresh"><?php esc_html_e( 'Refresh', 'wprus' ); ?></button>		
	</div>

</div>
