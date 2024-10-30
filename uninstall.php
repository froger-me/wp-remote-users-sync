<?php

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Exit if accessed directly
}

if ( ! defined( 'WPRUS_PLUGIN_PATH' ) ) {
	define( 'WPRUS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WPRUS_PLUGIN_URL' ) ) {
	define( 'WPRUS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WPRUS_PLUGIN_BASEFILE' ) ) {
	define( 'WPRUS_PLUGIN_FILE', plugin_basename( __FILE__ ) );
}

require_once WPRUS_PLUGIN_PATH . 'inc/class-wprus.php';

global $wpdb;

wp_clear_scheduled_hook( 'wprus_nonce_cleanup' );
wp_clear_scheduled_hook( 'wprus_logs_cleanup' );

$option_prefix = 'wprus';
$sql           = "DELETE FROM $wpdb->options WHERE `option_name` LIKE %s";

$wpdb->query( $wpdb->prepare( $sql, '%' . $option_prefix . '%' ) ); // @codingStandardsIgnoreLine

$meta_prefix = 'wprus';
$table       = Wprus::get_table( 'usermeta' );
$sql         = "DELETE FROM $table WHERE `meta_key` LIKE %s";

$wpdb->query( $wpdb->prepare( $sql, '%' . $meta_prefix . '%' ) ); // @codingStandardsIgnoreLine

$table = Wprus::get_table( 'wprus_nonce' );
$sql   = "DROP TABLE IF EXISTS {$table};";

$wpdb->query( $sql ); // @codingStandardsIgnoreLine

$table = Wprus::get_table( 'wprus_logs' );
$sql   = "DROP TABLE IF EXISTS {$table};";

$wpdb->query( $sql ); // @codingStandardsIgnoreLine
