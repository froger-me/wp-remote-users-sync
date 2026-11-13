<?php
/**
 * Start integrations registered for other plugins.
 *
 * @package Wprus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/** Provide shared plugin objects to integrations. */
class Wprus_Integration {

	/**
	 * Registered integrations.
	 *
	 * @var array
	 */
	public static $integrations = array(
		'woocommerce' => array(
			'plugin'     => 'woocommerce/woocommerce.php',
			'class_name' => 'Wprus_Integration_WooCommerce',
		),
	);

	/**
	 * Main plugin object.
	 *
	 * @var Wprus
	 */
	public $wprus;
	/**
	 * API action objects.
	 *
	 * @var array
	 */
	public $api;
	/**
	 * Plugin settings object.
	 *
	 * @var Wprus_Settings
	 */
	public $settings;
	/**
	 * Activity logger.
	 *
	 * @var Wprus_Logger
	 */
	public $wprus_logger;

	/**
	 * Optionally register the integration startup hook.
	 *
	 * @param bool $init_hooks Whether to register WordPress hooks.
	 */
	public function __construct( $init_hooks = false ) {

		if ( $init_hooks ) {
			add_action( 'wprus_ready', array( $this, 'run' ), 10, 4 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	/**
	 * Start integrations for installed plugins.
	 *
	 * @return void No value is returned.
	 */
	public static function init() {
		$integrations = apply_filters( 'wprus_registered_integrations', self::$integrations );

		if ( empty( $integrations ) ) {
			return;
		}

		$integration = new self( true );

		foreach ( $integrations as $slug => $integration_details ) {

			if ( is_plugin_active( $integration_details['plugin'] ) ) {
				$class_name = $integration_details['class_name'];

				if ( ! class_exists( $class_name ) ) {
					do_action( 'wprus_require_integration_file', $slug, $integration_details['class_name'] );
				}

				if ( ! class_exists( $class_name ) && is_string( $slug ) && sanitize_key( $slug ) === $slug ) {
					$path = WPRUS_PLUGIN_PATH . 'inc/integration/' . $slug . '/class-wprus-integration-' . $slug . '.php';

					if ( is_file( $path ) ) {
						require_once $path;
					}
				}

				if ( class_exists( $class_name ) ) {
					$plugin_integration = new $class_name( true );

					do_action( 'wprus_did_register_integration', $plugin_integration, $slug );
				}
			}
		}

		do_action( 'wprus_did_register_integrations', $integration );
	}

	/**
	 * Provide plugin objects to a registered integration.
	 *
	 * @param Wprus          $wprus Main plugin object.
	 * @param array          $api User action objects.
	 * @param Wprus_Settings $settings Plugin settings object.
	 * @param Wprus_Logger   $wprus_logger Activity logger.
	 * @return void No value is returned.
	 */
	public function run( $wprus, $api, $settings, $wprus_logger ) {
		$this->wprus        = $wprus;
		$this->api          = $api;
		$this->settings     = $settings;
		$this->wprus_logger = $wprus_logger;

		do_action( 'wprus_integration_run', $this );
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/
}
