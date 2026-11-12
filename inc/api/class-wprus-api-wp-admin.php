<?php
/**
 * Permanent administrative request endpoint.
 *
 * @package Wprus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Dispatch administrative requests through the shared WPRUS transport. */
class Wprus_Api_Wp_Admin extends Wprus_Api_Abstract {

	/**
	 * Register the response and local connection-test hooks.
	 *
	 * @param string         $endpoint Endpoint name.
	 * @param Wprus_Settings $settings Plugin settings.
	 * @param bool           $init_hooks Whether to register WordPress hooks.
	 */
	public function __construct( $endpoint, $settings, $init_hooks = false ) {
		parent::__construct( $endpoint, $settings, $init_hooks );

		if ( $init_hooks ) {
			add_action( 'wprus_after_handle_action_notification', array( $this, 'send_response' ), 10, 3 );

			if ( ! $this->doing_remote_action ) {
				add_action( 'wp_ajax_wprus_wp_admin_notify_ping_remote', array( $this, 'notify_ping_remote' ), 10, 0 );
			}
		}
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

		$handlers = apply_filters(
			'wprus_wp_admin_handlers',
			array( 'ping' => array( $this, 'handle_ping_notification' ) ),
			$this
		);

		if ( ! is_array( $handlers ) || ! isset( $handlers[ $_data['action'] ] ) || ! is_callable( $handlers[ $_data['action'] ] ) ) {
			return new WP_Error( 'wprus_unknown_admin_action', __( 'Unknown administrative action.', 'wprus' ), array( 'status' => 400 ) );
		}

		return call_user_func( $handlers[ $_data['action'] ], $_data['data'] );
	}

	/**
	 * Send the final administrative response.
	 *
	 * @param string $endpoint Request endpoint.
	 * @param array  $data Complete request data.
	 * @param mixed  $result Returned action result.
	 * @return void
	 */
	public function send_response( $endpoint, $data, $result ) {
		if ( 'wp-admin' !== $endpoint ) {
			return;
		}

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status     = is_array( $error_data ) && isset( $error_data['status'] ) ? absint( $error_data['status'] ) : 400;
			if ( $status < 200 || $status > 599 ) {
				$status = 400;
			}
			wp_send_json_error( $result->get_error_message(), $status );
		}

