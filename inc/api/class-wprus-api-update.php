<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Wprus_Api_Update extends Wprus_Api_Abstract {

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public function init_notification_hooks() {
		add_action( 'profile_update', array( $this, 'notify_remote' ), PHP_INT_MAX, 2 );
		add_action( 'add_user_role', array( $this, 'notify_remote' ), PHP_INT_MAX, 2 );
		add_action( 'remove_user_role', array( $this, 'notify_remote' ), PHP_INT_MAX, 2 );
		add_action( 'set_user_role', array( $this, 'notify_remote' ), PHP_INT_MAX, 2 );

		// There is no 'before_profile_update' action so we do what we need to do in a filter (bad practice but no choice)
		add_filter( 'pre_user_login', array( $this, 'remove_set_user_role_action' ), 10, 1 );
	}

	public function handle_notification() {
		$data   = $this->get_data_post();
		$result = false;

		if ( ! $this->validate( $data ) ) {
			Wprus_Logger::log(
				__( 'Create action failed - received invalid data.', 'wprus' ),
				'alert',
				'db_log'
			);

			return $result;
		}

		$data = $this->sanitize( $data );
		$site = $this->get_active_site_for_action( $this->endpoint, $data['base_url'] );

		if ( $site ) {
			$incoming_roles    = isset( $data['roles'] ) && ! empty( $data['roles'] ) ? $data['roles'] : false;
			$incoming_username = $data['username'];
			$user              = get_user_by( 'login', $data['username'] );

			unset( $data['username'] );
			unset( $data['base_url'] );

			if ( $user ) {
				$data['ID'] = $user->ID;
			}

			if ( $user || ( $site['incoming_actions']['create'] && ! $user ) ) {
				$data          = $this->password_handler->handle_notification_password_data( $data, $site, $user );
				$maybe_user_id = wp_insert_user( $data );
			} else {
				Wprus_Logger::log(
					sprintf(
						// translators: %1$s is the username ; %2$s is the caller
						__( 'Update action failed - "%1$s" from %2$s does not exist locally and the incoming Create action is not enabled.', 'wprus' ),
						$incoming_username,
						$site['url']
					),
					'alert',
					'db_log'
				);

				return;
			}

			if ( isset( $data['user_pass'] ) ) {
				$data['user_pass'] = '** HIDDEN **';
			}

			if ( is_wp_error( $maybe_user_id ) ) {
				Wprus_Logger::log(
					sprintf(
						// translators: %1$s is the error ; %2$s is the username ; %3$s is the caller
						__( 'Update action failed - "%1$s" for user "%2$s" from %3$s.', 'wprus' ),
						$maybe_user_id->get_error_message(),
						$incoming_username,
						$site['url']
					),
					'alert',
					'db_log'
				);
			} elseif ( $user ) {
				$result = true;

				Wprus_Logger::log(
					array(
						'message' => sprintf(
							// translators: %1$s is the username, %2$s is the caller
							__( 'Update action - successfully updated user "%1$s" from %2$s.', 'wprus' ),
							$incoming_username,
							$site['url']
						),
						'data'    => $data,
					),
					'success',
					'db_log'
				);
			} else {
				$result = true;

				Wprus_Logger::log(
					array(
						'message' => sprintf(
							// translators: %1$s is the username ; %2$s is the caller
							__( 'Update action aborted - performed Create action instead: "%1$s" from %2$s was not found locally.', 'wprus' ),
							$incoming_username,
							$site['url']
						),
						'data'    => $data,
					),
					'warning',
					'db_log'
				);
			}

			if ( ! is_wp_error( $maybe_user_id ) && $incoming_roles ) {
				$user = get_user_by( 'ID', $maybe_user_id );

				$this->role_handler->handle_notification_roles( $site, $user, $incoming_roles );
			}
		} else {
			Wprus_Logger::log(
				sprintf(
					// translators: %s is the url of the caller
					__( 'Update action failed - incoming update action not enabled for %s', 'wprus' ),
					$data['base_url']
				),
				'alert',
				'db_log'
			);
		}

		return $result;
	}

	public function notify_remote( $user_id, $old_userdata ) {
		$sites = $this->settings->get_sites( $this->endpoint, 'outgoing' );
		$user  = get_user_by( 'ID', $user_id );

		if ( $user && ! empty( $sites ) ) {
			$data = array(
				'username'             => $user->user_login,
				'user_login'           => $user->user_login,
				'user_nicename'        => $user->user_nicename,
				'user_url'             => $user->user_url,
				'user_email'           => $user->user_email,
				'display_name'         => $user->display_name,
				'nickname'             => $user->nickname,
				'first_name'           => $user->first_name,
				'last_name'            => $user->last_name,
				'description'          => $user->description,
				'rich_editing'         => $user->rich_editing,
				'syntax_highlighting'  => $user->syntax_highlighting,
				'comment_shortcuts'    => get_user_meta( $user_id, 'comment_shortcuts', true ),
				'admin_color'          => get_user_meta( $user_id, 'admin_color', true ),
				'use_ssl'              => get_user_meta( $user_id, 'use_ssl', true ),
				'user_registered'      => $user->user_registered,
				'show_admin_bar_front' => get_user_meta( $user_id, 'show_admin_bar_front', true ),
				'locale'               => $user->locale,
			);

			Wprus_Logger::log(
				sprintf(
					// translators: %s is the username
					__( 'Update action - firing action for username "%s"', 'wprus' ),
					$user->user_login
				),
				'info',
				'db_log'
			);

			foreach ( $sites as $index => $site ) {
				$data = $this->password_handler->handle_notify_remote_data( $data, $site );
				$data = $this->role_handler->handle_notify_remote_data( $data, $site, $user );

				$this->fire_action( $site['url'], $data );
			}
		}
	}

	public function remove_set_user_role_action( $user_login ) {
		remove_action( 'set_user_role', array( $this, 'notify_remote' ), PHP_INT_MAX );

		return $user_login;
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected function sanitize( $data ) {
		$data['user_pass'] = isset( $data['user_pass'] ) ? $data['user_pass'] : false;
		$data['roles']     = isset( $data['roles'] ) ? $data['roles'] : array();

		return $data;
	}

}
