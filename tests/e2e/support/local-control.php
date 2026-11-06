<?php
/**
 * Read narrowly scoped browser-test results using the test run's secret.
 *
 * @package Wprus
 */

if ( ! defined( 'ANYAPE_WP_TEST_TOOLS_E2E' ) || ! ANYAPE_WP_TEST_TOOLS_E2E ) {
	return;
}

require_once WP_PLUGIN_DIR . '/wp-remote-users-sync/tests/support/class-wprus-test-peer.php';

add_filter(
	'wprus_debug',
	static function ( $debug ) {
		$script = isset( $_GET['wprus_test_script'] ) ? sanitize_key( wp_unslash( $_GET['wprus_test_script'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This temporary setting only selects a local test asset.
		if ( 'readable' === $script ) {
			return true;
		}
		if ( 'compact' === $script ) {
			return false;
		}
		return $debug;
	}
);

$wprus_test_identity = static function () {
	$token = isset( $_SERVER['HTTP_X_WPRUS_TEST_TOKEN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WPRUS_TEST_TOKEN'] ) ) : '';
	$hash  = get_option( 'anyape_wp_test_tools_e2e_auth_token_hash', '' );
	if ( ! is_string( $hash ) || '' === $hash || '' === $token || ! hash_equals( $hash, hash( 'sha256', $token ) ) ) {
		wp_send_json_error( 'Invalid test secret.', 403 );
	}
	$user = wp_get_current_user();
	wp_send_json_success( array( 'username' => $user->exists() ? $user->user_login : '' ) );
};
add_action( 'wp_ajax_wprus_tests_identity', $wprus_test_identity );
add_action( 'wp_ajax_nopriv_wprus_tests_identity', $wprus_test_identity );

$wprus_test_password = static function () {
	$token = isset( $_SERVER['HTTP_X_WPRUS_TEST_TOKEN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WPRUS_TEST_TOKEN'] ) ) : '';
	$hash  = get_option( 'anyape_wp_test_tools_e2e_auth_token_hash', '' );
	if ( ! is_string( $hash ) || '' === $hash || '' === $token || ! hash_equals( $hash, hash( 'sha256', $token ) ) ) {
		wp_send_json_error( 'Invalid test secret.', 403 );
	}
	$username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The per-run test secret is checked above.
	$password = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The test secret is checked above and passwords must remain unchanged.
	if ( 0 !== strpos( $username, 'wprus_test_' ) || ! is_string( $password ) ) {
		wp_send_json_error( 'Invalid test user.', 400 );
	}
	$user = get_user_by( 'login', $username );
	wp_send_json_success( array( 'matches' => $user && wp_check_password( $password, $user->user_pass, $user->ID ) ) );
};
add_action( 'wp_ajax_wprus_tests_password', $wprus_test_password );
add_action( 'wp_ajax_nopriv_wprus_tests_password', $wprus_test_password );

add_action(
	'wp_ajax_nopriv_wprus_tests_login',
	static function () {
		$token = isset( $_SERVER['HTTP_X_WPRUS_TEST_TOKEN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WPRUS_TEST_TOKEN'] ) ) : '';
		$hash  = get_option( 'anyape_wp_test_tools_e2e_auth_token_hash', '' );
		if ( ! is_string( $hash ) || '' === $hash || '' === $token || ! hash_equals( $hash, hash( 'sha256', $token ) ) ) {
			wp_send_json_error( 'Invalid test secret.', 403 );
		}
		$user = get_user_by( 'login', 'wprus_test_alice' );
		if ( ! $user ) {
			wp_send_json_error( 'Test user not found.', 404 );
		}
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, false, is_ssl() );
		wp_send_json_success();
	}
);

add_action(
	'wp_ajax_wprus_tests_nonce',
	static function () {
		$token = isset( $_SERVER['HTTP_X_WPRUS_TEST_TOKEN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WPRUS_TEST_TOKEN'] ) ) : '';
		$hash  = get_option( 'anyape_wp_test_tools_e2e_auth_token_hash', '' );
		if ( ! is_string( $hash ) || '' === $hash || '' === $token || ! hash_equals( $hash, hash( 'sha256', $token ) ) ) {
			wp_send_json_error( 'Invalid test secret.', 403 );
		}
		wp_send_json_success(
			array(
				'logs'   => wp_create_nonce( 'wprus_logs_nonce' ),
				'logout' => wp_create_nonce( 'log-out' ),
				'ping'   => wp_create_nonce( 'wprus_ping_nonce' ),
			)
		);
	}
);

add_action(
	'wp_ajax_wprus_tests_peer_mode',
	static function () {
		$token = isset( $_SERVER['HTTP_X_WPRUS_TEST_TOKEN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WPRUS_TEST_TOKEN'] ) ) : '';
		$hash  = get_option( 'anyape_wp_test_tools_e2e_auth_token_hash', '' );
		if ( ! is_string( $hash ) || '' === $hash || '' === $token || ! hash_equals( $hash, hash( 'sha256', $token ) ) ) {
			wp_send_json_error( 'Invalid test secret.', 403 );
		}
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The per-run test secret is checked above.
		if ( ! in_array( $mode, array( 'normal', 'timeout', 'malformed', 'disabled' ), true ) ) {
			wp_send_json_error( 'Invalid peer mode.', 400 );
		}
		update_option( 'wprus_tests_peer_mode', $mode, false );
		wp_send_json_success();
	}
);

