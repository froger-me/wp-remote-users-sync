<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Wprus_Api_Delete extends Wprus_Api_Abstract {

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public function init_notification_hooks() {
		add_action( 'delete_user', array( $this, 'notify_remote' ), PHP_INT_MAX, 2 );
	}

	public function handle_notification() {
		$data = $this->get_data_post();

		if ( ! $this->validate( $data ) ) {
			Wprus_Logger::log(
				__( 'Delete action failed - received invalid data.', 'wprus' ),
				'alert',
				'db_log'
			);

			return;
		}

		$data = $this->sanitize( $data );
		$site = $this->get_active_site_for_action( $this->endpoint, $data['base_url'] );

		if ( $site ) {
			$reassign      = null;
			$user          = get_user_by( 'login', $data['username'] );
			$user_reassign = ( $data['reassign_username'] ) ? get_user_by( 'login', $data['reassign_username'] ) : false;

			if ( $user_reassign ) {
				$reassign = $user_reassign->ID;
			}

			if ( $user ) {
				wp_delete_user( $user->ID, $reassign );
			}

			if ( ! $user ) {
				Wprus_Logger::log(
					sprintf(
						// translators: %1$s is the username ; %2$s is the caller
						__( 'Delete action failed - user "%1$s" from %2$s was not found locally.', 'wprus' ),
						$data['username'],
						$site['_url']
					),
					'alert',
					'db_log'
				);
			} else {

				if ( ! $user_reassign && $data['reassign_username'] ) {
					Wprus_Logger::log(
						sprintf(
							// translators: %1$s is the username, %2$s is the caller, %3$s is the reassign username
							__( 'Delete action - successfully deleted user "%1$s" from %2$s but failed to reassign content to %3$s.', 'wprus' ),
							$data['username'],
							$site['url'],
							$data['reassign_username']
						),
						'warning',
						'db_log'
					);
				} elseif ( $user_reassign ) {
					Wprus_Logger::log(
						sprintf(
							// translators: %1$s is the username, %2$s is the caller, %3$s is the reassign username
							__( 'Delete action - successfully deleted user "%1$s" from %2$s and reassigned content to %3$s.', 'wprus' ),
							$data['username'],
							$site['url'],
							$data['reassign_username']
						),
						'success',
						'db_log'
					);
				} else {
					Wprus_Logger::log(
						sprintf(
							// translators: %1$s is the username, %2$s is the caller
							__( 'Delete action - successfully deleted user "%1$s" from %2$s.', 'wprus' ),
							$data['username'],
							$site['_url']
						),
						'success',
						'db_log'
					);
				}
			}
		} else {
			Wprus_Logger::log(
				sprintf(
					// translators: %s is the url of the caller
					__( 'Delete action failed - incoming delete action not enabled for %s', 'wprus' ),
					$data['base_url']
				),
				'alert',
				'db_log'
			);
		}
	}

	public function notify_remote( $user_id, $reassign ) {
		$sites = $this->settings->get_sites( $this->endpoint, 'outgoing' );

		if ( ! empty( $sites ) ) {
			$user          = get_user_by( 'ID', $user_id );
			$user_reassign = get_user_by( 'ID', $reassign );

			Wprus_Logger::log(
				sprintf(
					// translators: %s is the username
					__( 'Delete action - firing action for username "%s"', 'wprus' ),
					$user->user_login
				),
				'info',
				'db_log'
			);

			foreach ( $sites as $index => $site ) {
				$this->fire_action(
					$site['url'],
					array(
						'username'          => $user->user_login,
						'reassign_username' => ( $user_reassign ) ? $user_reassign->user_login : false,
					)
				);
			}
		}
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected function sanitize( $data ) {
		$data['reassign_username'] = isset( $data['reassign_username'] ) ? $data['reassign_username'] : false;

		return $data;
	}

}
