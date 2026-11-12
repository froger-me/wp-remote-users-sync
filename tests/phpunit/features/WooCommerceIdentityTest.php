<?php
/**
 * WooCommerce registration uses the shared remote identity check.
 *
 * @package Wprus
 */

// Load the shared action helpers and WooCommerce integration.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';
require_once dirname( __DIR__, 3 ) . '/inc/integrations/woocommerce/class-wprus-integration-woocommerce.php';

use AnyapeWPTestTools\HttpMock;

/** Exercise WooCommerce's registration error filter. */
class WooCommerceIdentityTest extends Wprus_Feature_Test_Case {
	/** A generated username is checked after WooCommerce has chosen it. */
	public function test_generated_username_and_email_are_validated() {
		wp_set_current_user( 0 );
		$identity = new Wprus_Api_Identity( 'identity', $this->settings );
		HttpMock::queue(
			'https://wprus-peer.test/wprus/token/',
			HttpMock::response(
				wp_json_encode(
					array(
						'nonce'  => 'identity-token',
						'expiry' => time() + 600,
					)
				)
			)
		);
		HttpMock::queue(
			'https://wprus-peer.test/wprus/identity/',
			HttpMock::response(
				wp_json_encode(
					array(
						'success' => true,
						'data'    => array(
							'username_exists' => false,
							'email_exists'    => false,
						),
					)
				)
			)
		);
		$integration = new Wprus_Integration_WooCommerce( $identity );
		$errors      = $integration->validate_registration( new WP_Error(), 'generated.customer', 'customer@example.test' );
		$this->assertFalse( $errors->has_errors() );
		$this->assertSame(
			array(
				'user_login' => 'generated.customer',
				'user_email' => 'customer@example.test',
			),
			$identity->require_validated_creation(
				array(
					'user_login' => 'generated.customer',
					'user_email' => 'customer@example.test',
				),
				false,
				null,
				array()
			)
		);
		remove_filter( 'woocommerce_registration_errors', array( $integration, 'validate_registration' ), 10 );
	}

	/** A remote conflict uses WooCommerce's existing error object. */
	public function test_remote_conflict_is_returned_as_registration_error() {
		wp_set_current_user( 0 );
		$identity = new Wprus_Api_Identity( 'identity', $this->settings );
		HttpMock::queue(
			'https://wprus-peer.test/wprus/token/',
			HttpMock::response(
				wp_json_encode(
					array(
						'nonce'  => 'identity-token',
						'expiry' => time() + 600,
					)
				)
			)
		);
		HttpMock::queue(
			'https://wprus-peer.test/wprus/identity/',
			HttpMock::response(
				wp_json_encode(
					array(
						'success' => true,
						'data'    => array(
							'username_exists' => false,
							'email_exists'    => true,
						),
					)
				)
			)
		);
		$integration = new Wprus_Integration_WooCommerce( $identity );
		$errors      = $integration->validate_registration( new WP_Error(), 'customer', 'used@example.test' );
		$this->assertSame( 'wprus_email_exists', $errors->get_error_code() );
		remove_filter( 'woocommerce_registration_errors', array( $integration, 'validate_registration' ), 10 );
	}
}
