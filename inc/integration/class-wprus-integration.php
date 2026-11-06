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
	public static $integrations = array();

	/**
	 * Main plugin object.
	 *
	 * @var Wprus
	 */
	protected $wprus;
	/**
	 * User action objects.
	 *
	 * @var array
	 */
	protected $api;
	/**
	 * Plugin settings object.
	 *
	 * @var Wprus_Settings
	 */
	protected $settings;
	/**
	 * Activity logger.
	 *
	 * @var Wprus_Logger
	 */
	protected $wprus_logger;

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
		$integrations = apply_filters( 'wprus_registered_integration', self::$integrations );

		if ( empty( $integrations ) ) {

			return;
		}

		foreach ( $integrations as $slug => $info ) {

			if ( is_plugin_active( $info['plugin'] ) ) {
				$class_name = $info['class_name'];

				if ( ! class_exists( $class_name ) ) {
					do_action( 'wprus_require_integration_file', $slug, $info['class_name'] );
				}

				if ( class_exists( $class_name ) ) {
					$integration = new $class_name( true );

					do_action( 'wprus_integration', $integration, $slug );
				}
			}
		}
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
	 */
}