add_action(
	'wp_ajax_wprus_tests_peer_sessions',
	static function () {
		$token = isset( $_SERVER['HTTP_X_WPRUS_TEST_TOKEN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WPRUS_TEST_TOKEN'] ) ) : '';
		$hash  = get_option( 'anyape_wp_test_tools_e2e_auth_token_hash', '' );
		if ( ! current_user_can( 'manage_options' ) || ! is_string( $hash ) || '' === $hash || '' === $token || ! hash_equals( $hash, hash( 'sha256', $token ) ) ) {
			wp_send_json_error( 'Invalid test request.', 403 );
		}
		$enabled  = isset( $_POST['enabled'] ) && '1' === $_POST['enabled']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The per-run test secret is checked above.
		$strict   = isset( $_POST['strict'] ) && '1' === $_POST['strict']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The per-run test secret is checked above.
		$silent   = isset( $_POST['silent'] ) && '1' === $_POST['silent']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The per-run test secret is checked above.
		$settings = get_option( 'wprus' );
		foreach ( $settings['sites'] as &$site ) {
			if ( 'https://wprus-peer.test' === $site['url'] ) {
				$site['outgoing_actions']['login']  = $enabled;
				$site['outgoing_actions']['logout'] = $enabled;
			}
		}
		unset( $site );
		$settings['browser_support']['force_login_logout_strict']         = $enabled && $strict;
		$settings['browser_support']['force_disable_login_logout_strict'] = false;
		$settings['browser_support']['silent_login_logout_strict']        = $enabled && $silent;
		$peer = get_option( 'wprus_tests_peer' );
		if ( $enabled && $peer instanceof Wprus_Test_Peer ) {
			$peer->users['wprus_test_alice']['logged_in'] = false;
			update_option( 'wprus_tests_peer', $peer, false );
		}
		update_option( 'wprus', $settings );
		wp_send_json_success();
	}
);

add_action(
	'wp_ajax_wprus_tests_peer_user_actions',
	static function () {
		$token = isset( $_SERVER['HTTP_X_WPRUS_TEST_TOKEN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WPRUS_TEST_TOKEN'] ) ) : '';
		$hash  = get_option( 'anyape_wp_test_tools_e2e_auth_token_hash', '' );
		if ( ! current_user_can( 'manage_options' ) || ! is_string( $hash ) || '' === $hash || '' === $token || ! hash_equals( $hash, hash( 'sha256', $token ) ) ) {
			wp_send_json_error( 'Invalid test request.', 403 );
		}
		$enabled  = isset( $_POST['enabled'] ) && '1' === $_POST['enabled']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The per-run test secret is checked above.
		$settings = get_option( 'wprus' );
		foreach ( $settings['sites'] as &$site ) {
			if ( 'https://wprus-peer.test' === $site['url'] ) {
				foreach ( array( 'update', 'delete', 'password', 'role', 'meta' ) as $action ) {
					$site['outgoing_actions'][ $action ] = $enabled;
				}
				$site['outgoing_roles'] = $enabled ? array( 'editor' ) : array();
				$site['outgoing_meta']  = $enabled ? array( 'test_note' ) : array();
			}
		}
		unset( $site );
		update_option( 'wprus', $settings );
		wp_send_json_success();
	}
);

