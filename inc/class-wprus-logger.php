<?php
/**
 * Store and display synchronization activity logs.
 *
 * @package Wprus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/** Manage database activity logs and requests from the settings page. */
class Wprus_Logger {

	/**
	 * Current activity log settings.
	 *
	 * @var array
	 */
	public static $log_settings;
	/**
	 * Labels for activity log types.
	 *
	 * @var array
	 */
	public static $log_types;

	/**
	 * Name of the settings class.
	 *
	 * @var string
	 */
	protected static $settings_class;

	/**
	 * Settings object for this instance.
	 *
	 * @var Wprus_Settings
	 */
	protected $settings;

	/**
	 * Set the log settings and register hooks when requested.
	 *
	 * @param Wprus_Settings $settings   Plugin settings.
	 * @param bool           $init_hooks Whether to register WordPress hooks.
	 */
	public function __construct( $settings, $init_hooks = false ) {
		$this->settings  = $settings;
		self::$log_types = array(
			'info'    => 'Info',
			'success' => 'Success',
			'warning' => 'Warning',
			'alert'   => 'Alert',
		);

		if ( $init_hooks ) {
			add_action( 'init', array( self::class, 'register_log_types' ) );
			add_action( 'wp', array( self::class, 'register_logs_cleanup' ) );
			add_action( 'wprus_logs_cleanup', array( self::class, 'clear_logs' ) );
			add_action( 'wp_ajax_wprus_refresh_logs', array( $this, 'refresh_logs_async' ), 10, 0 );
			add_action( 'wp_ajax_wprus_clear_logs', array( $this, 'clear_logs_async' ), 10, 0 );
		}

		self::init();
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	/**
	 * Load translated labels for activity log types.
	 *
	 * @return void No value is returned.
	 */
	public static function register_log_types() {
		self::$log_types = array(
			'info'    => __( 'Info', 'wprus' ),
			'success' => __( 'Success', 'wprus' ),
			'warning' => __( 'Warning', 'wprus' ),
			'alert'   => __( 'Alert', 'wprus' ),
		);
	}

	/**
	 * Schedule automatic removal of older log entries.
	 *
	 * @return void No value is returned.
	 */
	public static function register_logs_cleanup() {

		if ( ! wp_next_scheduled( 'wprus_logs_cleanup' ) ) {
			wp_schedule_event( time(), 'hourly', 'wprus_logs_cleanup' );
		}
	}

	/**
	 * Write a message to an available log destination.
	 *
	 * @param mixed  $expression  Message or structured details.
	 * @param string $extend      Log type or additional context.
	 * @param string $destination Log destination method.
	 * @return void No value is returned.
	 */
	public static function log( $expression, $extend = '', $destination = 'error_log' ) {

		if ( method_exists( self::class, $destination ) ) {
			self::$destination( $expression, $extend );
		}
	}

	/**
	 * Remove old entries while keeping the configured minimum number.
	 *
	 * @return bool|null Whether rows were removed, or nothing during installation.
	 */
	public static function clear_logs() {

		if ( defined( 'WP_SETUP_CONFIG' ) || defined( 'WP_INSTALLING' ) ) {
			return;
		}

		global $wpdb;

		$table = Wprus::get_table( 'wprus_logs' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup must use the current log table.
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table " // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				. "
				WHERE id <= (
					SELECT id
					FROM (
						SELECT id
						FROM $table " // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						. '
						ORDER BY id DESC
						LIMIT 1 OFFSET %d
						) temp
					);',
				self::$log_settings['min_num']
			)
		);

		return (bool) $result;
	}

	/**
	 * Count activity log entries.
	 *
	 * @return int Number of entries.
	 */
	public static function get_logs_count() {
		global $wpdb;

		$table = Wprus::get_table( 'wprus_logs' );
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE 1 = 1;" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Counts must reflect current logs.

		return absint( $count );
	}

	/**
	 * Render the latest activity log entries for the settings page.
	 *
	 * @return string Log entries as HTML.
	 */
	public static function get_logs() {
		global $wpdb;

		if ( ! isset( self::$log_settings ) ) {
			$instance = new self( new Wprus_Settings() );

			$instance->init();
		}

		$logs  = '';
		$table = Wprus::get_table( 'wprus_logs' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The view must show current log entries.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table ORDER BY timestamp ASC LIMIT %d;", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::$log_settings['min_num']
			)
		);

		if ( ! empty( $rows ) ) {

			foreach ( $rows as $log ) {
				$type_output = self::$log_types[ $log->type ];

				ob_start();

				wprus_get_admin_template(
					'log-row.php',
					array(
						'rows'        => $rows,
						'log'         => $log,
						'type_output' => $type_output,
					)
				);

				$logs .= ob_get_clean();
			}
		}

