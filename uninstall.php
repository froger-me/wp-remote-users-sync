<?php

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Exit if accessed directly
}

global $wpdb;

wp_clear_scheduled_hook( 'wprus_nonce_cleanup' );
wp_clear_scheduled_hook( 'wprus_logs_cleanup' );

$option_prefix = 'wprus';
$sql           = "DELETE FROM $wpdb->options WHERE `option_name` LIKE %s";

$wpdb->query( $wpdb->prepare( $sql, '%' . $option_prefix . '%' ) ); // @codingStandardsIgnoreLine

$meta_prefix = 'wprus';
$sql         = "DELETE FROM $wpdb->usermeta WHERE `meta_key` LIKE %s";

$wpdb->query( $wpdb->prepare( $sql, '%' . $meta_prefix . '%' ) ); // @codingStandardsIgnoreLine

// get table ##
$_table_logs = Wprus_Settings::get_wpdb_table( 'wprus_logs' );
$_table_nonce = Wprus_Settings::get_wpdb_table( 'wprus_logs' );

$sql = "DROP TABLE IF EXISTS {$_table_nonce};";
$wpdb->query( $sql ); // @codingStandardsIgnoreLine

$sql = "DROP TABLE IF EXISTS {$_table_logs};";
$wpdb->query( $sql ); // @codingStandardsIgnoreLine
