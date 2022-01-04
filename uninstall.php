<?php

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Exit if accessed directly
}

require_once WPRUS_PLUGIN_PATH . 'inc/class-wprus-database.php';

global $wpdb;

wp_clear_scheduled_hook( 'wprus_nonce_cleanup' );
wp_clear_scheduled_hook( 'wprus_logs_cleanup' );

$option_prefix = 'wprus';
$sql           = "DELETE FROM $wpdb->options WHERE `option_name` LIKE %s";

$wpdb->query( $wpdb->prepare( $sql, '%' . $option_prefix . '%' ) ); // @codingStandardsIgnoreLine

$meta_prefix = 'wprus';
$sql         = "DELETE FROM $wpdb->usermeta WHERE `meta_key` LIKE %s";

$wpdb->query( $wpdb->prepare( $sql, '%' . $meta_prefix . '%' ) ); // @codingStandardsIgnoreLine

$wprus_logs = Wprus_Database::get_table( 'wprus_logs' );
$wprus_nonce = Wprus_Database::get_table( 'wprus_logs' );

$sql = "DROP TABLE IF EXISTS {$wprus_nonce};";
$wpdb->query( $sql ); // @codingStandardsIgnoreLine

$sql = "DROP TABLE IF EXISTS {$wprus_logs};";
$wpdb->query( $sql ); // @codingStandardsIgnoreLine
