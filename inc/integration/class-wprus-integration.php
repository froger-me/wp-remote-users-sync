<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Wprus_Integration {

	public static $integrations = array(
		'woocommerce' => array(
			'plugin'     => 'woocommerce/woocommerce.php',
			'class_name' => 'Wprus_Woocommerce_Integration',
		),
	);

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

		foreach ( $integrations as $slug => $info ) {

			if ( is_plugin_active( $info['plugin'] ) ) {
				$filename   = 'class-' . strtolower( str_replace( '_', '-', $info['class_name'] ) ) . '.php';
				$file_path  = WPRUS_PLUGIN_PATH . 'inc/integration/' . $slug . '/' . $filename;
				$class_name = $info['class_name'];

				if ( ! class_exists( $class_name ) && file_exists( $file_path ) ) {
					require_once $file_path;
				} else {
					do_action( 'wprus_require_integration_file', $slug, $class_name );
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

		$this->init_hooks();
		do_action( 'wprus_integration_run', $this );
	}

	public function init_hooks() {}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

}
