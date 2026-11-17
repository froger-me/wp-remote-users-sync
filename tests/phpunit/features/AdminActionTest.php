<?php
/**
 * Permanent administrative endpoint and its connection tests.
 *
 * @package Wprus
 */

use AnyapeWPTestTools\HttpMock;

require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';
require_once dirname( __DIR__, 2 ) . '/support/class-wprus-test-peer.php';

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Keep this small test helper beside its tests.

/** Give tests a decoded administrative request. */
class Wprus_Test_Admin extends Wprus_Api_Admin {
	use Wprus_Test_Incoming_Input;
}

/** Expose metadata filtering to its feature test. */
class Wprus_Test_Admin_Meta extends Wprus_Admin_Meta {

	/**
	 * Expose the remote key filter to these tests.
	 *
	 * @param array $keys Remote metadata keys.
	 * @return array Accepted keys.
	 */
	public function filter_keys( $keys ) {
		return $this->filter_outgoing_meta_keys( $keys );
	}
}

/**
 * Check administrative requests and connection tests.
 *
 * @group wprus
 */
class AdminActionTest extends Wprus_Feature_Test_Case {
	/** The endpoint is initialized independently of the user actions. */
	public function test_endpoint_is_permanent() {
		$this->assertArrayHasKey( 'admin', $GLOBALS['wprus_test_ready'][1] );
		$this->assertInstanceOf( Wprus_Api_Admin::class, $GLOBALS['wprus_test_ready'][1]['admin'] );
		$this->assertArrayHasKey( 'admin', apply_filters( 'wprus_wp_endpoints', array( 'token' => 'token' ) ) );
		$admin = new Wprus_Test_Admin( 'admin', new Wprus_Settings( array( 'identity' ) ) );
		$this->assertSame( 'wprus_unknown_admin_action', $admin->receive( $this->request( 'custom' ) )->get_error_code() );
		$this->assertNotWPError( $admin->receive( $this->request( 'outgoing_meta' ) ) );
	}

	/** The request reads only the saved outgoing keys for the requesting site. */
	public function test_outgoing_meta_uses_requesting_site_settings() {
		$GLOBALS['wprus_test_settings']['sites'][0]['outgoing_meta']            = array( 'billing_address_1', 'test_note' );
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_meta']            = array( 'different_key' );
		$GLOBALS['wprus_test_settings']['sites'][0]['outgoing_actions']['meta'] = false;
		$GLOBALS['wprus_test_settings']['sites'][]                              = array_merge(
			$GLOBALS['wprus_test_settings']['sites'][0],
			array(
				'url'           => 'https://another.test',
				'outgoing_meta' => array( 'other_key' ),
			)
		);
		$this->reload_settings();
		$request = $this->request( 'outgoing_meta' );
		$this->assertSame( array( 'billing_address_1', 'test_note' ), $this->admin()->receive( $request ) );
		$request['base_url']         = 'https://another.test';
		$request['data']['base_url'] = 'https://another.test';
		$this->assertSame( array( 'other_key' ), $this->admin()->receive( $request ) );
		$request['data'] = array();
		$this->assertSame( 'wprus_invalid_outgoing_meta_request', $this->admin()->receive( $request )->get_error_code() );
		$request['base_url']         = 'https://wprus-peer.test/subsite';
		$request['data']['base_url'] = 'https://wprus-peer.test/subsite';
		$this->assertSame( 'wprus_unknown_outgoing_meta_site', $this->admin()->receive( $request )->get_error_code() );
		$GLOBALS['wprus_test_settings']['sites'][0]['outgoing_meta'] = array();
		$this->reload_settings();
		$this->assertSame( array(), $this->admin()->receive( array_merge( $this->request( 'outgoing_meta' ), array( 'data' => array( 'base_url' => 'https://wprus-peer.test' ) ) ) ) );
	}

	/** Safe keys survive the same exclusions used for incoming metadata. */
	public function test_outgoing_meta_filters_remote_keys() {
		global $wpdb;
		$keys = array(
			'billing_address_1',
			'billing_address_1',
			'test_note',
			'',
			12,
			' billing_city',
			'billing city',
			'BILLING_CITY',
			'foo\\bar',
			$wpdb->prefix . 'capabilities',
			$wpdb->prefix . 'user_level',
			'session_tokens',
			'_application_passwords',
		);
		$meta = new Wprus_Test_Admin_Meta( $this->admin(), $this->settings );

		$this->assertSame( array( 'billing_address_1', 'test_note' ), $meta->filter_keys( $keys ) );
	}

