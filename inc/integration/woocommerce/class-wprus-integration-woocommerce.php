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
	 * @param bool $init_hooks Whether to register WordPress hooks.
	 */
	public function __construct( $init_hooks ) {

		if ( $init_hooks ) {
			add_action( 'wprus_integration_run', array( $this, 'wprus_integration_run' ) );
		}
	}

	/**
	 * Run the integration - set the properties needed for other filters
	 *
	 * @param Wprus_Integration $integration Main integration object.
	 * @return void
	 */
	public function wprus_integration_run( $integration ) {

		if (
			isset( $integration->api['identity'] ) &&
			$integration->api['identity'] instanceof Wprus_Api_Identity
		) {
			$this->identity = $integration->api['identity'];

			add_filter( 'woocommerce_registration_errors', array( $this, 'woocommerce_registration_errors' ), 10, 3 );
		}
	}

	/**
	 * Validate My Account and checkout registration, including generated usernames.
	 *
	 * @param WP_Error $errors Existing registration errors.
	 * @param string   $username Username chosen by WooCommerce.
	 * @param string   $email Email address being registered.
	 * @return WP_Error Registration errors.
	 */
	public function woocommerce_registration_errors( $errors, $username, $email ) {
		return $this->identity->validate_registration( $errors, $username, $email );
	}
}
