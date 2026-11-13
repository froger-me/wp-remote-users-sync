<?php
/**
 * WooCommerce registration uses the shared remote identity check.
 *
 * @package Wprus
 */

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

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
		$integration = $this->start_woocommerce_integration( $identity );
		$errors      = $integration->woocommerce_registration_errors( new WP_Error(), 'generated.customer', 'customer@example.test' );
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
		remove_filter( 'woocommerce_registration_errors', array( $integration, 'woocommerce_registration_errors' ), 10 );
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
		$integration = $this->start_woocommerce_integration( $identity );
		$errors      = $integration->woocommerce_registration_errors( new WP_Error(), 'customer', 'used@example.test' );
		$this->assertSame( 'wprus_email_exists', $errors->get_error_code() );
		remove_filter( 'woocommerce_registration_errors', array( $integration, 'woocommerce_registration_errors' ), 10 );
	}

	/**
	 * Start WooCommerce through the same registration path used by the plugin.
	 *
	 * @param Wprus_Api_Identity $identity Account identity checker.
	 * @return Wprus_Integration_WooCommerce Started integration.
	 */
	private function start_woocommerce_integration( $identity ) {
		$integration    = null;
		$active_plugins = static function ( $plugins ) {
			$plugins[] = 'woocommerce/woocommerce.php';
			return $plugins;
		};
		$capture        = static function ( $registered, $slug ) use ( &$integration ) {
			if ( 'woocommerce' === $slug ) {
				$integration = $registered;
			}
		};

		add_filter( 'option_active_plugins', $active_plugins );
		add_action( 'wprus_integration', $capture, 10, 2 );
		try {
			Wprus_Integration::init();
		} finally {
			remove_action( 'wprus_integration', $capture, 10 );
			remove_filter( 'option_active_plugins', $active_plugins );
		}

		$this->assertInstanceOf( Wprus_Integration_WooCommerce::class, $integration );
		$this->assertSame( 10, has_action( 'wprus_integration_run', array( $integration, 'wprus_integration_run' ) ) );
		$this->assertSame( 10, has_filter( 'woocommerce_registration_errors', array( $integration, 'woocommerce_registration_errors' ) ) );
		$contexts        = array();
		$capture_context = static function ( $context ) use ( &$contexts ) {
			if ( $context instanceof Wprus_Integration && get_class( $context ) === Wprus_Integration::class ) {
				$contexts[] = $context;
			}
		};
		add_action( 'wprus_integration_run', $capture_context );
		$previous_ready = $GLOBALS['wprus_test_ready'];
		try {
			do_action( 'wprus_ready', null, array( 'identity' => $identity ), $this->settings, null );
		} finally {
			remove_action( 'wprus_integration_run', $capture_context );
			$GLOBALS['wprus_test_ready'] = $previous_ready;
		}
		$this->assertNotEmpty( $contexts );
		foreach ( $contexts as $context ) {
			$this->assertSame( $identity, $context->api['identity'] );
			remove_action( 'wprus_ready', array( $context, 'run' ), 10 );
		}
		remove_action( 'wprus_integration_run', array( $integration, 'wprus_integration_run' ), 10 );

		return $integration;
	}
}