	/** Returned values become JSON responses. */
	public function test_response_handler_sends_results() {
		$doing_ajax  = static function () {
			return true;
		};
		$die_handler = static function () {
			return static function () {
				throw new RuntimeException( 'Response sent.' );
			};
		};
		add_filter( 'wp_doing_ajax', $doing_ajax );
		add_filter( 'wp_die_ajax_handler', $die_handler );
		try {
			$cases = array(
				array( 'ok', true, 'ok' ),
				array(
					new WP_Error( 'disabled', 'Disabled', array( 'status' => 200 ) ),
					false,
					array(
						'code'    => 'disabled',
						'message' => 'Disabled',
					),
				),
				array(
					new WP_Error( 'invalid', 'Invalid', array( 'status' => 422 ) ),
					false,
					array(
						'code'    => 'invalid',
						'message' => 'Invalid',
					),
				),
				array( null, false, array( 'code' => 'wprus_admin_no_result' ) ),
			);
			foreach ( $cases as $case ) {
				ob_start();
				try {
					$this->admin()->send_response( $case[0] );
				} catch ( RuntimeException $error ) {
					$this->assertSame( 'Response sent.', $error->getMessage() );
				}
				$body = json_decode( ob_get_clean(), true );
				$this->assertSame( $case[1], $body['success'] );
				$this->assertSame( $case[2], $body['data'] );
			}
		} finally {
			remove_filter( 'wp_die_ajax_handler', $die_handler );
			remove_filter( 'wp_doing_ajax', $doing_ajax );
		}
	}

	/**
	 * Missing or malformed fields are rejected before action dispatch.
	 *
	 * @dataProvider invalid_requests
	 * @param string $field Field to change.
	 * @param mixed  $value Replacement value.
	 */
	public function test_invalid_request( $field, $value ) {
		$request = $this->request();
		if ( null === $value ) {
			unset( $request[ $field ] );
		} else {
			$request[ $field ] = $value;
		}
		$result = $this->admin()->receive( $request );
		$this->assertWPError( $result );
		$this->assertSame( 'wprus_invalid_admin_request', $result->get_error_code() );
	}

	/** Invalid request fields and values. */
	public static function invalid_requests() {
		return array(
			'missing action'      => array( 'action', null ),
			'missing data'        => array( 'data', null ),
			'number action'       => array( 'action', 12 ),
			'invalid action name' => array( 'action', '../test' ),
			'string data'         => array( 'data', 'hello' ),
			'unknown site'        => array( 'base_url', 'https://unknown.test' ),
			'conflicting site'    => array( 'data', array( 'base_url' => 'https://unknown.test' ) ),
		);
	}

	/** Custom actions receive only their own data and return a value. */
	public function test_custom_action_receives_only_its_data() {
		$received = null;
		$filter   = static function ( $handlers ) use ( &$received ) {
			$handlers['status'] = static function ( $data ) use ( &$received ) {
				$received = $data;
				return array( 'status' => 'ok' );
			};
			return $handlers;
		};
		add_filter( 'wprus_admin_handlers', $filter );
		try {
			$request                       = $this->request( 'status' );
			$request['identity_email']     = 'admin@example.test';
			$request['extra_common_field'] = 'unchanged';
			$result                        = $this->admin()->receive( $request );
			$this->assertSame( array( 'status' => 'ok' ), $result );
			$this->assertSame( $request['data'], $received );
		} finally {
			remove_filter( 'wprus_admin_handlers', $filter );
		}
	}

