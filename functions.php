<?php

if ( ! defined( 'ABSPATH' ) || ! defined( 'WPINC' ) ) {
	exit; // Exit if accessed directly
}


if ( ! function_exists( 'wp_hash_password' ) ) {
	/**
	 * Create a hash (encrypt) of a plain text password.
	 *
	 * For integration with other applications, this function can be overwritten to
	 * instead use the other package password checking algorithm.
	 *
	 * @since 2.5.0
	 *
	 * @global PasswordHash $wp_hasher PHPass object
	 *
	 * @param string $password Plain text user password to hash
	 * @return string The hash string of the password
	 */
	function wp_hash_password( $password ) {
		global $wp_hasher;

		if ( empty( $wp_hasher ) ) {
			require_once ABSPATH . WPINC . '/class-phpass.php';
			// By default, use the portable hash from phpass
			$wp_hasher = new PasswordHash( 8, true ); // @codingStandardsIgnoreLine
		}

		do_action( 'wprus_password', $password );

		return $wp_hasher->HashPassword( trim( $password ) );
	}
}

if ( ! function_exists( 'wprus_log' ) ) {

	function wprus_log( $expression, $extend = '', $destination = 'error_log' ) {

		Wprus_Logger::log( $expression, $extend, $destination );
	}
}
