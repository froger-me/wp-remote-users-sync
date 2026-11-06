<?php
/**
 * Apply role changes permitted by each receiving site.
 *
 * @package Wprus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/** Handle role changes attached to Create and Update actions. */
class Wprus_Api_Role extends Wprus_Api_Abstract {

	/**
	 * Local user receiving a role change.
	 *
	 * @var WP_User
	 */
	protected $user;
	/**
	 * Settings for the sending site.
	 *
	 * @var array
	 */
	protected $site;
	/**
	 * Role changes sent by a connected site.
	 *
	 * @var array
	 */
	protected $incoming_roles;

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	/**
	 * Apply only role changes accepted by the receiving site.
	 *
	 * @param array   $site           Sending site settings.
	 * @param WP_User $user           Local user to update.
	 * @param array   $incoming_roles Roles requested by the sending site.
	 * @return void No value is returned.
	 */
	public function handle_notification_roles( $site, $user, $incoming_roles ) {

		if ( ! $user || ! $site || empty( $incoming_roles ) ) {

			return;
		}

		if ( $site['incoming_actions']['role'] ) {
			$wp_roles             = wp_roles();
			$this->site           = $site;
			$this->user           = $user;
			$this->incoming_roles = $incoming_roles;

			if ( $this->site['incoming_roles_merge'] ) {
					$this->incoming_roles = $this->merge_roles();
			}

			if ( ! empty( $this->incoming_roles['set'] ) ) {

				if (
					$wp_roles->is_role( $this->incoming_roles['set'] ) &&
					(
						empty( $this->site['incoming_roles'] ) ||
						in_array( $this->incoming_roles['set'], $this->site['incoming_roles'], true )
					)
				) {
					$this->user->set_role( $this->incoming_roles['set'] );
				} else {
					Wprus_Logger::log(
						sprintf(
							// translators: %1$s is the meta_key, %2$s is the username, %3$s is the caller.
							__(
								'Role action aborted - role %1$s for user "%2$s" from %3$s not accepted',
								'wprus'
							),
							$this->incoming_roles['set'],
							$this->user->user_login,
							$site['url']
						),
						'warning',
						'db_log'
					);
				}
			}

			if ( ! empty( $this->incoming_roles['add'] ) ) {

				foreach ( $this->incoming_roles['add'] as $role ) {

					if (
						$wp_roles->is_role( $role ) &&
						(
							empty( $this->site['incoming_roles'] ) ||
							in_array( $role, $this->site['incoming_roles'], true )
						)
					) {
						$this->user->add_role( $role );
					} else {
						Wprus_Logger::log(
							sprintf(
								// translators: %1$s is the meta_key, %2$s is the username, %3$s is the caller.
								__(
									'Role action aborted - role %1$s for user "%2$s" from %3$s not accepted',
									'wprus'
								),
								$role,
								$this->user->user_login,
								$site['url']
							),
							'warning',
							'db_log'
						);
					}
				}
			}
		} else {
			Wprus_Logger::log(
				sprintf(
					// translators: %s is the url of the caller.
					__( 'Role action failed - incoming role action not enabled for %s', 'wprus' ),
					$site['url']
				),
				'alert',
				'db_log'
			);
		}
	}

	/**
	 * Add permitted local roles to outgoing user fields.
	 *
	 * @param array   $data User fields to send.
	 * @param array   $site Receiving site settings.
	 * @param WP_User $user Local user.
	 * @return array User fields with accepted roles.
	 */
	public function handle_notify_remote_data( $data, $site, $user ) {

		if ( $site['outgoing_actions']['role'] ) {
			$data['roles'] = array();

			foreach ( $user->roles  as $role ) {

				if ( in_array( $role, $site['outgoing_roles'], true ) ) {
					$data['roles'][] = $role;
				}
			}

			$data['roles'] = array(
				'set' => ( ! empty( $data['roles'] ) ) ? array_shift( $data['roles'] ) : false,
				'add' => ( ! empty( $data['roles'] ) ) ? $data['roles'] : false,
			);
		}

		return $data;
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	/**
	 * Combine incoming roles with the user's existing roles.
	 *
	 * @return array Role to set and any additional roles to add.
	 */
	protected function merge_roles() {

		if ( $this->incoming_roles['set'] ) {
			$this->incoming_roles['set'] = array( $this->incoming_roles['set'] );
		} else {
			$this->incoming_roles['set'] = array();
		}

		if ( ! is_array( $this->incoming_roles['add'] ) ) {
			$this->incoming_roles['add'] = array();
		}

		$this->incoming_roles = array_merge( $this->incoming_roles['set'], $this->incoming_roles['add'] );
		$existing_roles       = $this->user->roles;
		$roles                = array_unique( array_merge( $existing_roles, $this->incoming_roles ) );
		$has_administrator    = in_array( 'administrator', $roles, true );
		$has_editor           = in_array( 'editor', $roles, true );
		$has_author           = in_array( 'author', $roles, true );
		$has_contributor      = in_array( 'contributor', $roles, true );
		$has_subscriber       = in_array( 'subscriber', $roles, true );

		foreach ( $roles as $index => $role ) {

			if ( $has_administrator && in_array( $role, array( 'editor', 'author', 'contributor', 'subscriber' ), true ) ) {
				unset( $roles[ $index ] );
			} elseif ( $has_editor && in_array( $role, array( 'author', 'contributor', 'subscriber' ), true ) ) {
				unset( $roles[ $index ] );
			} elseif ( $has_author && in_array( $role, array( 'contributor', 'subscriber' ), true ) ) {
				unset( $roles[ $index ] );
			} elseif ( $has_contributor && 'subscriber' === $role ) {
				unset( $roles[ $index ] );
			}
		}

		foreach ( $roles as $index => $role ) {

			if ( in_array( $role, array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ), true ) ) {
				unset( $roles[ $index ] );
				array_unshift( $roles, $role );

				break;
			}
		}

		$this->incoming_roles = array(
			'set' => array_shift( $roles ),
			'add' => $roles,
		);

		return $this->incoming_roles;
	}
}