	/** The result hook receives returned values before JSON is sent. */
	public function test_after_action_hook_receives_result() {
		$admin          = $this->admin();
		$received       = null;
		$handler_result = array( 'status' => 'ok' );
		$filter         = static function ( $handlers ) use ( &$handler_result ) {
			$handlers['status'] = static function () use ( &$handler_result ) {
				return $handler_result;
			};
			return $handlers;
		};
		$listener       = static function ( $endpoint, $data, $result ) use ( &$received ) {
			$received = array( $endpoint, $data, $result );
		};
		add_filter( 'wprus_admin_handlers', $filter );
		add_action( 'wprus_after_handle_action_notification', $listener, 9, 3 );
		try {
			$request = $this->request( 'status' );
			$admin->receive_checked( $request );
			$this->assertSame( 'admin', $received[0] );
			$this->assertSame( $request, $received[1] );
			$this->assertSame( array( 'status' => 'ok' ), $received[2] );
			$handler_result = new WP_Error( 'example', 'Example failure', array( 'status' => 422 ) );
			$admin->receive_checked( $request );
			$this->assertSame( 'example', $received[2]->get_error_code() );
		} finally {
			remove_action( 'wprus_after_handle_action_notification', $listener, 9 );
			remove_filter( 'wprus_admin_handlers', $filter );
		}
	}

	/** A single request reports every action-setting difference. */
	public function test_full_connection_test_reports_all_differences() {
		$actions                    = array( 'login', 'logout', 'create', 'update', 'delete', 'password', 'role', 'meta' );
		$request                    = $this->request( 'test' );
		$request['data']['actions'] = array(
			'incoming' => array_fill_keys( $actions, false ),
			'outgoing' => array_fill_keys( $actions, true ),
		);
		$this->assertSame( array( 'failures' => array() ), $this->admin()->receive( $request ) );

		$request['data']['actions']['incoming']['login']  = true;
		$request['data']['actions']['outgoing']['create'] = false;
		$result = $this->admin()->receive( $request );
		$this->assertSame( 'wprus_configuration_mismatch', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
		$this->assertSame(
			array(
				array(
					'action'        => 'login',
					'direction'     => 'incoming',
					'local_enabled' => true,
				),
				array(
					'action'        => 'create',
					'direction'     => 'outgoing',
					'local_enabled' => false,
				),
			),
			$result->get_error_data()['details']['failures']
		);
	}

	/** An outgoing call keeps common fields and uses the shared encrypted transport. */
	public function test_outgoing_admin_request_uses_shared_transport() {
		self::factory()->user->create( array( 'user_login' => 'administrator' ) );
		$peer  = new Wprus_Test_Peer();
		$reply = static function ( $arguments, $url ) use ( $peer ) {
			return $peer->receive( $arguments, $url );
		};
		HttpMock::queue( 'https://wprus-peer.test/wprus/token/', $reply );
		HttpMock::queue( 'https://wprus-peer.test/wprus/admin/', $reply );
		$filter = static function ( $data ) {
			$data['identity_email'] = 'admin@example.test';
			return $data;
		};
		add_filter( 'wprus_action_data', $filter );
		try {
			$request_data                     = $this->request( 'test' );
			$request_data['data']['base_url'] = get_option( 'home' );
			$request_data['data']['actions']  = array(
				'incoming' => array( 'create' => true ),
				'outgoing' => array( 'create' => true ),
			);
			$this->admin()->fire_action( 'https://wprus-peer.test', $request_data, true, 5 );
			$request = end( $peer->requests );
			$this->assertSame( '/wprus/admin/', $request['path'] );
			$this->assertSame( 'administrator', $request['data']['username'] );
			$this->assertSame( get_option( 'home' ), $request['data']['base_url'] );
			$this->assertSame( 'admin@example.test', $request['data']['identity_email'] );
			$this->assertSame( 'test', $request['data']['action'] );
			$this->assertSame( $request_data['data']['actions'], $request['data']['data']['actions'] );
		} finally {
			remove_filter( 'wprus_action_data', $filter );
		}
	}

	/**
	 * Build a decoded administrative request.
	 *
	 * @param string $action Administrative action name.
	 * @return array Request fields.
	 */
	private function request( $action = 'test' ) {
		return array(
			'username' => 'administrator',
			'base_url' => 'https://wprus-peer.test',
			'action'   => $action,
			'data'     => array(
				'base_url' => 'https://wprus-peer.test',
				'actions'  => array(
					'incoming' => array(),
					'outgoing' => array(),
				),
			),
		);
	}

	/** Build the handler with the current test settings. */
	private function admin() {
		return new Wprus_Test_Admin( 'admin', $this->settings );
	}
}
