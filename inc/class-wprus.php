<?php
/**
 * Register plugin requests and manage plugin database tables.
 *
 * @package Wprus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/** Register the plugin's request URLs and database tables. */
class Wprus {
	/**
	 * Cached database table names.
	 *
	 * @var array
	 */
	protected static $tables;

	/**
	 * Names of accepted request endpoints.
	 *
	 * @var array
	 */
	protected $authorised_endpoints;
	/**
	 * Request rules registered by this plugin.
	 *
	 * @var array
	 */
	protected $registered_rewrite_rules;
	/**
	 * Plugin settings object.
	 *
	 * @var Wprus_Settings
	 */
	protected $wprus_settings;

	/**
	 * Store settings and optionally register request hooks.
	 *
	 * @param Wprus_Settings $wprus_settings Plugin settings object.
	 * @param bool           $init_hooks Whether to register WordPress hooks.
	 */
	public function __construct( $wprus_settings, $init_hooks = false ) {
		$this->wprus_settings = $wprus_settings;

		if ( $init_hooks ) {
			// Add the API endpoints.
			add_action( 'init', array( $this, 'add_endpoints' ), PHP_INT_MIN + 100, 0 );
			// Parse the endpoint request.
			add_action( 'parse_request', array( $this, 'parse_request' ), 10, 0 );

			// Set up the API endpoint variables.
			add_filter( 'query_vars', array( $this, 'add_query_vars' ), 10, 1 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	/**
	 * Create plugin tables and clear cached settings.
	 *
	 * @return void No value is returned.
	 */
	public static function activate() {

		$result = self::maybe_create_or_upgrade_db();

		if ( ! $result ) {
			die( esc_html( __( 'Failed to create the necessary database table(s).', 'wprus' ) ) );
		}

		if ( ! get_option( 'wprus_plugin_version' ) ) {
			update_option( 'wprus_plugin_version', self::get_plugin_version() );
		}

		set_transient( 'wprus_flush', 1, 60 );
		wp_cache_flush();
	}

	/**
	 * Remove saved plugin data that expires automatically.
	 *
	 * @return void No value is returned.
	 */
	public static function deactivate() {
		global $wpdb;

		$prefix = $wpdb->esc_like( '_transient_wprus_' );
		$sql    = "DELETE FROM $wpdb->options WHERE `option_name` LIKE '%s'";

		$wpdb->query( $wpdb->prepare( $sql, $prefix . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Run the plugin removal script.
	 *
	 * @return void No value is returned.
	 */
	public static function uninstall() {
		include_once WPRUS_PLUGIN_PATH . 'uninstall.php';
	}

	/**
	 * Create or update the security code and activity log tables.
	 *
	 * @return bool Whether both required tables exist.
	 */
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

	/**
	 * Get a database table name with the appropriate prefix.
	 *
	 * @param string|null $table Database table name.
	 * @return string|null Prefixed table name, or null when no name was given.
	 */
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
				$use_base_prefix = apply_filters(
					'wprus_wpdb_use_base_prefix_network_active',
					array(
						'wprus_logs',
						'wprus_nonce',
					)
				);

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

	/**
	 * Find or load a front end template.
	 *
	 * @param string $template_name Template name.
	 * @param bool   $load Whether to load the template.
	 * @param bool   $required_once Whether to load the template only once.
	 * @return string Template path.
	 */
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

	/**
	 * Find or load an administrator template.
	 *
	 * @param string $template_name Template name.
	 * @param bool   $load Whether to load the template.
	 * @param bool   $required_once Whether to load the template only once.
	 * @return string Administrator template path.
	 */
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

	/**
	 * Register request values used by the plugin.
	 *
	 * @param array $vars Existing request value names.
	 * @return array Request value names.
	 */
	public function add_query_vars( $vars ) {
		$vars[] = '__wprus_api';
		$vars[] = 'action';
		$vars[] = 'wprus_data';
		$vars[] = 'wprus_file';
		$vars[] = 'nonce';

		return $vars;
	}

	/**
	 * Register URLs for incoming plugin requests.
	 *
	 * @return void No value is returned.
	 */
	public function add_endpoints() {
		$this->authorised_endpoints     = array(
			'token' => 'token',
		);
		$this->authorised_endpoints     = apply_filters( 'wprus_wp_endpoints', $this->authorised_endpoints );
		$this->registered_rewrite_rules = array();

		foreach ( $this->authorised_endpoints as $action => $url_suffix ) {
			$pattern = '^wprus/' . $url_suffix . '/?$';
			$target  = 'index.php?__wprus_api=1&action=' . $action;

			add_rewrite_rule( $pattern, $target, 'top' );

			$this->registered_rewrite_rules[ $pattern ] = $target;
		}

		add_action( 'wp_loaded', array( $this, 'maybe_flush_rewrite_rules' ), 10, 0 );
	}

	/** Refresh saved request rules when plugin rules or the installed version change. */
	public function maybe_flush_rewrite_rules() {
		$version       = self::get_plugin_version();
		$rules_changed = false;

		if ( get_option( 'permalink_structure' ) ) {
			$saved_rules = get_option( 'rewrite_rules', array() );

			foreach ( $this->registered_rewrite_rules as $target ) {

				if ( ! is_array( $saved_rules ) || ! in_array( $target, $saved_rules, true ) ) {
					$rules_changed = true;

					break;
				}
			}
		}

		if ( ! get_transient( 'wprus_flush' ) && ! $rules_changed && get_option( 'wprus_plugin_version' ) === $version ) {
			return;
		}

		flush_rewrite_rules();
		delete_transient( 'wprus_flush' );
		update_option( 'wprus_plugin_version', $version );
	}

	/**
	 * Handle a request for a registered plugin URL.
	 *
	 * @return void No value is returned.
	 */
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

	/**
	 * Read the version from the installed plugin file.
	 *
	 * @return string Plugin version.
	 */
	protected static function get_plugin_version() {
		static $version = null;

		if ( null === $version ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			$plugin_data = get_plugin_data( WPRUS_PLUGIN_PATH . 'wprus.php', false, false );
			$version     = $plugin_data['Version'];
		}

		return $version;
	}

	/**
	 * Display the theme 404 page for an unknown plugin request.
	 *
	 * @return void No value is returned.
	 */
	protected function parse_error() {
		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );

		include get_query_template( '404' );
	}
}
