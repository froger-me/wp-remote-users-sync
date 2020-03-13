<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Wprus_Api_Meta extends Wprus_Api_Abstract {
	protected $meta_values = array();

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public function init_notification_hooks() {
		add_action( 'shutdown', array( $this, 'notify_remote' ), PHP_INT_MAX, 0 );

		add_filter( 'update_user_metadata', array( $this, 'track_user_meta_update' ), PHP_INT_MAX, 5 );
		add_filter( 'add_user_metadata', array( $this, 'track_user_meta_add' ), PHP_INT_MAX, 5 );
		add_filter( 'delete_user_metadata', array( $this, 'track_user_meta_delete' ), PHP_INT_MAX, 5 );
	}

	public function track_user_meta_update( $check, $user_id, $meta_key, $meta_value, $prev_value = '' ) {
		$user  = get_user_by( 'ID', $user_id );
		$sites = $this->settings->get_sites( $this->endpoint, 'outgoing' );

		if ( ! empty( $sites ) ) {

			foreach ( $sites as $index => $site ) {

				if ( in_array( $meta_key, $site['outgoing_meta'], true ) ) {

					if ( ! isset( $this->meta_values[ $user->user_login ] ) ) {
						$this->meta_values[ $user->user_login ] = array();
					}

					if ( ! isset( $this->meta_values[ $user->user_login ][ $site['url'] ] ) ) {
						$this->meta_values[ $user->user_login ][ $site['url'] ] = array();
					}

					if ( ! isset( $this->meta_values[ $user->user_login ][ $site['url'] ]['update'] ) ) {
						$this->meta_values[ $user->user_login ][ $site['url'] ]['update'] = array();
					}

					if ( ! isset( $this->meta_values[ $user->user_login ][ $site['url'] ]['update'][ $meta_key ] ) ) {
						$this->meta_values[ $user->user_login ][ $site['url'] ]['update'][ $meta_key ] = array();
					}

					$this->meta_values[ $user->user_login ][ $site['url'] ]['update'][ $meta_key ][] = array(
						'meta_value' => $meta_value,
						'prev_value' => $prev_value,
					);
				}
			}
		}

		return $check;
	}

	public function track_user_meta_delete( $check, $user_id, $meta_key, $meta_value, $delete_all = false ) {
		$user  = get_user_by( 'ID', $user_id );
		$sites = $this->settings->get_sites( $this->endpoint, 'outgoing' );

		if ( ! empty( $sites ) ) {

			foreach ( $sites as $index => $site ) {

				if ( in_array( $meta_key, $site['outgoing_meta'], true ) ) {

					if ( ! isset( $this->meta_values[ $user->user_login ] ) ) {
						$this->meta_values[ $user->user_login ] = array();
					}

					if ( ! isset( $this->meta_values[ $user->user_login ][ $site['url'] ] ) ) {
						$this->meta_values[ $user->user_login ][ $site['url'] ] = array();
					}

					if ( ! isset( $this->meta_values[ $user->user_login ][ $site['url'] ]['delete'] ) ) {
						$this->meta_values[ $user->user_login ][ $site['url'] ]['delete'] = array();
					}

					if ( ! isset( $this->meta_values[ $user->user_login ][ $site['url'] ]['delete'][ $meta_key ] ) ) {
						$this->meta_values[ $user->user_login ][ $site['url'] ]['delete'][ $meta_key ] = array();
					}

					$this->meta_values[ $user->user_login ][ $site['url'] ]['delete'][ $meta_key ][] = array(
						'meta_value' => $meta_value,
						'delete_all' => $delete_all,
					);
				}
			}
		}

		return $check;
	}

	public function track_user_meta_add( $check, $user_id, $meta_key, $meta_value, $unique = false ) {
		$user  = get_user_by( 'ID', $user_id );
		$sites = $this->settings->get_sites( $this->endpoint, 'outgoing' );

		if ( ! empty( $sites ) ) {

			foreach ( $sites as $index => $site ) {

				if ( in_array( $meta_key, $site['outgoing_meta'], true ) ) {

					if ( ! isset( $this->meta_values[ $user->user_login ] ) ) {
						$this->meta_values[ $user->user_login ] = array();
					}

					if ( ! isset( $this->meta_values[ $user->user_login ][ $site['url'] ] ) ) {
						$this->meta_values[ $user->user_login ][ $site['url'] ] = array();
					}

					if ( ! isset( $this->meta_values[ $user->user_login ][ $site['url'] ]['add'] ) ) {
						$this->meta_values[ $user->user_login ][ $site['url'] ]['add'] = array();
					}

					if ( ! isset( $this->meta_values[ $user->user_login ][ $site['url'] ]['add'][ $meta_key ] ) ) {
						$this->meta_values[ $user->user_login ][ $site['url'] ]['add'][ $meta_key ] = array();
					}

					$this->meta_values[ $user->user_login ][ $site['url'] ]['add'][ $meta_key ][] = array(
						'meta_value' => $meta_value,
						'unique'     => $unique,
					);
				}
			}
		}

		return $check;
	}


	public function handle_notification() {
		$result = false;
		$data   = $this->get_data();

		if ( ! $this->validate( $data ) ) {

			return $result;
		}

		$data = $this->sanitize( $data );
		$site = $this->get_active_site_for_action( $this->endpoint, $data['base_url'] );

		if ( $site ) {
			$user         = get_user_by( 'login', $data['username'] );
			$meta_changes = array(
				'add'    => ( isset( $data['add'] ) ) ? $data['add'] : false,
				'update' => ( isset( $data['update'] ) ) ? $data['update'] : false,
				'delete' => ( isset( $data['delete'] ) ) ? $data['delete'] : false,
			);

			foreach ( $meta_changes as $change_type => $metas ) {

				if ( ! $metas ) {

					continue;
				}

				foreach ( $metas as $meta_key => $values ) {

					if (
						empty( $site['incoming_meta'] ) ||
						in_array( $meta_key, $site['incoming_meta'], true )
					) {

						foreach ( $values as $index => $value ) {

							switch ( $change_type ) {
								case 'add':
									$success = add_metadata(
										'user',
										$user->ID,
										$meta_key,
										$value['meta_value'],
										$value['unique']
									);
									break;
								case 'update':
									$success = update_metadata(
										'user',
										$user->ID,
										$meta_key,
										$value['meta_value'],
										$value['prev_value']
									);
									break;
								case 'delete':
									$success = delete_metadata(
										'user',
										$user->ID,
										$meta_key,
										$value['meta_value'],
										$value['delete_all']
									);
									break;
							}

							if ( $success ) {

								switch ( $change_type ) {
									case 'add':
										// translators: %1$s is the meta_key, %2$s is the username, %3$s is the caller
										$message = __(
											'Metadata action - added metadata %1$s for user "%2$s" from %3$s',
											'wprus'
										);
										break;
									case 'update':
										// translators: %1$s is the meta_key, %2$s is the username, %3$s is the caller
										$message = __(
											'Metadata action - updated metadata %1$s for user "%2$s" from %3$s',
											'wprus'
										);
										break;
									case 'delete':
										// translators: %1$s is the meta_key, %2$s is the username, %3$s is the caller
										$message = __(
											'Metadata action - deleted metadata %1$s for user "%2$s" from %3$s',
											'wprus'
										);
										break;
								}

								$result = true;

								Wprus_Logger::log(
									sprintf( $message, $meta_key, $data['username'], $site['url'] ),
									'success',
									'db_log'
								);
							} else {

								switch ( $change_type ) {
									case 'add':
										// translators: %1$s is the meta_key, %2$s is the username, %3$s is the caller
										$message = __(
											'Metadata action - failed to add metadata %1$s for user "%2$s" from %3$s',
											'wprus'
										);
										break;
									case 'update':
										// translators: %1$s is the meta_key, %2$s is the username, %3$s is the caller
										$message = __(
											'Metadata action - failed to update metadata %1$s for user "%2$s" from %3$s',
											'wprus'
										);
										break;
									case 'delete':
										// translators: %1$s is the meta_key, %2$s is the username, %3$s is the caller
										$message = __(
											'Metadata action - failed to delete metadata %1$s for user "%2$s" from %3$s',
											'wprus'
										);
										break;
								}

								Wprus_Logger::log(
									sprintf( $message, $meta_key, $data['username'], $site['url'] ),
									'alert',
									'db_log'
								);
							}
						}
					} else {
						Wprus_Logger::log(
							sprintf(
								// translators: %1$s is the meta_key, %2$s is the username, %3$s is the caller
								__(
									'Metadata action aborted - metadata %1$s for user "%2$s" from %3$s not accepted',
									'wprus'
								),
								$meta_key,
								$data['username'],
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
					// translators: %s is the url of the caller
					__( 'Metadata action failed - incoming metadata action not enabled for %s', 'wprus' ),
					$data['base_url']
				),
				'alert',
				'db_log'
			);
		}

		return $result;
	}

	public function notify_remote() {
		$sites = $this->settings->get_sites( $this->endpoint, 'outgoing' );

		if ( ! empty( $this->meta_values ) ) {
			Wprus_Logger::log(
				sprintf(
					// translators: %s is the usernames
					__( 'Metadata action - firing action for username(s) %s', 'wprus' ),
					'"' . implode( '","', array_keys( $this->meta_values ) ) . '"'
				),
				'info',
				'db_log'
			);

			foreach ( $this->meta_values  as $username => $meta_values ) {

				foreach ( $meta_values as $site_url => $meta_changes ) {
					$data             = $meta_changes;
					$data['username'] = $username;

					$this->fire_action( $site_url, $data );
				}
			}
		}
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected function sanitize( $data ) {

		foreach ( $data as $key => $value ) {

			if (
				'base_url' !== $key &&
				'username' !== $key &&
				'add' !== $key &&
				'update' !== $key &&
				'delete' !== $key
			) {
				unset( $data[ $key ] );
			}
		}

		return $data;
	}

}
