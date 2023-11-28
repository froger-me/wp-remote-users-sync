<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

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
