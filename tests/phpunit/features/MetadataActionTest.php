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
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_meta'] = array( $capabilities, $level );
		$this->reload_settings();
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
		$id         = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'role'       => 'administrator',
			)
		);
		$other_id   = self::factory()->user->create( array( 'user_login' => 'bob' ) );
		$legitimate = WP_Application_Passwords::create_new_application_password( $id, array( 'name' => 'Existing' ) );
		$injected   = WP_Application_Passwords::create_new_application_password( $other_id, array( 'name' => 'Other' ) );
		$this->assertIsArray( $legitimate );
		$this->assertIsArray( $injected );
		$before       = get_user_meta( $id, '_application_passwords', false );
		$other_before = get_user_meta( $other_id, '_application_passwords', false );

		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_meta'] = array( $key );
		$this->reload_settings();
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
			$cases[ $name ]                     = array( $operation[0], $operation[1], '_application_passwords' );
			$cases[ $name . ' uppercase' ]      = array( $operation[0], $operation[1], '_APPLICATION_PASSWORDS' );
			$cases[ $name . ' trailing space' ] = array( $operation[0], $operation[1], '_application_passwords ' );
			$cases[ $name . ' accented' ]       = array( $operation[0], $operation[1], '_application_passwórds' );
		}
		return $cases;
	}

	/** An empty incoming selection does not accept metadata. */
	public function test_empty_selection_rejects_metadata() {
		$id = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_meta'] = array();
		$this->reload_settings();
		$this->assertFalse(
			$this->action( 'meta' )->receive(
				$this->payload( 'alice' ) + array(
					'update' => array(
						'allowed_key' => array(
							array(
								'meta_value' => 'changed',
								'prev_value' => '',
							),
						),
					),
				)
			)
		);
		$this->assertFalse( metadata_exists( 'user', $id, 'allowed_key' ) );
	}

	/** Each connected site can update only its own selected metadata keys. */
	public function test_incoming_selection_is_specific_to_the_connected_site() {
		$id                                      = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		$first                                   = $GLOBALS['wprus_test_settings']['sites'][0];
		$first['incoming_meta']                  = array( 'first_site_key' );
		$second                                  = $first;
		$second['url']                           = 'https://second-peer.test';
		$second['incoming_meta']                 = array( 'second_site_key' );
		$GLOBALS['wprus_test_settings']['sites'] = array( $first, $second );
		$this->reload_settings();
		$changes = array(
			'first_site_key'  => array(
				array(
					'meta_value' => 'first',
					'prev_value' => '',
				),
			),
			'second_site_key' => array(
				array(
					'meta_value' => 'second',
					'prev_value' => '',
				),
			),
		);
		$this->assertTrue( $this->action( 'meta' )->receive( $this->payload( 'alice' ) + array( 'update' => $changes ) ) );
		$this->assertSame( 'first', get_user_meta( $id, 'first_site_key', true ) );
		$this->assertFalse( metadata_exists( 'user', $id, 'second_site_key' ) );
		$this->assertTrue(
			$this->action( 'meta' )->receive(
				array(
					'username' => 'alice',
					'base_url' => 'https://second-peer.test',
					'update'   => $changes,
				)
			)
		);
		$this->assertSame( 'second', get_user_meta( $id, 'second_site_key', true ) );
	}

	/** A key changed by WordPress sanitization cannot alter a different stored key. */
	public function test_unsanitized_selected_key_cannot_change_stored_key() {
		$id = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		update_user_meta( $id, 'private_value', 'unchanged' );
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_meta'] = array( 'private_válue', 'allowed_key' );
		$this->reload_settings();
		$this->assertTrue(
			$this->action( 'meta' )->receive(
				$this->payload( 'alice' ) + array(
					'update' => array(
						'private_válue' => array(
							array(
								'meta_value' => 'changed',
								'prev_value' => '',
							),
						),
						'allowed_key'   => array(
							array(
								'meta_value' => 'accepted',
								'prev_value' => '',
							),
						),
					),
				)
			)
		);
		$this->assertSame( 'unchanged', get_user_meta( $id, 'private_value', true ) );
		$this->assertSame( 'accepted', get_user_meta( $id, 'allowed_key', true ) );
	}

	/**
	 * Selected keys are rejected when WordPress changes them during sanitization.
	 *
	 * @dataProvider unsanitized_keys
	 * @param string $key Submitted metadata key.
	 * @return void
	 */
	public function test_selected_unsanitized_key_is_rejected( $key ) {
		$id = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_meta'] = array( $key, 'allowed_key' );
		$this->reload_settings();
		$this->assertTrue(
			$this->action( 'meta' )->receive(
				$this->payload( 'alice' ) + array(
					'update' => array(
						$key          => array(
							array(
								'meta_value' => 'rejected',
								'prev_value' => '',
							),
						),
						'allowed_key' => array(
							array(
								'meta_value' => 'accepted',
								'prev_value' => '',
							),
						),
					),
				)
			)
		);
		$this->assertFalse( metadata_exists( 'user', $id, $key ) );
		$this->assertSame( 'accepted', get_user_meta( $id, 'allowed_key', true ) );
	}

	/**
	 * Supply keys that WordPress sanitization would change.
	 *
	 * @return array Named cases.
	 */
	public static function unsanitized_keys() {
		return array(
			'uppercase' => array( 'Custom_Key' ),
			'period'    => array( 'custom.key' ),
			'space'     => array( 'custom key' ),
			'accent'    => array( 'custom_kéy' ),
			'slash'     => array( 'custom_\\key' ),
		);
	}

	/**
	 * Sensitive keys cannot be changed through alternative spellings, even if selected.
	 *
	 * @dataProvider permission_key_variants
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @param string $suffix Submitted permission key ending.
	 * @return void
	 */
	public function test_selected_permission_key_variants_are_rejected( $suffix ) {
		global $wpdb;
		$id  = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'role'       => 'subscriber',
			)
		);
		$key = $wpdb->get_blog_prefix() . $suffix;
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_meta'] = array( $key, 'allowed_key' );
		$this->reload_settings();
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
		$this->assertTrue(
			$this->action( 'meta' )->receive(
				$this->payload( 'alice' ) + array(
					'update' => array(
						$key          => array(
							array(
								'meta_value' => array( 'administrator' => true ),
								'prev_value' => '',
							),
						),
						'allowed_key' => array(
							array(
								'meta_value' => 'accepted',
								'prev_value' => '',
							),
						),
					),
				)
			)
		);
		$this->assertFalse( user_can( $id, 'manage_options' ) );
		$this->assertSame( 'accepted', get_user_meta( $id, 'allowed_key', true ) );
	}

	/**
	 * Supply permission key spellings.
	 *
	 * @return array Named cases.
	 */
	public static function permission_key_variants() {
		return array(
			'exact'  => array( 'capabilities' ),
			'space'  => array( 'capabilities ' ),
			'accent' => array( 'capabilitiés' ),
			'slash'  => array( 'capabilitie\\s' ),
		);
	}

	/**
	 * A filter can add protected keys without removing the built-in protections.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @return void
	 */
	public function test_filter_adds_protected_keys_without_removing_defaults() {
		global $wpdb;
		$id           = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'role'       => 'subscriber',
			)
		);
		$capabilities = $wpdb->get_blog_prefix() . 'capabilities';
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_meta'] = array( 'private_key', 'private_kéy', $capabilities, 'allowed_key' );
		$this->reload_settings();
		$filter_input = null;
		add_filter(
			'wprus_protected_meta_keys',
			static function ( $keys ) use ( &$filter_input ) {
				$filter_input = $keys;
				return array( 'private_key' );
			}
		);
		$this->assertTrue(
			$this->action( 'meta' )->receive(
				$this->payload( 'alice' ) + array(
					'update' => array(
						'private_key' => array(
							array(
								'meta_value' => 'denied',
								'prev_value' => '',
							),
						),
						'private_kéy' => array(
							array(
								'meta_value' => 'denied',
								'prev_value' => '',
							),
						),
						$capabilities => array(
							array(
								'meta_value' => array( 'administrator' => true ),
								'prev_value' => '',
							),
						),
						'allowed_key' => array(
							array(
								'meta_value' => 'accepted',
								'prev_value' => '',
							),
						),
					),
				)
			)
		);
		$this->assertSame( array(), $filter_input );
		$this->assertFalse( metadata_exists( 'user', $id, 'private_key' ) );
		$this->assertFalse( metadata_exists( 'user', $id, 'private_kéy' ) );
		$this->assertFalse( user_can( $id, 'manage_options' ) );
		$this->assertSame( 'accepted', get_user_meta( $id, 'allowed_key', true ) );
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
		$prefix = $wpdb->get_blog_prefix( get_current_blog_id() );
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_meta'] = array( $prefix . 'capabilities', $prefix . 'user_level', 'allowed_key' );
		$this->reload_settings();
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
