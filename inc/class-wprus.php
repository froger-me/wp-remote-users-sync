<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Wprus {
	protected static $tables;

	protected $authorised_endpoints;
	protected $wprus_settings;

	public function __construct( $wprus_settings, $init_hooks = false ) {
		$this->wprus_settings = $wprus_settings;

		if ( $init_hooks ) {
			// Add the API endpoints
			add_action( 'init', array( $this, 'add_endpoints' ), PHP_INT_MIN + 100, 0 );
			// Parse the endpoint request
			add_action( 'parse_request', array( $this, 'parse_request' ), 10, 0 );

			// Setup the API endpoint vars
			add_filter( 'query_vars', array( $this, 'add_query_vars' ), 10, 1 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/
	public static function activate() {

		$result = self::maybe_create_or_upgrade_db();

		if ( ! $result ) {
			die( esc_html( __( 'Failed to create the necessary database table(s).', 'wprus' ) ) );
		}

		set_transient( 'wprus_flush', 1, 60 );
		wp_cache_flush();
	}

	public static function deactivate() {
		global $wpdb;

		$prefix = $wpdb->esc_like( '_transient_wprus_' );
		$sql    = "DELETE FROM $wpdb->options WHERE `option_name` LIKE '%s'";

		$wpdb->query( $wpdb->prepare( $sql, $prefix . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function uninstall() {
		include_once WPRUS_PLUGIN_PATH . 'uninstall.php';
	}

	public static function maybe_create_or_upgrade_db() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = '';

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE {$wpdb->collate}";
		}

		$table = self::get_table( 'wprus_nonce' );
		$sql   =
			'CREATE TABLE ' . $table . ' (
				id int(12) NOT NULL auto_increment,
				nonce varchar(255) NOT NULL,
				expiry int(12) NOT NULL,
				PRIMARY KEY (id),
				KEY nonce (nonce)
			)' . $charset_collate . ';';

		dbDelta( $sql );

		$table = $wpdb->get_var( "SHOW TABLES LIKE '" . self::get_table( 'wprus_nonce' ) . "'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( self::get_table( 'wprus_nonce' ) !== $table ) {

			return false;
		}

		$table = self::get_table( 'wprus_logs' );
		$sql   =
			'CREATE TABLE ' . $table . ' (
				id int(12) NOT NULL auto_increment,
				timestamp int(12) NOT NULL,
				type varchar(10) NOT NULL,
				message text NOT NULL,
				data text,
				PRIMARY KEY (id),
				KEY timestamp (timestamp)
			)' . $charset_collate . ';';

		dbDelta( $sql );

		$table = $wpdb->get_var( "SHOW TABLES LIKE '" . self::get_table( 'wprus_logs' ) . "'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( self::get_table( 'wprus_logs' ) !== $table ) {

			return false;
		}

		return true;
	}

	public static function get_table( $table = null ) {

		if ( is_null( $table ) ) {

			return $table;
		}

		if ( isset( self::$tables[ $table ] ) ) {

			return self::$tables[ $table ];
		}

		global $wpdb;

		$is_plugin_active_for_network = is_plugin_active_for_network( WPRUS_PLUGIN_FILE );
		$use_base_prefix              = array(
			'users',
			'usermeta',
		);

		$use_base_prefix = apply_filters( 'wprus_wpdb_use_base_prefix', $use_base_prefix );

		if ( is_multisite() ) {
			if ( $is_plugin_active_for_network ) {

				$use_base_prefix = array_merge(
					$use_base_prefix,
					array(
						'wprus_logs',
						'wprus_nonce',
					)
				);

				$use_base_prefix = apply_filters( 'wprus_wpdb_use_base_prefix_network_active', $use_base_prefix );

				if ( in_array( $table, $use_base_prefix, true ) ) {
					$table = $wpdb->base_prefix . $table;
				} else {
					$table = $wpdb->prefix . $table;
				}
			} elseif ( in_array( $table, $use_base_prefix, true ) ) {
				$table = $wpdb->base_prefix . $table;
			} else {
				$table = $wpdb->prefix . $table;
			}
		} else {
			$table = $wpdb->prefix . $table;
		}

		self::$tables[ $table ] = $table;

		return $table;
	}

	public static function locate_template( $template_name, $load = false, $required_once = true ) {
		$name     = str_replace( 'templates/', '', $template_name );
		$paths    = array(
			'plugins/wprus/templates/' . $name,
			'plugins/wprus/' . $name,
			'wprus/templates/' . $name,
			'wprus/' . $name,
		);
		$template = locate_template( apply_filters( 'wprus_locate_template_paths', $paths ) );

		if ( empty( $template ) ) {
			$template = WPRUS_PLUGIN_PATH . 'inc/templates/' . $template_name;
		}

		$template = apply_filters(
			'wprus_locate_template',
			$template,
			$template_name,
			str_replace( $template_name, '', $template )
		);

		if ( $load && '' !== $template ) {
			load_template( $template, $required_once );
		}

		return $template;
	}

	public static function locate_admin_template( $template_name, $load = false, $required_once = true ) {
		$template = apply_filters(
			'wprus_locate_admin_template',
			WPRUS_PLUGIN_PATH . 'inc/templates/admin/' . $template_name,
			$template_name,
			str_replace( $template_name, '', WPRUS_PLUGIN_PATH . 'inc/templates/admin/' )
		);

		if ( $load && '' !== $template ) {
			load_template( $template, $required_once );
		}

		return $template;
	}

	public function add_query_vars( $vars ) {
		$vars[] = '__wprus_api';
		$vars[] = 'action';
		$vars[] = 'wprus_data';
		$vars[] = 'wprus_file';
		$vars[] = 'nonce';

		return $vars;
	}

	public function add_endpoints() {

		if ( get_transient( 'wprus_flush' ) ) {
			delete_transient( 'wprus_flush' );
			flush_rewrite_rules();
		}

		$this->authorised_endpoints = array(
			'token' => 'token',
		);
		$this->authorised_endpoints = apply_filters( 'wprus_wp_endpoints', $this->authorised_endpoints );

		foreach ( $this->authorised_endpoints as $action => $url_suffix ) {
			add_rewrite_rule(
				'^wprus/' . $url_suffix . '/?$',
				'index.php?__wprus_api=1&action=' . $action,
				'top'
			);
		}

		add_rewrite_rule(
			'^wprus_download',
			'index.php?wprus_download=1',
			'top'
		);
	}

	public function parse_request() {
		global $wp;

		if ( isset( $wp->query_vars['__wprus_api'] ) ) {
			$action      = isset( $wp->query_vars['action'] ) ? $wp->query_vars['action'] : false;
			$api_actions = array_keys( $this->authorised_endpoints );

			if ( has_action( 'wprus_api_' . $action ) && in_array( $action, $api_actions, true ) ) {
				do_action( 'wprus_api_' . $action );
			} else {
				$this->parse_error();
			}

			exit();
		}
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected function parse_error() {
		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );

		include get_query_template( '404' );
	}
}
