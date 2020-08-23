<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Wprus_Logger {

	public static $log_settings;
	public static $log_types;

	protected static $settings_class;

	protected $settings;

	public function __construct( $settings, $init_hooks = false ) {
		$this->settings  = $settings;
		self::$log_types = array(
			'info'    => __( 'Info', 'wprus' ),
			'success' => __( 'Success', 'wprus' ),
			'warning' => __( 'Warning', 'wprus' ),
			'alert'   => __( 'Alert', 'wprus' ),
		);

		add_action( 'wp', array( get_class(), 'register_logs_cleanup' ) );
		add_action( 'wprus_logs_cleanup', array( get_class(), 'clear_logs' ) );
		add_action( 'wp_ajax_wprus_refresh_logs', array( $this, 'refresh_logs_async' ), 10, 0 );
		add_action( 'wp_ajax_wprus_clear_logs', array( $this, 'clear_logs_async' ), 10, 0 );

		self::init();
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public static function register_logs_cleanup() {

		if ( ! wp_next_scheduled( 'wprus_logs_cleanup' ) ) {
			wp_schedule_event( time(), 'hourly', 'wprus_logs_cleanup' );
		}
	}

	public static function log( $expression, $extend = '', $destination = 'error_log' ) {

		if ( method_exists( get_class(), $destination ) ) {
			call_user_func_array( array( get_class(), $destination ), array( $expression, $extend ) );
		}
	}

	public static function clear_logs( $force = false ) {

		if ( defined( 'WP_SETUP_CONFIG' ) || defined( 'WP_INSTALLING' ) ) {

			return;
		}

		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}wprus_logs
				WHERE id <= (
					SELECT id
					FROM (
						SELECT id
						FROM {$wpdb->prefix}wprus_logs
						ORDER BY id DESC
						LIMIT 1 OFFSET %d
						) temp
					);",
				self::$log_settings['min_num']
			)
		);

		return (bool) $result;
	}

	public static function get_logs_count() {
		global $wpdb;

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wprus_logs WHERE 1 = 1;" );

		return absint( $count );
	}

	public static function get_logs() {
		global $wpdb;

		$logs = '';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wprus_logs ORDER BY timestamp ASC LIMIT %d;",
				self::$log_settings['min_num']
			)
		);

		if ( ! empty( $rows ) ) {

			foreach ( $rows as $log ) {
				$type_output = self::$log_types[ $log->type ];

				ob_start();

				include apply_filters( // @codingStandardsIgnoreLine
					'wprus_template_log-row',
					WPRUS_PLUGIN_PATH . 'inc/templates/admin/log-row.php'
				);

				$logs .= ob_get_clean();
			}
		}

		return $logs;
	}

	public function refresh_logs_async() {
		wp_send_json_success(
			array(
				'html'               => self::get_logs(),
				'clean_trigger_text' => sprintf(
					// translators: %d is the current number of log entries
					__( 'Clear All (%d entries)', 'wprus' ),
					self::get_logs_count()
				),
			)
		);
	}

	public function clear_logs_async() {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wprus_logs;" );
	}

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

		$log = array(
			'timestamp' => time(),
			'type'      => ( ! in_array( $type, array_keys( self::$log_types ), true ) ) ? 'info' : $type,
			'message'   => $message,
			'data'      => maybe_serialize( $data ),
		);

		$result = $wpdb->insert(
			$wpdb->prefix . 'wprus_logs',
			$log
		);

		if ( (bool) $result ) {

			return $log;
		}

		return false;
	}

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
					$expression = call_user_func_array( $alternative['func'], $alternative['args'] );

					break;
				}
			}
		}

		$extend_context      = ( $extend_context ) ? ' - ' . $extend_context : '';
		$trace               = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 ); // @codingStandardsIgnoreLine
		$caller_line_holder  = $trace[1];
		$caller_class_holder = $trace[2];
		$class               = isset( $caller_class_holder['class'] ) ? $caller_class_holder['class'] : '';
		$type                = isset( $caller_class_holder['type'] ) ? $caller_class_holder['type'] : '';
		$function            = isset( $caller_class_holder['function'] ) ? $caller_class_holder['function'] : '';
		$context             = $class . $type . $function . ' on line ' . $caller_line_holder['line'] . $extend_context . ': ';

		error_log( $context . $expression ); // @codingStandardsIgnoreLine
	}

}
