<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>

<div class="wprus-log-row <?php echo esc_attr( $log->type ); ?>">
	<span class="wprus-log-date"><?php echo esc_html( gmdate( 'Y-m-d H:i:s', $log->timestamp ) ); ?></span> - <span class="log-message"><?php echo esc_html( $type_output ); ?> - <?php echo esc_html( $log->message ); ?></span>
	<?php if ( ! empty( $log->data ) ) : ?>
	<pre class="trace">
		<?php print_r( maybe_unserialize( $log->data ) ); // @codingStandardsIgnoreLine ?>
	</pre>
	<?php endif; ?>
</div>
