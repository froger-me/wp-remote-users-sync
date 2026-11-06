<?php
/**
 * Create and manage security codes for connected sites.
 *
 * @package Wprus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/** Store and validate security codes. */
class Wprus_Nonce {
	/**
	 * Whether security codes can be used only once.
	 *
	 * @var bool
	 */
	protected static $true_nonce;
	/**
	 * Number of seconds a security code remains valid.
	 *
	 * @var int
	 */
	protected static $expiry_length;

	const DEFAULT_EXPIRY_LENGTH = HOUR_IN_SECONDS / 2;

	/**
	 * Set security code behavior and optionally register cleanup hooks.
	 *
	 * @param bool $true_nonce Whether a code can be used only once.
	 * @param bool $init_hooks Whether to register WordPress hooks.
	 * @param int  $expiry_length Number of seconds a code remains valid.
	 * @return void No value is returned.
	 */
	public static function init( $true_nonce = true, $init_hooks = false, $expiry_length = self::DEFAULT_EXPIRY_LENGTH ) {
		self::$true_nonce    = $true_nonce;
		self::$expiry_length = $expiry_length;

		if ( $init_hooks ) {
			add_action( 'wp', array( self::class, 'register_nonce_cleanup' ) );
			add_action( 'wprus_nonce_cleanup', array( self::class, 'clear_nonces' ) );
		}
	}

	/**
	 * Schedule removal of expired security codes.
	 *
	 * @return void No value is returned.
	 */
	public static function register_nonce_cleanup() {

		if ( ! wp_next_scheduled( 'wprus_nonce_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wprus_nonce_cleanup' );
		}
	}

	/**
	 * Create a security code and optionally store its expiration.
	 *
	 * @param bool           $include_expiry Whether to include the expiration time.
	 * @param bool           $store Whether to save the code.
	 * @param callable|false $delegate Optional function used to create the code.
	 * @param array          $delegate_args Values passed to the optional function.
	 * @return string|array|false Security code or code and expiration, or false on failure.
	 */
	public static function create_nonce(
		$include_expiry = false,
		$store = true,
		$delegate = false,
		$delegate_args = array()
	) {

		if ( $delegate && ( is_array( $delegate ) ) ) {
			$nonce = call_user_func_array( $delegate, $delegate_args );
		} else {
			$id    = self::generate_id();
			$nonce = md5( wp_salt( 'nonce' ) . $id . microtime( true ) );
		}

		if ( $store ) {
			$result = self::store_nonce( $nonce );
		} else {
			$result = array(
				'nonce'  => $nonce,
				'expiry' => time() + self::$expiry_length,
			);
		}

		if ( $include_expiry ) {
			$return = $result;
		} else {
			$return = ( $result ) ? $result['nonce'] : $result;
		}

		return $return;
	}

	/**
	 * Read the expiration time of a stored security code.
	 *
	 * @param string $nonce Security code.
	 * @return int|string Expiration time, or zero when the code is absent.
	 */
	public static function get_nonce_expiry( $nonce ) {
		global $wpdb;

		$table = Wprus::get_table( 'wprus_nonce' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE nonce = %s;", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$nonce
			)
		);

		if ( ! $row ) {
			$nonce_expires = 0;
		} else {
			$nonce_expires = $row->expiry;
		}

		return $nonce_expires;
	}

	/**
	 * Check a stored security code and consume it when required.
	 *
	 * @param string $value Security code to check or remove.
	 * @return bool Whether the code is valid.
	 */
	public static function validate_nonce( $value ) {

		if ( empty( $value ) ) {

			return false;
		}

		$nonce = self::fetch_nonce( $value );
		$valid = ( $nonce === $value );

		return $valid;
	}

	/**
	 * Save a security code and its expiration time.
	 *
	 * @param string $nonce Security code.
	 * @return array|false Stored code and expiration, or false on failure.
	 */
	public static function store_nonce( $nonce ) {
		global $wpdb;

		$table  = Wprus::get_table( 'wprus_nonce' );
		$data   = array(
			'nonce'  => $nonce,
			'expiry' => time() + self::$expiry_length,
		);
		$result = $wpdb->insert( $table, $data );

		if ( (bool) $result ) {

			return $data;
		}

		return false;
	}


	/**
	 * Read a valid security code and consume it when required.
	 *
	 * @param string $value Security code to check or remove.
	 * @return string|null Valid code, or null when absent or expired.
	 */
	protected static function fetch_nonce( $value ) {
		global $wpdb;
		$table = Wprus::get_table( 'wprus_nonce' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE nonce = %s;", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$value
			)
		);

		if ( ! $row ) {
			$nonce = null;
		} else {
			$nonce         = $row->nonce;
			$nonce_expires = $row->expiry;

			if ( $nonce_expires < time() ) {
				$nonce = null;
			}

			if ( self::$true_nonce || null === $nonce ) {
				self::delete_nonce( $value );
			}
		}

		return $nonce;
	}


	/**
	 * Delete a stored security code.
	 *
	 * @param string $value Security code to check or remove.
	 * @return bool Whether a code was deleted.
	 */
	public static function delete_nonce( $value ) {
		global $wpdb;

		$table  = Wprus::get_table( 'wprus_nonce' );
		$where  = array( 'nonce' => $value );
		$result = $wpdb->delete( $table, $where );

		return (bool) $result;
	}

	/**
	 * Remove security codes that have expired.
	 *
	 * @return bool|null Whether expired codes were deleted, or null during installation.
	 */
	public static function clear_nonces() {

		if ( defined( 'WP_SETUP_CONFIG' ) || defined( 'WP_INSTALLING' ) ) {

			return;
		}

		global $wpdb;

		$table  = Wprus::get_table( 'wprus_nonce' );
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE expiry < %d;", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				time() - self::DEFAULT_EXPIRY_LENGTH
			)
		);

		return (bool) $result;
	}

	/**
	 * Generate a random identifier for a security code.
	 *
	 * @return string Random identifier.
	 */
	protected static function generate_id() {

		require_once ABSPATH . 'wp-includes/class-phpass.php';

		$hasher = new PasswordHash( 8, false );

		return md5( $hasher->get_random_bytes( 100 ) );
	}
}