		return $logs;
	}

	/**
	 * Send current log entries to an authorized administrator.
	 *
	 * @return void No value is returned.
	 */
	public function refresh_logs_async() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error: unauthorized access - please reload the page and try again.', 'wprus' ),
				),
				403
			);
		}

		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_UNSAFE_RAW );

		if ( ! wp_verify_nonce( $nonce, 'wprus_logs_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error: unauthorized access - please reload the page and try again.', 'wprus' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'html'               => self::get_logs(),
				'clean_trigger_text' => sprintf(
					// translators: %d is the current number of log entries.
					__( 'Clear All (%d entries)', 'wprus' ),
					self::get_logs_count()
				),
			)
		);
	}

	/**
	 * Clear all log entries for an authorized administrator.
	 *
	 * @return void No value is returned.
	 */
	public function clear_logs_async() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error: unauthorized access - please reload the page and try again.', 'wprus' ),
				),
				403
			);
		}

		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_UNSAFE_RAW );

		if ( ! wp_verify_nonce( $nonce, 'wprus_logs_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error: unauthorized access - please reload the page and try again.', 'wprus' ),
				),
				403
			);
		}

		global $wpdb;

		$table = Wprus::get_table( 'wprus_logs' );

		$wpdb->query( "TRUNCATE TABLE $table;" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		wp_send_json_success();
	}

	/**
	 * Load the current activity log settings.
	 *
	 * @return void No value is returned.
	 */
	public function init() {
		self::$settings_class = get_class( $this->settings );
		self::$log_settings   = self::$settings_class::get_option(
			'logs',
			array(
				'enable'  => false,
				'min_num' => 100,
			)
		);
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	/**
	 * Store a message in the activity log table.
	 *
	 * @param mixed  $expression Message or structured details.
	 * @param string $type       Activity log type.
	 * @return array|false Stored entry or false when disabled or unsuccessful.
	 */
	protected static function db_log( $expression, $type ) {

		if ( ! self::$log_settings['enable'] ) {
			return false;
		}

		global $wpdb;

		$data = null;

		if ( ! is_string( $expression ) ) {

			if ( is_array( $expression ) ) {

				if ( isset( $expression['message'] ) ) {
					$message = $expression['message'];

					unset( $expression['message'] );
				}
			}

			if ( empty( $message ) ) {
				$message = __( '(No message)', 'wprus' );
			}

			$data = $expression;
		} else {
			$message = $expression;
		}

		$log   = array(
			'timestamp' => time(),
			'type'      => ( ! in_array( $type, array_keys( self::$log_types ), true ) ) ? 'info' : $type,
			'message'   => $message,
			'data'      => maybe_serialize( $data ),
		);
		$table = Wprus::get_table( 'wprus_logs' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Activity logs use their own database table.
		$result = $wpdb->insert( $table, $log );

		if ( (bool) $result ) {
			return $log;
		}

		return false;
	}

	/**
	 * Write a message to the PHP error log with its calling location.
	 *
	 * @param mixed  $expression     Message or structured details.
	 * @param string $extend_context Additional context.
	 * @return void No value is returned.
	 */
	protected static function error_log( $expression, $extend_context = '' ) {

		if ( ! is_string( $expression ) ) {
			$alternatives = array(
				array(
					'func' => 'print_r',
					'args' => array( $expression, true ),
				),
				array(
					'func' => 'var_export',
					'args' => array( $expression, true ),
				),
				array(
					'func' => 'json_encode',
					'args' => array( $expression ),
				),
				array(
					'func' => 'serialize',
					'args' => array( $expression ),
				),
			);

			foreach ( $alternatives as $alternative ) {

				if ( function_exists( $alternative['func'] ) ) {
					$func       = $alternative['func'];
					$expression = $func( ...$alternative['args'] );

					break;
				}
			}
		}

		$extend_context      = ( $extend_context ) ? ' - ' . $extend_context : '';
		$trace               = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$caller_line_holder  = $trace[1];
		$caller_class_holder = $trace[2];
		$class               = isset( $caller_class_holder['class'] ) ? $caller_class_holder['class'] : '';
		$type                = isset( $caller_class_holder['type'] ) ? $caller_class_holder['type'] : '';
		$function            = isset( $caller_class_holder['function'] ) ? $caller_class_holder['function'] : '';
		$context             = $class . $type . $function . ' on line ' . $caller_line_holder['line'] . $extend_context . ': ';

		error_log( $context . $expression ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
