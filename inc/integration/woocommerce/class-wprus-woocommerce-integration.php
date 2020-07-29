<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Wprus_Woocommerce_Integration extends Wprus_Integration {

	public function __construct( $init_hooks = false ) {
		parent::__construct( $init_hooks );

		if ( $init_hooks ) {
			add_action( 'woocommerce_checkout_process', array( $this, 'checkout_process' ), 10, 0 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public function checkout_process() {
		$wc_checkout = WC_Checkout::instance();
		$data        = $wc_checkout->get_posted_data();

		if ( 1 === $data['createaccount'] || $wc_checkout->is_registration_required() ) {
			add_action( 'woocommerce_created_customer', array( $this, 'created_customer' ), 10, 3 );
		}
	}

	public function created_customer( $customer_id, $new_customer_data, $password_generated ) {
		$user = get_user_by( 'ID', $customer_id );

		do_action( 'wp_login', $user->user_login, $user );
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

}
