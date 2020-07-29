<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once WPRUS_PLUGIN_PATH . 'inc/integration/class-wprus-integration.php';

function wprus_integration() {

	if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
		require_once WPRUS_PLUGIN_PATH . 'inc/integration/woocommerce/class-wprus-woocommerce-integration.php';

		$wprus_woocommerce_integration = new Wprus_Woocommerce_Integration( true );
		$wprus_woocommerce_integration = apply_filters( 'wprus_integration', $wprus_woocommerce_integration, 'woocommerce' );
	}
}
add_action( 'wprus_loaded', 'wprus_integration', 10, 0 );