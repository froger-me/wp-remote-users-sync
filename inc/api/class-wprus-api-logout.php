<?php
/**
 * Send and receive user logout actions.
 *
 * @package Wprus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/** Handle the Logout action for connected sites. */
class Wprus_Api_Logout extends Wprus_Api_Abstract {

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	/**
	 * Register the hooks that send local logouts.
	 *
	 * @return void No value is returned.
	 */
	public function init_notification_hooks() {
		add_action( 'clear_auth_cookie', array( $this, 'notify_remote' ), PHP_INT_MAX - 100, 0 );
		add_action( 'wp_ajax_destroy-sessions', array( $this, 'notify_remote' ), PHP_INT_MIN + 100, 0 );
	}

	/**
	 * State whether logout actions can run after the page loads.
	 *
	 * @return bool Always true for the Logout action.
	 */
	public function has_async_actions() {
		return true;
	}

	/**
	 * Receive a logout action allowed by this site's settings.
	 *
	 * @return bool Whether the logout succeeded.
	 */
	public function handle_notification() {
		global $is_safari;

		$result = false;
		$data   = $this->get_data();

		if ( ! $this->validate( $data ) ) {
			Wprus_Logger::log(
				__( 'Logout action failed - received invalid data.', 'wprus' ),
				'alert',
				'db_log'
			);

			return false;
		}

		$data = $this->sanitize( $data );
		$site = $this->get_active_site_for_action( $this->endpoint, $data['base_url'] );

		if (
			! get_current_user_id() &&
			(
				self::$browser_support_settings['force_login_logout_strict'] ||
				$is_safari ||
				(
					isset( $_SERVER['HTTP_USER_AGENT'] ) &&
					1 === preg_match( '/iPhone|iPad/', sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) )
				)
			) &&
			! self::$browser_support_settings['force_disable_login_logout_strict']
		) {
			$user = get_user_by( 'login', $data['username'] );

			if ( $user ) {
				wp_set_current_user( $user->ID );
			}
		}

		if ( ! get_current_user_id() ) {
			Wprus_Logger::log(
				__( 'Logout action failed - the user is already logged out.', 'wprus' ),
				'warning',
				'db_log'
			);

			return false;
		}

		if ( $site ) {
			$user = get_user_by( 'login', $data['username'] );

			if ( $user && get_current_user_id() === $user->ID ) {
				$result = true;

				if (
					(
						self::$browser_support_settings['force_login_logout_strict'] ||
						$is_safari ||
						(
							isset( $_SERVER['HTTP_USER_AGENT'] ) &&
							1 === preg_match( '/iPhone|iPad/', sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) )
						)
					) &&
					! self::$browser_support_settings['force_disable_login_logout_strict']
				) {
					wp_destroy_all_sessions();
				} else {
					wp_destroy_current_session();
					wprus_clear_auth_cookie();
				}

				wp_set_current_user( 0 );
				do_action( 'wp_logout', $user->ID );
				Wprus_Logger::log(
					sprintf(
						// translators: %1$s is the username, %2$s is the caller.
						__( 'Logout action - successfully logged out user "%1$s" from %2$s.', 'wprus' ),
						$data['username'],
						$site['url']
					),
					'success',
					'db_log'
				);
			} elseif ( ! $user ) {
				Wprus_Logger::log(
					sprintf(
						// translators: %1$s is the username, %2$s is the caller.
						__( 'Logout action aborted - user "%1$s" from %2$s does not exist locally.', 'wprus' ),
						$data['username'],
						$site['url']
					),
					'warning',
					'db_log'
				);
			} else {
				Wprus_Logger::log(
					sprintf(
						// translators: %1$s is the username, %2$s is the caller.
						__( 'Logout action aborted - user "%1$s" from %2$s was not logged in.', 'wprus' ),
						$data['username'],
						$site['url']
					),
					'warning',
					'db_log'
				);
			}
		} elseif ( ! $site ) {
			Wprus_Logger::log(
				sprintf(
					// translators: %s is the url of the caller.
					__( 'Logout action failed - incoming logout action not enabled for %s', 'wprus' ),
					$data['base_url']
				),
				'alert',
				'db_log'
			);
		}

		return $result;
	}

	/**
	 * Send a local logout to connected sites.
	 *
	 * @return void No value is returned.
	 */
	public function notify_remote() {

		if ( ! is_user_logged_in() ) {

			return;
		}

		$user = $this->get_user();

		if ( ! $user ) {
			return;
		}

		$sites = $this->settings->get_sites( $this->endpoint, 'outgoing' );

		if ( empty( $sites ) ) {
			return;
		}

		$message = $this->use_async() ?
			sprintf(
				// translators: %s is the username.
				__( 'Logout action - enqueueing asynchronous actions for username "%s"', 'wprus' ),
				$user->user_login
			) :
			sprintf(
				// translators: %s is the username.
				__( 'Logout action - immediately firing actions for username "%s"', 'wprus' ),
				$user->user_login
			);

		Wprus_Logger::log( $message, 'info', 'db_log' );

		foreach ( $sites as $site ) {

			if ( $this->use_async() ) {
				$this->add_async_action(
					$site['url'],
					array(
						'username' => $user->user_login,
					)
				);

				continue;
			}

			$this->fire_action(
				$site['url'],
				array(
					'username' => $user->user_login,
				)
			);
		}
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	/**
	 * Check required fields for an incoming logout action.
	 *
	 * @param array $data Received request data.
	 * @return bool Whether the logout request is valid.
	 */
	protected function validate( $data ) {
		$valid =
			parent::validate( $data ) &&
			username_exists( $data['username'] );

		return $valid;
	}

	/**
	 * Check whether the logout began by clearing a login cookie.
	 *
	 * @return bool Whether to send the logout after the page loads.
	 */
	protected function use_async() {
		return doing_action( 'clear_auth_cookie' );
	}

	/**
	 * Find the user whose sessions are being destroyed.
	 *
	 * @return WP_User|false User allowed to log out, or false.
	 */
	protected function get_user() {

		if ( doing_action( 'wp_ajax_destroy-sessions' ) ) {
			$user_id = filter_input( INPUT_POST, 'user_id', FILTER_VALIDATE_INT );
			$user    = $user_id ? get_userdata( $user_id ) : false;

			if ( $user ) {

				if ( ! current_user_can( 'edit_user', $user->ID ) ) {
					$user = false;
				} elseif ( ! wp_verify_nonce( (string) filter_input( INPUT_POST, 'nonce', FILTER_UNSAFE_RAW ), 'update-user_' . $user->ID ) ) {
					$user = false;
				}
			}

			if ( ! $user ) {
				Wprus_Logger::log(
					__( 'Logout action failed - user not found, WPRUS connected sites were not notified for logout action.', 'wprus' ),
					'warning',
					'db_log'
				);
			}

			return $user;
		}

		return wp_get_current_user();
	}
}