		if ( null === $result ) {
			wp_send_json_error( __( 'Administrative action did not return a result.', 'wprus' ), 500 );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Check an incoming connection test and return its result
	 *
	 * @param array $data Fields needed for a connection test.
	 * @return string|WP_Error Connection result.
	 */
	public function handle_ping_notification( $data ) {
		$actions = apply_filters(
			'wprus_actions',
			array(
				'login'    => __( 'Login', 'wprus' ),
				'logout'   => __( 'Logout', 'wprus' ),
				'create'   => __( 'Create', 'wprus' ),
				'update'   => __( 'Update', 'wprus' ),
				'delete'   => __( 'Delete', 'wprus' ),
				'password' => __( 'Password', 'wprus' ),
				'role'     => __( 'Roles', 'wprus' ),
				'meta'     => __( 'Metadata', 'wprus' ),
			)
		);

		if (
			! is_array( $data ) ||
			! isset( $data['base_url'], $data['target_action'], $data['direction'] ) ||
			! is_string( $data['base_url'] ) ||
			'' === $data['base_url'] ||
			! $this->settings->get_site( $data['base_url'] ) ||
			! is_string( $data['target_action'] ) ||
			! isset( $actions[ $data['target_action'] ] ) ||
			! in_array( $data['direction'], array( 'incoming', 'outgoing' ), true )
		) {
			Wprus_Logger::log( __( 'A ping was received but the initiator did not send the proper data.', 'wprus' ), 'info', 'db_log' );

			return new WP_Error( 'wprus_invalid_ping', __( 'Malformed data - the test request was received but the data could not be extracted.', 'wprus' ), array( 'status' => 400 ) );
		}

		$target_action   = $data['target_action'];
		$site            = $this->get_active_site_for_action( $target_action, $data['base_url'], $data['direction'] );
		$action_label    = $target_action;
		$direction_label = ( 'incoming' === $data['direction'] ) ? __( 'incoming', 'wprus' ) : __( 'outgoing', 'wprus' );
		$remote_addr     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$settings_class  = self::$settings_class;
		$class_vars      = get_class_vars( $settings_class );

		if ( isset( $class_vars['actions'][ $action_label ] ) ) {
			$action_label = $class_vars['actions'][ $action_label ];
		}

		if ( $site ) {
			do_action( 'wprus_ping_success', $target_action, $data, $remote_addr );

			// translators: %1$s is the sync action name, %2$s is the remote site URL, %3$s is the remote IP, %4$s is the direction.
			$message = __( 'Ping received for activated action "%1$s" from %2$s with remote IP %3$s (%4$s)', 'wprus' );

			Wprus_Logger::log(
				sprintf(
					$message,
					$action_label,
					$data['base_url'],
					$remote_addr,
					$direction_label
				),
				'success',
				'db_log'
			);

			// translators: %1$s is the sync action name, %2$s is the current site URL, %3$s is the remote IP, %4$s is the direction.
			$message = __( 'Ping success for action "%1$s" from %2$s with remote IP %3$s (%4$s)', 'wprus' );

			return sprintf(
				$message,
				$action_label,
				get_option( 'home' ),
				$remote_addr,
				$direction_label
			);
		}

		do_action( 'wprus_ping_failure', $target_action, $data, $remote_addr );

		// translators: %1$s is the sync action name, %2$s is the remote site URL, %3$s is the remote IP, %4$s is the direction.
		$message = __( 'Ping received for deactivated action "%1$s" from %2$s with remote IP %3$s (%4$s)', 'wprus' );

		Wprus_Logger::log(
			sprintf(
				$message,
				$action_label,
				$data['base_url'],
				$remote_addr,
				$direction_label
			),
			'warning',
			'db_log'
		);

		// translators: %1$s is the sync action name, %2$s is the current site URL, %3$s is the remote IP, %4$s is the direction.
		$message = __( '"%1$s" action is not activated on %2$s with remote IP %3$s (%4$s).', 'wprus' );
		$message = sprintf(
			$message,
			$action_label,
			get_option( 'home' ),
			$remote_addr,
			$direction_label
		);

		return new WP_Error( 'wprus_ping_disabled', $message, array( 'status' => 200 ) );
	}

	/**
	 * Send a test ping request to a remote website and send the response to the user interface
	 *
	 * @return void No value is returned.
	 */
	public function notify_ping_remote() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Error: unauthorized access - please reload the page and try again.', 'wprus' ), 403 );
		}

		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_UNSAFE_RAW );

		if ( ! wp_verify_nonce( $nonce, 'wprus_ping_nonce' ) ) {
			wp_send_json_error( __( 'Error: unauthorized access - please reload the page and try again.', 'wprus' ) );
		}

		$url       = filter_input( INPUT_POST, 'site_url', FILTER_VALIDATE_URL );
		$direction = filter_input( INPUT_POST, 'direction', FILTER_UNSAFE_RAW );
		$data      = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
		$success   = false;
		$payload   = false;

		if (
			! $url || ! is_array( $data ) ||
			! isset( $data['username'], $data['target_action'] ) ||
			! is_string( $data['username'] ) ||
			'' === $data['username'] ||
			! is_string( $data['target_action'] ) ||
			'' === $data['target_action'] ||
			! in_array( $direction, array( 'incoming', 'outgoing' ), true )
		) {
			wp_send_json_error( __( 'Malformed data - the test request was received but the data could not be extracted.', 'wprus' ), 400 );
		}

		$target_action = sanitize_key( $data['target_action'] );
		$payload       = array(
			'username' => $data['username'],
			'action'   => 'ping',
			'data'     => array(
				'base_url'      => get_option( 'home' ),
				'target_action' => $target_action,
				'direction'     => ( 'incoming' === $direction ) ? 'outgoing' : 'incoming',
			),
		);
		$response      = $this->fire_action(
			$url,
			$payload,
			true,
			5
		);

		do_action( 'wprus_ping_fired', $target_action, $payload['data'], $response );

		if ( 200 === absint( $response['response_code'] ) ) {
			$data = json_decode( $response['body'], true );

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$payload  = __( 'Error contacting the remote site: ', 'wprus' );
				$payload .= __( 'Payload error - ' ) . json_last_error_msg();
			} else {

				if ( $data['success'] ) {
					$success = true;
				}

				$payload = $data['data'];
			}
		}

		if ( ! $success && ! $payload ) {
			$payload = __( 'Error contacting the remote site: ', 'wprus' );

			if (
				! empty( $response['response_code'] ) &&
				200 !== $response['response_code'] &&
				! empty( $response['response_message'] )
			) {
				$payload .= $response['response_code'] . ' - ' . $response['response_message'];
			} else {
				// translators: %s is the error code.
				$payload .= ( ! empty( $response['response_code'] ) ) ? sprintf( __( ' - code: %s - ', 'wprus' ), $response['response_code'] ) : '';
				$payload .= __( 'an undefined error occured. Please make sure the address is correct and try again.', 'wprus' );
				$payload .= "\n";
				$payload .= __( 'On the remote site, please make sure the plugin is activated and that the permalinks are up to date by visiting the permalinks settings page.', 'wprus' );
			}

			if ( 404 === absint( $response['response_code'] ) ) {
				$payload .= "\n";
				$payload .= __( 'On the remote site, please make sure the permalinks are not using the "Plain" option.', 'wprus' );
			}
		}

		$log_type = ( $success ) ? 'success' : 'alert';

		Wprus_Logger::log( $payload, $log_type, 'db_log' );

		if ( ! $success ) {
			Wprus_Logger::log(
				array(
					'message' => 'Response data received from the remote site: ',
					$response,
				),
				'info',
				'db_log'
			);
		}

		if ( $success ) {
			wp_send_json_success( $payload );
		}

		wp_send_json_error( $payload );
	}
}
