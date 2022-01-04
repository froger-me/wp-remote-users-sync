<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Wprus_Database {

	protected static $tables = [];

	public function __construct() {

	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	/**
	 * Get WP Table name, adding correct prefix, depending if plugin is on multisite and network active or not
	 * 
	 * @since	1.2.8.1
	 * @param	string	$table
	 * @return	string
	*/
	public static function get_table( string $table = null ):?string
	{

		if( is_null( $table ) ){

			return null;

		}

		if( isset( self::$tables[$table] ) ){

			// error_log( 'Returning cache for table: '.$table.' --> '. self::$tables[$table] );
			return self::$tables[$table];

		}

		$is_plugin_active_for_network = \is_plugin_active_for_network( WPRUS_PLUGIN_BASEFILE );

		global $wpdb;

		$use_base_prefix = [
			'users',
			'usermeta'
		];

		$use_base_prefix = \apply_filters( 'wprus_wpdb_use_base_prefix', $use_base_prefix );

		if(
			\is_multisite()
		){

			if( $is_plugin_active_for_network ){

				$use_base_prefix = array_merge( $use_base_prefix, [
					'wprus_logs',
					'wprus_nonce'
				]);

				$use_base_prefix = \apply_filters( 'wprus_wpdb_use_base_prefix_network_active', $use_base_prefix );

				if( in_array( $table, $use_base_prefix ) ){

					// error_log( 'Table: '.$table.' should use base_prefix' );
					$table = $wpdb->base_prefix.$table;

				} else {
 
					$table = $wpdb->prefix.$table;

				}

			} else {

				if( in_array( $table, $use_base_prefix ) ){

					$table = $wpdb->base_prefix.$table;

				} else {

					$table = $wpdb->prefix.$table;

				}

			}

		} else {

			$table = $wpdb->prefix.$table;

		}

		// error_log( 'table: '.$table );

		self::$tables[$table] = $table;

		return $table;

	}

}
