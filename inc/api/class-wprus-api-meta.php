<?php
/**
 * Send and receive permitted user metadata changes.
 *
 * @package Wprus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/** Handle the Metadata action for connected sites. */
class Wprus_Api_Meta extends Wprus_Api_Abstract {
	/**
	 * Metadata changes waiting to be sent to each connected site.
	 *
	 * @var array
	 */
	protected $meta_values = array();

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	/**
	 * Register the hooks that send local metadata changes.
	 *
	 * @return void No value is returned.
	 */
	public function init_notification_hooks() {
		add_action( 'shutdown', array( $this, 'notify_remote' ), PHP_INT_MAX - 100, 0 );

		add_filter( 'update_user_metadata', array( $this, 'track_user_meta_update' ), PHP_INT_MAX - 100, 5 );
		add_filter( 'add_user_metadata', array( $this, 'track_user_meta_add' ), PHP_INT_MAX - 100, 5 );
		add_filter( 'delete_user_metadata', array( $this, 'track_user_meta_delete' ), PHP_INT_MAX - 100, 5 );
	}

	/**
	 * Collect a local metadata update for connected sites.
	 *
	 * @param mixed  $check      Current filter result.
	 * @param int    $user_id    User ID.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value New value.
	 * @param mixed  $prev_value Previous value.
	 * @return mixed Unchanged filter result.
	 */
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
						'meta_value' => $meta_value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- This is a payload field, not a database query.
						'prev_value' => $prev_value,
					);
				}
			}
		}

		return $check;
	}

	/**
	 * Collect a local metadata deletion for connected sites.
	 *
	 * @param mixed  $check      Current filter result.
	 * @param int    $user_id    User ID.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Value being deleted.
	 * @param bool   $delete_all Whether to delete every matching value.
	 * @return mixed Unchanged filter result.
	 */
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
						'meta_value' => $meta_value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- This is a payload field, not a database query.
						'delete_all' => $delete_all,
					);
				}
			}
		}

		return $check;
	}

	/**
	 * Collect a local metadata addition for connected sites.
	 *
	 * @param mixed  $check      Current filter result.
	 * @param int    $user_id    User ID.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Value being added.
	 * @param bool   $unique     Whether the key must be unique.
	 * @return mixed Unchanged filter result.
	 */
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
						'meta_value' => $meta_value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- This is a payload field, not a database query.
						'unique'     => $unique,
					);
				}
			}
		}

		return $check;
	}


	/**
	 * Receive metadata changes allowed by this site's settings.
	 *
	 * @return bool Whether the metadata action succeeded.
	 */
	public function handle_notification() {
		$result = false;
		$data   = $this->get_data();

		if ( ! $this->validate( $data ) ) {

			return $result;
		}

		$data = $this->sanitize( $data );
		$site = $this->get_active_site_for_action( $this->endpoint, $data['base_url'] );

		if ( $site ) {
			$user = get_user_by( 'login', $data['username'] );

			if ( ! $user ) {
				return false;
			}

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
					if ( $this->is_excluded_meta_key( $meta_key ) ) {
						Wprus_Logger::log(
							sprintf(
								// translators: %1$s is the metadata key, %2$s is the username, %3$s is the sending site.
								__( 'Metadata action blocked protected key %1$s for user "%2$s" from %3$s', 'wprus' ),
								$meta_key,
								$data['username'],
								$site['url']
							),
							'warning',
							'db_log'
						);

						continue;
					}

					if (
						empty( $site['incoming_meta'] ) ||
						in_array( $meta_key, $site['incoming_meta'], true )
					) {

						foreach ( $values as $index => $value ) {
							$success = false;
							$message = '';

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
										// translators: %1$s is the metadata key, %2$s is the username, %3$s is the caller.
										$message = __(
											'Metadata action - added metadata %1$s for user "%2$s" from %3$s',
											'wprus'
										);
										break;
									case 'update':
										// translators: %1$s is the metadata key, %2$s is the username, %3$s is the caller.
										$message = __(
											'Metadata action - updated metadata %1$s for user "%2$s" from %3$s',
											'wprus'
										);
										break;
									case 'delete':
										// translators: %1$s is the metadata key, %2$s is the username, %3$s is the caller.
										$message = __(
											'Metadata action - deleted metadata %1$s for user "%2$s" from %3$s',
											'wprus'
										);
										break;
								}

								$result = true;

								if ( $message ) {
									Wprus_Logger::log(
										sprintf( $message, $meta_key, $data['username'], $site['url'] ),
										'success',
										'db_log'
									);
								}
							} else {

								switch ( $change_type ) {
									case 'add':
										// translators: %1$s is the metadata key, %2$s is the username, %3$s is the caller.
										$message = __(
											'Metadata action - failed to add metadata %1$s for user "%2$s" from %3$s',
											'wprus'
										);
										break;
									case 'update':
										// translators: %1$s is the metadata key, %2$s is the username, %3$s is the caller.
										$message = __(
											'Metadata action - failed to update metadata %1$s for user "%2$s" from %3$s',
											'wprus'
										);
										break;
									case 'delete':
										// translators: %1$s is the metadata key, %2$s is the username, %3$s is the caller.
										$message = __(
											'Metadata action - failed to delete metadata %1$s for user "%2$s" from %3$s',
											'wprus'
										);
										break;
								}

								if ( $message ) {
									Wprus_Logger::log(
										sprintf( $message, $meta_key, $data['username'], $site['url'] ),
										'alert',
										'db_log'
									);
								}
							}
						}
					} else {
						Wprus_Logger::log(
							sprintf(
								// translators: %1$s is the metadata key, %2$s is the username, %3$s is the caller.
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
					// translators: %s is the URL of the caller.
					__( 'Metadata action failed - incoming metadata action not enabled for %s', 'wprus' ),
					$data['base_url']
				),
				'alert',
				'db_log'
			);
		}

		return $result;
	}

	/**
	 * Send collected metadata changes to connected sites.
	 *
	 * @return void No value is returned.
	 */
	public function notify_remote() {

		if ( ! empty( $this->meta_values ) ) {
			Wprus_Logger::log(
				sprintf(
					// translators: %s is the usernames.
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

	/**
	 * Check whether a metadata key is excluded from incoming changes.
	 *
	 * @param string $meta_key Metadata key to check.
	 * @return bool Whether the key is excluded.
	 */
	protected function is_excluded_meta_key( $meta_key ) {
		global $wpdb;

		// Application passwords must never be changed through metadata synchronization.
		if (
			0 === strcasecmp( $meta_key, '_application_passwords' ) ||
			0 === strcasecmp( $meta_key, 'session_tokens' )
		) {
			return true;
		}

		// Check WordPress role keys first so the filter cannot allow them.
		static $protected_keys = null;

		if ( null === $protected_keys ) {
			$site_ids = array( get_current_blog_id() );

			if ( is_multisite() ) {
				$site_ids = array_merge(
					$site_ids,
					get_sites(
						array(
							'fields' => 'ids',
							'number' => 0,
						)
					)
				);
			}

			$protected_keys = array();

			foreach ( array_unique( $site_ids ) as $site_id ) {
				$prefix           = $wpdb->get_blog_prefix( $site_id );
				$protected_keys[] = $prefix . 'capabilities';
				$protected_keys[] = $prefix . 'user_level';
			}
		}

		foreach ( $protected_keys as $key ) {

			if ( 0 === strcasecmp( $meta_key, $key ) ) {
				return true;
			}
		}

		// Other exclusions may be changed by the filter.
		static $excluded = null;

		if ( null === $excluded ) {
			$excluded = $this->settings->get_excluded_meta_request_rules();

			/**
			 * Filters metadata keys excluded from incoming Metadata actions.
			 *
			 * WordPress role, permission, and application-password keys remain excluded regardless of this filter.
			 *
			 * @since 2.2.1
			 * @param array $excluded Lists of exact keys in `keys` and regular expressions in `regex`.
			 */
			$filtered = apply_filters( 'wprus_incoming_meta_exclusions', $excluded );

			if (
				is_array( $filtered ) &&
				isset( $filtered['keys'], $filtered['regex'] ) &&
				is_array( $filtered['keys'] ) &&
				is_array( $filtered['regex'] )
			) {
				$excluded = $filtered;
			}

			foreach ( $excluded['regex'] as $index => $pattern ) {
				if ( ! is_string( $pattern ) || false === @preg_match( $pattern, '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid filter values are logged below.
					unset( $excluded['regex'][ $index ] );
					Wprus_Logger::log( __( 'Ignored an invalid metadata exclusion expression.', 'wprus' ), 'warning', 'db_log' );
				}
			}
		}

		foreach ( $excluded['keys'] as $key ) {

			if ( is_string( $key ) && 0 === strcasecmp( $meta_key, $key ) ) {
				return true;
			}
		}

		foreach ( $excluded['regex'] as $pattern ) {

			if ( is_string( $pattern ) && 1 === preg_match( $pattern, $meta_key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Keep only the metadata operations supported by this action.
	 *
	 * @param array $data Received metadata changes.
	 * @return array Accepted metadata changes.
	 */
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
