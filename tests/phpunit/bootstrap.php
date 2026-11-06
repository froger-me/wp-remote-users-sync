<?php
/**
 * Prepare fixed settings before the plugin starts.
 *
 * @package Wprus
 */

// Load the shared test settings.
require_once dirname( __DIR__ ) . '/support/settings.php';
$GLOBALS['wprus_test_settings'] = wprus_test_settings();

tests_add_filter(
	'pre_option_wprus',
	static function () {
		return $GLOBALS['wprus_test_settings'];
	}
);

tests_add_filter(
	'wprus_ready',
	static function ( $plugin, $api, $settings, $logger ) {
		$GLOBALS['wprus_test_ready'] = array( $plugin, $api, $settings, $logger );
	},
	10,
	4
);
