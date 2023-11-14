<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Wprus_Integration {

	public static $integrations = array();

	protected $wprus;
	protected $api;
	protected $settings;
	protected $wprus_logger;

	public function __construct( $init_hooks = false ) {

		if ( $init_hooks ) {
			add_action( 'wprus_ready', array( $this, 'run' ), 10, 4 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/
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
