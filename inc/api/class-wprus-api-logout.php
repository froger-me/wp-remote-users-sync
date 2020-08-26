<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Wprus_Api_Logout extends Wprus_Api_Abstract {

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public function init_notification_hooks() {
		add_action( 'clear_auth_cookie', array( $this, 'notify_remote' ), PHP_INT_MAX, 0 );
	}

	public function has_remote_async_actions() {

		return true;
	}

	public function handle_notification() {
		$result = false;

		if ( ! is_user_logged_in() ) {
			Wprus_Logger::log(
				__( 'Logout action failed - the user is already logged out.', 'wprus' ),
				'warning',
				'db_log'
			);

			return $result;
		}

		$data = $this->get_data_get();

		if ( ! $this->validate( $data ) ) {
			Wprus_Logger::log(
				__( 'Logout action failed - received invalid data.', 'wprus' ),
				'alert',
				'db_log'
			);

			return $result;
		}

		$data = $this->sanitize( $data );
		$site = $this->get_active_site_for_action( $this->endpoint, $data['base_url'] );

		if ( $site ) {
			$user = get_user_by( 'login', $data['username'] );

			if ( $user && get_current_user_id() === $user->ID ) {
				$result = true;

				wp_destroy_current_session();
				wprus_clear_auth_cookie();
				wp_set_current_user( 0 );
				do_action( 'wp_logout' );
				Wprus_Logger::log(
					sprintf(
						// translators: %1$s is the username, %2$s is the caller
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
						// translators: %1$s is the username, %2$s is the caller
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
						// translators: %1$s is the username, %2$s is the caller
						__( 'Logout action aborted - user "%1$s" from %2$s was not logged in.', 'wprus' ),
						$data['username'],
						$site['url']
					),
					'warning',
					'db_log'
				);
			}
		} else {
			Wprus_Logger::log(
				sprintf(
					// translators: %s is the url of the caller
					__( 'Logout action failed - incoming logout action not enabled for %s', 'wprus' ),
					$data['base_url']
				),
				'alert',
				'db_log'
			);
		}

		return $result;
	}

	public function notify_remote() {

		if ( ! is_user_logged_in() ) {

			return;
		}

		$user  = wp_get_current_user();
		$sites = $this->settings->get_sites( $this->endpoint, 'outgoing' );

		if ( ! empty( $sites ) ) {

			Wprus_Logger::log(
				sprintf(
					// translators: %s is the username
					__( 'Logout action - enqueueing asynchronous actions for username "%s"', 'wprus' ),
					$user->user_login
				),
				'info',
				'db_log'
			);

			foreach ( $sites as $index => $site ) {
				$this->add_remote_async_action(
					$site['url'],
					array(
						'username' => $user->user_login,
					)
				);
			}
		}
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected function validate( $data ) {
		$valid = parent::validate( $data ) && username_exists( $data['username'] );

		return $valid;
	}

}