add_action(
	'edit_user_profile',
	static function ( $user ) {
		if ( 0 === strpos( $user->user_login, 'wprus_test_' ) ) {
			printf( '<h2>Test information</h2><input id="wprus-test-note" name="wprus_test_note" value="%s">', esc_attr( get_user_meta( $user->ID, 'test_note', true ) ) );
		}
	}
);
add_action(
	'edit_user_profile_update',
	static function ( $user_id ) {
		$user = get_userdata( $user_id );
		if ( $user && 0 === strpos( $user->user_login, 'wprus_test_' ) && current_user_can( 'edit_user', $user_id ) && isset( $_POST['wprus_test_note'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress verifies the profile form before this action.
			update_user_meta( $user_id, 'test_note', sanitize_text_field( wp_unslash( $_POST['wprus_test_note'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress verifies the profile form before this action.
		}
	}
);

add_filter(
	'pre_http_request',
	static function ( $response, $arguments, $url ) {
		if ( false !== $response || 'wprus-peer.test' !== wp_parse_url( $url, PHP_URL_HOST ) ) {
			return $response;
		}
		$peer = get_option( 'wprus_tests_peer' );
		if ( ! $peer instanceof Wprus_Test_Peer ) {
			$peer = new Wprus_Test_Peer();
		}
		$result = $peer->receive( $arguments, $url );
		update_option( 'wprus_tests_peer', $peer, false );
		$mode = get_option( 'wprus_tests_peer_mode', 'normal' );
		if ( ! is_wp_error( $result ) && '/wprus/token/' !== wp_parse_url( $url, PHP_URL_PATH ) ) {
			if ( 'timeout' === $mode ) {
				return new WP_Error( 'http_request_failed', 'The simulated site timed out.' );
			}
			if ( 'malformed' === $mode ) {
				$result['body'] = '{invalid';
			}
			if ( 'disabled' === $mode ) {
				$result['body'] = wp_json_encode(
					array(
						'success' => false,
						'data'    => 'The simulated action is disabled.',
					)
				);
			}
		}
		return $result;
	},
	5,
	3
);

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'wprus-tests/v1',
			'/user/(?P<username>wprus_test_[a-z0-9_]+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => static function ( WP_REST_Request $request ) {
					$token = $request->get_header( 'X-WPRUS-Test-Token' );
					$hash = get_option( 'anyape_wp_test_tools_e2e_auth_token_hash', '' );
					return is_string( $hash ) && '' !== $hash && is_string( $token ) && '' !== $token && hash_equals( $hash, hash( 'sha256', $token ) );
				},
				'callback'            => static function ( WP_REST_Request $request ) {
					$user = get_user_by( 'login', $request['username'] );
					if ( ! $user ) {
						return new WP_Error( 'wprus_test_missing_user', 'Test user not found.', array( 'status' => 404 ) );
					}
					return array(
						'ID'           => $user->ID,
						'user_login'   => $user->user_login,
						'user_email'   => $user->user_email,
						'display_name' => $user->display_name,
						'roles'        => $user->roles,
						'test_note'    => get_user_meta( $user->ID, 'test_note', true ),
					);
				},
			)
		);
		register_rest_route(
			'wprus-tests/v1',
			'/peer-request',
			array(
				'methods'             => 'POST',
				'permission_callback' => static function ( WP_REST_Request $request ) {
					$token = $request->get_header( 'X-WPRUS-Test-Token' );
					$hash  = get_option( 'anyape_wp_test_tools_e2e_auth_token_hash', '' );
					return is_string( $hash ) && '' !== $hash && is_string( $token ) && '' !== $token && hash_equals( $hash, hash( 'sha256', $token ) );
				},
				'callback'            => static function ( WP_REST_Request $request ) {
					$url  = $request->get_param( 'url' );
					$path = is_string( $url ) ? wp_parse_url( $url, PHP_URL_PATH ) : false;
					if ( ! is_string( $url ) || 'wprus-peer.test' !== wp_parse_url( $url, PHP_URL_HOST ) || ! in_array( $path, array( '/wprus/login/', '/wprus/logout/' ), true ) ) {
						return new WP_Error( 'wprus_test_invalid_peer_request', 'Unexpected simulated request.', array( 'status' => 400 ) );
					}
					$peer = get_option( 'wprus_tests_peer' );
					if ( ! $peer instanceof Wprus_Test_Peer ) {
						return new WP_Error( 'wprus_test_missing_peer', 'Simulated site not found.', array( 'status' => 500 ) );
					}
					$response = $peer->receive( array( 'method' => 'GET' ), $url );
					update_option( 'wprus_tests_peer', $peer, false );
					if ( is_wp_error( $response ) ) {
						return array(
							'status' => 403,
							'body'   => $response->get_error_message(),
						);
					}
					$last     = end( $peer->requests );
					$callback = $last['data']['callback_url'] ?? '';
					if ( is_string( $callback ) && '' !== $callback && wp_parse_url( $callback, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ) {
						return array(
							'status'  => 303,
							'headers' => array( 'location' => $callback ),
							'body'    => '',
						);
					}
					return array(
						'status' => 200,
						'body'   => '<!doctype html><title>Simulated connected site</title>',
					);
				},
			)
		);
		register_rest_route(
			'wprus-tests/v1',
			'/remote-address',
			array(
				'methods'             => 'GET',
				'permission_callback' => static function ( WP_REST_Request $request ) {
					$token = $request->get_header( 'X-WPRUS-Test-Token' );
					$hash  = get_option( 'anyape_wp_test_tools_e2e_auth_token_hash', '' );
					return is_string( $hash ) && '' !== $hash && is_string( $token ) && '' !== $token && hash_equals( $hash, hash( 'sha256', $token ) );
				},
				'callback'            => static function () {
					$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
					return array( 'address' => $address );
				},
			)
		);
		register_rest_route(
			'wprus-tests/v1',
			'/peer',
			array(
				'methods'             => 'GET',
				'permission_callback' => static function ( WP_REST_Request $request ) {
					$token = $request->get_header( 'X-WPRUS-Test-Token' );
					$hash  = get_option( 'anyape_wp_test_tools_e2e_auth_token_hash', '' );
					return is_string( $hash ) && '' !== $hash && is_string( $token ) && '' !== $token && hash_equals( $hash, hash( 'sha256', $token ) );
				},
				'callback'            => static function () {
					$peer = get_option( 'wprus_tests_peer' );
					return $peer instanceof Wprus_Test_Peer ? array(
						'users'    => $peer->users,
						'requests' => $peer->requests,
					) : array(
						'users'    => array(),
						'requests' => array(),
					);
				},
			)
		);
	}
);
