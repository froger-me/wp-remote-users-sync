<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div id="submitpost" class="submitbox wprus-submit">
	<div id="major-publishing-actions">
		<div id="publishing-action">
			<span class="spinner"></span>
			<?php submit_button( esc_attr( __( 'Save', 'wprus' ) ), 'primary', 'submit', false ); ?>
		</div>
		<div class="clear"></div>
	</div>
</div>
