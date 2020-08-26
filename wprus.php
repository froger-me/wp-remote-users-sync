<?php
/*
Plugin Name: WP Remote Users Sync
Plugin URI: https://github.com/froger-me/wp-remote-users-sync
Description: Synchronise WordPress Users across Multiple Sites.
Version: 1.2.4
Author: Alexandre Froger
Author URI: https://froger.me/
Text Domain: wprus
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! defined( 'WPRUS_PLUGIN_PATH' ) ) {
	define( 'WPRUS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WPRUS_PLUGIN_URL' ) ) {
	define( 'WPRUS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once WPRUS_PLUGIN_PATH . 'functions.php';
require_once ABSPATH . 'wp-includes/pluggable.php';
require_once WPRUS_PLUGIN_PATH . 'inc/class-wprus.php';

register_activation_hook( __FILE__, array( 'Wprus', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Wprus', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'Wprus', 'uninstall' ) );

function wprus_run() {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/user.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once WPRUS_PLUGIN_PATH . 'inc/class-wprus-logger.php';
	require_once WPRUS_PLUGIN_PATH . 'inc/class-wprus-settings.php';
	require_once WPRUS_PLUGIN_PATH . 'inc/api/class-wprus-api-abstract.php';
	require_once WPRUS_PLUGIN_PATH . 'inc/integration/class-wprus-integration.php';

	do_action( 'wprus_init' );
	Wprus_Integration::init();

	$settings       = new Wprus_Settings( true );
	$settings_class = get_class( $settings );

	if ( $settings->validate() ) {
		require_once WPRUS_PLUGIN_PATH . 'inc/class-wprus-crypto.php';
		require_once WPRUS_PLUGIN_PATH . 'inc/class-wprus-nonce.php';
		require_once WPRUS_PLUGIN_PATH . 'inc/class-wprus-import-export.php';

		$encryption_settings = $settings_class::get_option( 'encryption' );

		Wprus_Nonce::init( false, true, $encryption_settings['token_expiry'] );

		$wprus_logger = new Wprus_Logger( $settings, true );

		do_action( 'wprus_loaded' );

		$api                   = array();
		$enabled_api_endpoints = apply_filters(
			'wprus_enabled_api_endpoints',
			array(
				'login',
				'logout',
				'create',
				'update',
				'delete',
				'password',
				'role',
				'meta',
			)
		);

		foreach ( $enabled_api_endpoints as $api_endpoint ) {
			$api_endpoint_parts    = explode( '-', $api_endpoint );
			$api_endpoint_parts    = array_map( 'ucfirst', $api_endpoint_parts );
			$api_handler_classname = 'Wprus_Api_' . implode( '_', $api_endpoint_parts );
			$api_handler_path      = WPRUS_PLUGIN_PATH . 'inc/api/class-wprus-api-' . $api_endpoint . '.php';

			if (
				! class_exists( $api_handler_classname ) &&
				is_file( $api_handler_path )
			) {
				require_once $api_handler_path;

				$api[ $api_endpoint ] = new $api_handler_classname( $api_endpoint, $settings, true );
			} else {
				$api[ $api_endpoint ] = apply_filters( 'wprus_api_endpoint', false, $api_endpoint, $settings );
			}
		}

		$api        = apply_filters( 'wprus_api', $api );
		$api_update = isset( $api['update'] ) ? $api['update'] : false;
		$api_create = isset( $api['create'] ) ? $api['create'] : false;

		if ( $api_create && isset( $api['role'] ) ) {
			$api_create->init_role_handler( $api['role'] );
		}

		if ( $api_update && isset( $api['role'] ) ) {
			$api_update->init_role_handler( $api['role'] );
		}

		if ( $api_create && isset( $api['password'] ) ) {
			$api_create->init_password_handler( $api['password'] );
		}

		if ( $api_update && isset( $api['password'] ) ) {
			$api_update->init_password_handler( $api['password'] );
		}

		$wprus_import_export = new Wprus_Import_Export( true );
		$wprus               = new Wprus( $settings, true );

		do_action( 'wprus_ready', $wprus, $api, $settings, $wprus_logger );
	}
}
add_action( 'plugins_loaded', 'wprus_run', 10, 0 );
