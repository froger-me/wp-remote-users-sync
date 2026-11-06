<?php
/**
 * Print the script that completes an asynchronous redirect.
 *
 * @package Wprus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Destination for the redirected action.
 *
 * @var string $async_url
 */
/**
 * Page content displayed before redirecting.
 *
 * @var string $output
 */

?>
<script type="text/javascript">
	window.onerror = function(e) {
		console.log("WPRUS - Error: ", e);
		window.location.replace(`<?php echo esc_url_raw( $async_url ); ?>`);
	};
	setTimeout( function() {
		window.location.replace(`<?php echo esc_url_raw( $async_url ); ?>`);
	}, 1);
	document.open();
	document.write(`<?php echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>`);
	document.close();
</script>
