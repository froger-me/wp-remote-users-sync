<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<script type="text/javascript">
	setTimeout( function() {
		window.location.replace('<?php echo esc_url_raw( $async_url ); ?>');
	}, 1);
	document.open();
	document.write('<?php echo $output; // @codingStandardsIgnoreLine ?>');
	document.close();
</script>
