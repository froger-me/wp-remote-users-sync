<?php
/**
 * Validate WooCommerce customer registrations against connected sites.
 *
 * @package Wprus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keep WooCommerce registration errors in WooCommerce's normal flow. */
class Wprus_Integration_WooCommerce {
	/**
	 * Identity checker shared with WordPress registration.
	 *
	 * @var Wprus_Api_Identity
	 */
	private $identity;

	/**
	 * Register validation after WooCommerce has chosen the final username.
	 *
	 * @param Wprus_Api_Identity $identity The core account identity checker.
	 */
	public function __construct( $identity ) {
		$this->identity = $identity;
		add_filter( 'woocommerce_registration_errors', array( $this, 'validate_registration' ), 10, 3 );
	}

	/**
	 * Validate My Account and checkout registration, including generated usernames.
	 *
	 * @param WP_Error $errors Existing registration errors.
	 * @param string   $username Username chosen by WooCommerce.
	 * @param string   $email Email address being registered.
	 * @return WP_Error Registration errors.
	 */
	public function validate_registration( $errors, $username, $email ) {
		return $this->identity->validate_registration( $errors, $username, $email );
	}
}
