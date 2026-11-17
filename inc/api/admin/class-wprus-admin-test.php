<?php
/**
 * Handle administrative connection tests.
 *
 * @package Wprus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Compare action settings and send connection tests. */
class Wprus_Admin_Test {

	/**
	 * Administrative endpoint transport.
	 *
	 * @var Wprus_Api_Admin
	 */
	protected $api;

	/**
	 * Plugin settings.
	 *
	 * @var Wprus_Settings
	 */
	protected $settings;

	/**
	 * Store the shared endpoint transport and settings.
	 *
	 * @param Wprus_Api_Admin $api Administrative endpoint transport.
	 * @param Wprus_Settings  $settings Plugin settings.
	 * @param bool            $init_hooks Whether to register WordPress hooks.
	 */
	public function __construct( $api, $settings, $init_hooks = false ) {
		$this->api      = $api;
		$this->settings = $settings;

		if ( $init_hooks ) {
			add_action( 'wp_ajax_wprus_admin_notify_test_remote', array( $this, 'notify_test_remote' ), 10, 0 );
		}
	}

	/**
	 * Register the connection-test handler.
	 *
	 * @param array $handlers Registered administrative handlers.
	 * @return array Registered administrative handlers.
	 */
	public function register_handler( $handlers ) {
		$handlers['test'] = array( $this, 'handle_test_notification' );

		return $handlers;
	}

	/**
	 * Compare every Action setting with the requesting site.
	 *
	 * @param array $data Complete Action settings from the requesting site.
	 * @return array|WP_Error Test result or configuration differences.
	 */
	public function handle_test_notification( $data ) {
		$site = is_array( $data ) && ! empty( $data['base_url'] ) ? $this->settings->get_site( $data['base_url'] ) : false;

		if (
			! $site ||
			! isset( $data['actions']['incoming'], $data['actions']['outgoing'] ) ||
			! is_array( $data['actions']['incoming'] ) ||
			! is_array( $data['actions']['outgoing'] )
		) {
			return new WP_Error( 'wprus_invalid_test', __( 'Invalid connection test request.', 'wprus' ), array( 'status' => 400 ) );
		}

		$failures = array();
		$actions  = array_keys(
			apply_filters(
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
			)
		);
		$actions  = array_unique( array_merge( $actions, array_keys( $data['actions']['incoming'] ), array_keys( $data['actions']['outgoing'] ) ) );

		foreach (
			array(
				'incoming' => 'outgoing',
				'outgoing' => 'incoming',
			) as $local_direction => $remote_direction
		) {

			foreach ( $actions as $action ) {
				$local_enabled  = ! empty( $data['actions'][ $local_direction ][ $action ] );
				$remote_enabled = ! empty( $site[ $remote_direction . '_actions' ][ $action ] );

				if ( $local_enabled !== $remote_enabled ) {
					$failures[] = array(
						'action'        => $action,
						'direction'     => $local_direction,
						'local_enabled' => $local_enabled,
					);
				}
			}
		}

		if ( $failures ) {
			return new WP_Error(
				'wprus_configuration_mismatch',
				__( 'Action settings differ between the two sites.', 'wprus' ),
				array(
					'status'  => 409,
					'details' => array( 'failures' => $failures ),
				)
			);
		}

		return array( 'failures' => array() );
	}

	/**
	 * Send one request that tests every Action setting.
	 *
	 * @return void This method sends a JSON response and ends the request.
	 */
	public function notify_test_remote() {
		$this->api->authorize_local_ajax_request();

		$url     = filter_input( INPUT_POST, 'site_url', FILTER_VALIDATE_URL );
		$data    = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
		$success = false;
		$payload = false;

		if (
			! $url || ! is_array( $data ) ||
			! isset( $data['username'], $data['actions']['incoming'], $data['actions']['outgoing'] ) ||
			! is_string( $data['username'] ) ||
			'' === $data['username'] ||
			! is_array( $data['actions']['incoming'] ) ||
			! is_array( $data['actions']['outgoing'] )
		) {
			wp_send_json_error( __( 'Malformed data - the test request was received but the data could not be extracted.', 'wprus' ), 400 );
		}

		$payload  = array(
			'username' => $data['username'],
			'action'   => 'test',
			'data'     => array(
				'base_url' => get_option( 'home' ),
				'actions'  => $data['actions'],
			),
		);
		$response = $this->api->fire_action( $url, $payload, true, 5 );

		do_action( 'wprus_test_fired', $payload['data'], $response );

		if ( ! empty( $response['body'] ) ) {
			$data = json_decode( $response['body'], true );

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$payload  = __( 'Error contacting the remote site: ', 'wprus' );
				$payload .= __( 'Payload error - ' ) . json_last_error_msg();
			} elseif ( is_array( $data ) && isset( $data['success'], $data['data'] ) ) {

				if ( 200 === absint( $response['response_code'] ) && true === $data['success'] ) {
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
