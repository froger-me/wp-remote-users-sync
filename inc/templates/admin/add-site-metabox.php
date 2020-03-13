<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="wprus-container" data-postbox_class="wprus-site wprus-togglable">
	<input type="text" placeholder="<?php echo esc_attr( 'https://sub.domain.com' ); ?>" id="wprus_add_value"> <button class="button" id="wprus_add_trigger"><?php esc_html_e( 'Add', 'wprus' ); ?></button>
</div>
