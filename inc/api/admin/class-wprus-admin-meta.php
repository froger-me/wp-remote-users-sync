<?php
/**
 * Handle administrative metadata-setting requests.
 *
 * @package Wprus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Retrieve and validate outgoing metadata settings. */
class Wprus_Admin_Meta {

	/**
	 * Administrative endpoint transport.
	 *
	 * @var Wprus_Api_Admin
	 */
	protected $api;

	/**
	 * Plugin settings.
	 *
	 * @var Wprus_Settings
	 */
	protected $settings;

	/**
	 * Store the shared endpoint transport and settings.
	 *
	 * @param Wprus_Api_Admin $api Administrative endpoint transport.
	 * @param Wprus_Settings  $settings Plugin settings.
	 * @param bool            $init_hooks Whether to register WordPress hooks.
	 */
	public function __construct( $api, $settings, $init_hooks = false ) {
		$this->api      = $api;
		$this->settings = $settings;

		if ( $init_hooks ) {
			add_action( 'wp_ajax_wprus_admin_notify_outgoing_meta_remote', array( $this, 'notify_outgoing_meta_remote' ), 10, 0 );
		}
	}

	/**
	 * Register the outgoing metadata handler.
	 *
	 * @param array $handlers Registered administrative handlers.
	 * @return array Registered administrative handlers.
	 */
	public function register_handler( $handlers ) {
		$handlers['outgoing_meta'] = array( $this, 'handle_outgoing_meta_notification' );

		return $handlers;
	}

	/**
	 * Return outgoing metadata keys configured for the requesting site.
	 *
	 * @param array $data Administrative action data.
	 * @return array|WP_Error Configured keys or an error.
	 */
	public function handle_outgoing_meta_notification( $data ) {

		if ( ! is_array( $data ) || empty( $data['base_url'] ) || ! is_string( $data['base_url'] ) ) {
			return new WP_Error( 'wprus_invalid_outgoing_meta_request', __( 'Invalid outgoing metadata request.', 'wprus' ), array( 'status' => 400 ) );
		}

		$site = $this->settings->get_site( $data['base_url'] );

		if ( ! $site || ! isset( $site['url'] ) || untrailingslashit( $site['url'] ) !== untrailingslashit( $data['base_url'] ) ) {
			return new WP_Error( 'wprus_unknown_outgoing_meta_site', __( 'The requesting site is not configured.', 'wprus' ), array( 'status' => 400 ) );
		}

		return isset( $site['outgoing_meta'] ) && is_array( $site['outgoing_meta'] )
			? array_values( $site['outgoing_meta'] )
			: array();
	}

	/**
	 * Retrieve and validate a connected site's outgoing metadata keys.
	 *
	 * @return void This method sends a JSON response and ends the request.
	 */
	public function notify_outgoing_meta_remote() {
		$this->api->authorize_local_ajax_request();

		$url  = filter_input( INPUT_POST, 'site_url', FILTER_VALIDATE_URL );
		$site = is_string( $url ) ? $this->settings->get_site( $url ) : false;

		if ( ! $site || ! isset( $site['url'] ) || untrailingslashit( $site['url'] ) !== untrailingslashit( $url ) ) {
			wp_send_json_error( __( 'The remote site is not configured.', 'wprus' ), 400 );
		}

		$user = wp_get_current_user();

		if ( ! $user->exists() || '' === $user->user_login ) {
			wp_send_json_error( __( 'Unable to identify the current user.', 'wprus' ), 403 );
		}

		$payload  = array(
			'username' => $user->user_login,
			'action'   => 'outgoing_meta',
			'data'     => array( 'base_url' => get_option( 'home' ) ),
		);
		$response = $this->api->fire_action( $site['url'], $payload, true, 5 );
		$decoded  = json_decode( $response['body'], true );

		if (
			200 !== absint( $response['response_code'] ) ||
			JSON_ERROR_NONE !== json_last_error() ||
			! is_array( $decoded ) ||
			true !== ( $decoded['success'] ?? null ) ||
			! isset( $decoded['data'] ) ||
			! is_array( $decoded['data'] )
		) {
			$message = ( is_array( $decoded ) && isset( $decoded['data'] ) && is_string( $decoded['data'] ) )
				? $decoded['data']
				: __( 'Unable to retrieve outgoing metadata from the remote site.', 'wprus' );

			wp_send_json_error( $message );
		}

		$keys = $decoded['data'];

		if ( array_keys( $keys ) !== array_keys( array_values( $keys ) ) ) {
			wp_send_json_error( __( 'The remote site returned invalid metadata.', 'wprus' ) );
		}

		wp_send_json_success( $this->filter_outgoing_meta_keys( $keys ) );
	}

	/**
	 * Keep only remote metadata keys permitted by this site.
	 *
	 * @param array $keys Raw metadata keys returned by the remote site.
	 * @return array Accepted metadata keys.
	 */
	protected function filter_outgoing_meta_keys( $keys ) {
		require_once WPRUS_PLUGIN_PATH . 'inc/api/class-wprus-api-meta.php';

		$meta_handler = new Wprus_Api_Meta( 'meta', $this->settings, false );
		$accepted     = array();

		foreach ( $keys as $meta_key ) {

			if (
				! is_string( $meta_key ) ||
				'' === $meta_key ||
				trim( $meta_key ) !== $meta_key ||
				wp_unslash( $meta_key ) !== $meta_key ||
				sanitize_key( $meta_key ) !== $meta_key
			) {
				continue;
			}

			if ( $meta_handler->is_excluded_meta_key( $meta_key ) ) {
				continue;
			}

			$accepted[] = $meta_key;
		}

		return array_values( array_unique( $accepted ) );
	}
}
