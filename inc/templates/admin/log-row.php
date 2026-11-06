<?php
/**
 * Display one activity log entry.
 *
 * @package Wprus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Activity log row.
 *
 * @var stdClass $log
 */
/**
 * Label for the log type.
 *
 * @var string $type_output
 */

?>

<div class="wprus-log-row <?php echo esc_attr( $log->type ); ?>">
	<span class="wprus-log-date"><?php echo esc_html( gmdate( 'Y-m-d H:i:s', $log->timestamp ) ); ?></span> - <span class="log-message"><?php echo esc_html( $type_output ); ?> - <?php echo esc_html( $log->message ); ?></span>
	<?php if ( ! empty( $log->data ) ) : ?>
	<pre class="trace">
		<?php echo htmlspecialchars( print_r( maybe_unserialize( $log->data ), true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.PHP.DevelopmentFunctions.error_log_print_r ?>
	</pre>
	<?php endif; ?>
</div>
