<?php
/**
 * Route permanent administrative requests.
 *
 * @package Wprus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Dispatch administrative requests through the shared WPRUS transport. */
class Wprus_Api_Admin extends Wprus_Api_Abstract {

	/**
	 * Included administrative handlers.
	 *
	 * @var array
	 */
	protected $handlers = array();

	/**
	 * Create the administrative handlers and register local requests.
	 *
	 * @param string         $endpoint Endpoint name.
	 * @param Wprus_Settings $settings Plugin settings.
	 * @param bool           $init_hooks Whether to register WordPress hooks.
	 */
	public function __construct( $endpoint, $settings, $init_hooks = false ) {
		parent::__construct( $endpoint, $settings, $init_hooks );

		$init_handler_hooks = $init_hooks && ! $this->doing_remote_action;

		/**
		 * Lets another plugin load files containing administrative handler classes.
		 *
		 * @param Wprus_Api_Admin $api Administrative endpoint transport.
		 */
		do_action( 'wprus_require_admin_handler_files', $this );

		foreach ( (array) glob( __DIR__ . '/class-wprus-admin-*.php' ) as $handler_file ) {
			$handler_slug  = substr( basename( $handler_file, '.php' ), 18 );
			$class_parts   = array_map( 'ucfirst', explode( '-', $handler_slug ) );
			$handler_class = 'Wprus_Admin_' . implode( '_', $class_parts );

			require_once $handler_file;

			if ( class_exists( $handler_class ) ) {
				$this->handlers[ $handler_slug ] = new $handler_class( $this, $settings, $init_handler_hooks );
			}
		}

		/**
		 * Lets another plugin create its loaded administrative handlers.
		 *
		 * @param Wprus_Api_Admin $api                Administrative endpoint transport.
		 * @param Wprus_Settings  $settings           Plugin settings.
		 * @param bool            $init_handler_hooks Whether to register local administrator requests.
		 */
		do_action( 'wprus_init_admin_handlers', $this, $settings, $init_handler_hooks );
	}

	/** Find the requested administrative action and return its result. */
	public function handle_notification() {
		$_data = $this->get_data_post();

		if (
			! is_array( $_data ) ||
			! $this->validate( $_data ) ||
			! is_string( $_data['username'] ) ||
			! is_string( $_data['base_url'] ) ||
			! isset( $_data['action'], $_data['data'] ) ||
			! is_string( $_data['action'] ) ||
			! preg_match( '/^[a-z][a-z0-9_]*$/D', $_data['action'] ) ||
			! is_array( $_data['data'] ) ||
			! $this->settings->get_site( $_data['base_url'] ) ||
			( isset( $_data['data']['base_url'] ) && $_data['data']['base_url'] !== $_data['base_url'] )
		) {
			return new WP_Error( 'wprus_invalid_admin_request', __( 'Invalid administrative request.', 'wprus' ), array( 'status' => 400 ) );
		}

		$handlers = array();

		foreach ( $this->handlers as $handler_object ) {

			if ( method_exists( $handler_object, 'register_handler' ) ) {
				$handlers = $handler_object->register_handler( $handlers );
			}
		}

		$handlers = apply_filters( 'wprus_admin_handlers', $handlers, $this );
		$handler  = is_array( $handlers ) ? ( $handlers[ $_data['action'] ] ?? null ) : null;

		if ( $handler instanceof Closure ) {
			return $handler( $_data['data'] );
		}

		if ( is_string( $handler ) && function_exists( $handler ) ) {
			return $handler( $_data['data'] );
		}

		if (
			! is_array( $handler ) ||
			2 !== count( $handler ) ||
			! is_object( $handler[0] ) ||
			! is_string( $handler[1] ) ||
			! method_exists( $handler[0], $handler[1] )
		) {
			return new WP_Error( 'wprus_unknown_admin_action', __( 'Unknown administrative action.', 'wprus' ), array( 'status' => 400 ) );
		}

		return $handler[0]->{$handler[1]}( $_data['data'] );
	}

	/**
	 * Send the final administrative JSON response and end the request.
	 *
	 * @param mixed    $result Returned action result.
	 * @param int|null $status Status chosen before the action ran.
	 * @return void This method ends the request.
	 */
	public function send_response( $result, $status = null ) {

		if ( is_wp_error( $result ) ) {

			if ( null === $status ) {
				$error_data = $result->get_error_data();
				$status     = is_array( $error_data ) && isset( $error_data['status'] ) ? absint( $error_data['status'] ) : 400;
			}

			if ( $status < 400 || $status > 599 ) {
				$status = 400;
			}
		}

		if ( null === $result ) {
			$this->set_response_error( 'wprus_admin_no_result', 500 );

			$status = 500;
		}

		parent::send_response( $result, $status );
	}

	/**
	 * Authorize a local administrative AJAX request.
	 *
	 * @return void This method ends an unauthorized request.
	 */
	public function authorize_local_ajax_request() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Error: unauthorized access - please reload the page and try again.', 'wprus' ), 403 );
		}

		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_UNSAFE_RAW );

		if ( ! wp_verify_nonce( $nonce, 'wprus_test_nonce' ) ) {
			wp_send_json_error( __( 'Error: unauthorized access - please reload the page and try again.', 'wprus' ) );
		}
	}
}
