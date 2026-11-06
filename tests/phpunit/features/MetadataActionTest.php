<?php
/**
 * Metadata changes received from connected sites.
 *
 * @package Wprus
 */

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- These values are fields in simulated requests.

/**
 * Check plugin features.
 *
 * @group wprus
 * @group wprus-actions
 */
class MetadataActionTest extends Wprus_Feature_Test_Case {
	/**
	 * Protected fields remain unchanged for every metadata operation.
	 *
	 * @dataProvider metadata_operations
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @param string $operation Metadata operation.
	 * @param bool   $delete_all Whether deletion applies across users.
	 * @return void
	 */
	public function test_each_operation_preserves_permission_metadata( $operation, $delete_all ) {
		global $wpdb;
		$id                  = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'role'       => 'subscriber',
			)
		);
		$prefix              = $wpdb->get_blog_prefix( get_current_blog_id() );
		$capabilities        = $prefix . 'capabilities';
		$level               = $prefix . 'user_level';
		$before_capabilities = get_user_meta( $id, $capabilities, false );
		$before_level        = get_user_meta( $id, $level, false );
		$other_id            = self::factory()->user->create(
			array(
				'user_login' => 'bob',
				'role'       => 'subscriber',
			)
		);
		$other_capabilities  = get_user_meta( $other_id, $capabilities, false );
		$other_level         = get_user_meta( $other_id, $level, false );
		add_filter( 'wprus_excluded_meta_keys', '__return_empty_array' );
		add_filter( 'wprus_excluded_meta_keys_like', '__return_empty_array' );
		add_filter(
			'wprus_incoming_meta_exclusions',
			static function () {
				return array(
					'keys'  => array(),
					'regex' => array(),
				);
			}
		);
		$capability_value = array( 'administrator' => true );
		if ( 'add' === $operation ) {
			$capability_change = array(
				'meta_value' => $capability_value,
				'unique'     => false,
			);
			$level_change      = array(
				'meta_value' => 10,
				'unique'     => false,
			);
		} elseif ( 'update' === $operation ) {
			$capability_change = array(
				'meta_value' => $capability_value,
				'prev_value' => '',
			);
			$level_change      = array(
				'meta_value' => 10,
				'prev_value' => '',
			);
		} else {
			$capability_change = array(
				'meta_value' => '',
				'delete_all' => $delete_all,
			);
			$level_change      = array(
				'meta_value' => '',
				'delete_all' => $delete_all,
			);
		}
		$request = $this->payload( 'alice' ) + array(
			$operation => array(
				$capabilities => array( $capability_change ),
				$level        => array( $level_change ),
			),
		);
		$this->action( 'meta' )->receive( $request );
		$this->assertSame( $before_capabilities, get_user_meta( $id, $capabilities, false ) );
		$this->assertSame( $before_level, get_user_meta( $id, $level, false ) );
		$this->assertSame( $other_capabilities, get_user_meta( $other_id, $capabilities, false ) );
		$this->assertSame( $other_level, get_user_meta( $other_id, $level, false ) );
		$this->assertFalse( user_can( $id, 'manage_options' ) );
	}

	/**
	 * Supply supported metadata operations.
	 *
	 * @return array Named operations.
	 */
	public static function metadata_operations() {
		return array(
			'add'        => array( 'add', false ),
			'update'     => array( 'update', false ),
			'delete'     => array( 'delete', false ),
			'delete all' => array( 'delete', true ),
		);
	}

	/**
	 * Application passwords cannot be changed by incoming metadata, even when explicitly allowed.
	 *
	 * @dataProvider application_password_operations
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @param string $operation Metadata operation.
	 * @param bool   $delete_all Whether deletion applies across users.
	 * @param string $key Submitted metadata key.
	 * @return void
	 */
	public function test_application_passwords_cannot_be_changed( $operation, $delete_all, $key ) {
		$id = self::factory()->user->create( array( 'user_login' => 'alice', 'role' => 'administrator' ) );
		$other_id = self::factory()->user->create( array( 'user_login' => 'bob' ) );
		$legitimate = WP_Application_Passwords::create_new_application_password( $id, array( 'name' => 'Existing' ) );
		$injected = WP_Application_Passwords::create_new_application_password( $other_id, array( 'name' => 'Other' ) );
		$this->assertIsArray( $legitimate );
		$this->assertIsArray( $injected );
		$before = get_user_meta( $id, '_application_passwords', false );
		$other_before = get_user_meta( $other_id, '_application_passwords', false );

		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_meta'] = array( $key );
		$this->reload_settings();
		add_filter( 'wprus_excluded_meta_keys', '__return_empty_array' );
		add_filter( 'wprus_excluded_meta_keys_like', '__return_empty_array' );
		add_filter( 'wprus_incoming_meta_exclusions', static function () {
			return array( 'keys' => array(), 'regex' => array() );
		} );

		$change = array(
			'meta_value' => 'delete' === $operation ? '' : array( $injected[1] ),
			'unique'     => false,
			'prev_value' => '',
			'delete_all' => $delete_all,
		);
		$this->action( 'meta' )->receive( $this->payload( 'alice' ) + array( $operation => array( $key => array( $change ) ) ) );
		$this->assertSame( $before, get_user_meta( $id, '_application_passwords', false ) );
		$this->assertSame( $other_before, get_user_meta( $other_id, '_application_passwords', false ) );
		add_filter( 'application_password_is_api_request', '__return_true' );
		add_filter( 'wp_is_application_passwords_available', '__return_true' );
		$this->assertWPError( wp_authenticate_application_password( null, 'alice', $injected[0] ) );
		$this->assertInstanceOf( WP_User::class, wp_authenticate_application_password( null, 'alice', $legitimate[0] ) );
	}

	/**
	 * Supply metadata operations and key capitalization.
	 *
	 * @return array Named cases.
	 */
	public static function application_password_operations() {
		$cases = array();
		foreach ( self::metadata_operations() as $name => $operation ) {
			$cases[ $name ] = array( $operation[0], $operation[1], '_application_passwords' );
			$cases[ $name . ' uppercase' ] = array( $operation[0], $operation[1], '_APPLICATION_PASSWORDS' );
		}
		return $cases;
	}

	/** Add, change, and remove allowed metadata. */
	public function test_add_update_and_delete_allowed_metadata() {
		$id   = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		$api  = $this->action( 'meta' );
		$base = $this->payload( 'alice' );
		$this->assertTrue(
			$api->receive(
				$base + array(
					'add' => array(
						'favourite_colour' => array(
							array(
								'meta_value' => 'blue',
								'unique'     => true,
							),
						),
					),
				)
			)
		);
		$this->assertSame( 'blue', get_user_meta( $id, 'favourite_colour', true ) );
		$this->assertTrue(
			$api->receive(
				$base + array(
					'update' => array(
						'favourite_colour' => array(
							array(
								'meta_value' => 'green',
								'prev_value' => 'blue',
							),
						),
					),
				)
			)
		);
		$this->assertSame( 'green', get_user_meta( $id, 'favourite_colour', true ) );
		$this->assertTrue(
			$api->receive(
				$base + array(
					'delete' => array(
						'favourite_colour' => array(
							array(
								'meta_value' => 'green',
								'delete_all' => false,
							),
						),
					),
				)
			)
		);
		$this->assertFalse( metadata_exists( 'user', $id, 'favourite_colour' ) );
	}

	/** Incoming selections limit which ordinary keys can change. */
	public function test_selected_metadata_limits_incoming_changes() {
		$id = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_meta'] = array( 'allowed_key' );
		$this->reload_settings();
		$changes = array(
			'allowed_key' => array(
				array(
					'meta_value' => 'yes',
					'unique'     => false,
				),
			),
			'other_key'   => array(
				array(
					'meta_value' => 'no',
					'unique'     => false,
				),
			),
		);
		$this->assertTrue( $this->action( 'meta' )->receive( $this->payload( 'alice' ) + array( 'add' => $changes ) ) );
		$this->assertSame( 'yes', get_user_meta( $id, 'allowed_key', true ) );
		$this->assertFalse( metadata_exists( 'user', $id, 'other_key' ) );
	}

	/**
	 * Capability and user-level fields remain protected when filters remove exclusions.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_permission_keys_cannot_be_allowed_by_exclusion_filters() {
		global $wpdb;
		$id = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'role'       => 'subscriber',
			)
		);
		add_filter( 'wprus_excluded_meta_keys', '__return_empty_array' );
		add_filter( 'wprus_excluded_meta_keys_like', '__return_empty_array' );
		add_filter(
			'wprus_incoming_meta_exclusions',
			static function () {
				return array(
					'keys'  => array(),
					'regex' => array(),
				);
			}
		);
		$prefix              = $wpdb->get_blog_prefix( get_current_blog_id() );
		$before_capabilities = get_user_meta( $id, $prefix . 'capabilities', true );
		$before_level        = get_user_meta( $id, $prefix . 'user_level', true );
		$changes             = array(
			$prefix . 'capabilities' => array(
				array(
					'meta_value' => array( 'administrator' => true ),
					'prev_value' => '',
				),
			),
			$prefix . 'user_level'   => array(
				array(
					'meta_value' => 10,
					'prev_value' => '',
				),
			),
			'allowed_key'            => array(
				array(
					'meta_value' => 'updated',
					'prev_value' => '',
				),
			),
		);
		$this->assertTrue( $this->action( 'meta' )->receive( $this->payload( 'alice' ) + array( 'update' => $changes ) ) );
		$this->assertSame( $before_capabilities, get_user_meta( $id, $prefix . 'capabilities', true ) );
		$this->assertSame( $before_level, get_user_meta( $id, $prefix . 'user_level', true ) );
		$this->assertFalse( user_can( $id, 'manage_options' ) );
		$this->assertSame( 'updated', get_user_meta( $id, 'allowed_key', true ) );
	}

	/** Default WordPress and plugin metadata keys are not accepted. */
	public function test_default_excluded_keys_remain_unchanged() {
		$id      = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		$keys    = array( 'session_tokens', 'first_name', 'wp_user-settings', 'wp_dashboard_quick_press_last_post_id', 'wprus_secret', 'secret_wprus' );
		$changes = array();
		foreach ( $keys as $key ) {
			$changes[ $key ] = array(
				array(
					'meta_value' => 'unexpected',
					'prev_value' => '',
				),
			);
		}
		$this->action( 'meta' )->receive( $this->payload( 'alice' ) + array( 'update' => $changes ) );
		foreach ( $keys as $key ) {
			$this->assertNotSame( 'unexpected', get_user_meta( $id, $key, true ), $key );
		}
	}
}
