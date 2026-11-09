<?php
/**
 * Metadata protection for a WordPress network.
 *
 * @package Wprus
 */

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- These values are fields in a simulated request.

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

/**
 * Check permissions belonging to another WordPress site.
 *
 * @group wprus
 * @group wprus-multisite
 */
class MultisiteTest extends Wprus_Feature_Test_Case {
	/**
	 * The current site's role fields remain protected after switching sites.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @return void
	 */
	public function test_permission_keys_remain_protected_after_switching_sites() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This case requires the WordPress multisite test profile.' );
		}
		global $wpdb;
		$site_id = self::factory()->blog->create();
		$user_id = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		add_user_to_blog( $site_id, $user_id, 'subscriber' );
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
		switch_to_blog( $site_id );
		try {
			$capabilities = $wpdb->get_blog_prefix( $site_id ) . 'capabilities';
			$GLOBALS['wprus_test_settings']['sites'][0]['incoming_meta'] = array( $capabilities );
			$this->reload_settings();
			$before = get_user_meta( $user_id, $capabilities, true );
			$this->action( 'meta' )->receive(
				$this->payload( 'alice' ) + array(
					'delete' => array(
						$capabilities => array(
							array(
								'meta_value' => '',
								'delete_all' => true,
							),
						),
					),
				)
			);
			$this->assertSame( $before, get_user_meta( $user_id, $capabilities, true ) );
			$this->assertSame( array( 'subscriber' ), get_userdata( $user_id )->roles );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Another site's permission fields cannot be changed through metadata.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_metadata_cannot_change_another_sites_permission_fields() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This case requires the WordPress multisite test profile.' );
		}
		global $wpdb;
		$site_id = self::factory()->blog->create();
		$user_id = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'role'       => 'subscriber',
			)
		);
		$prefix  = $wpdb->get_blog_prefix( $site_id );
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_meta'] = array( $prefix . 'capabilities', $prefix . 'user_level' );
		$this->reload_settings();
		update_user_meta( $user_id, $prefix . 'capabilities', array( 'subscriber' => true ) );
		update_user_meta( $user_id, $prefix . 'user_level', 0 );
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
		$request = $this->payload( 'alice' ) + array(
			'update' => array(
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
			),
		);
		$this->action( 'meta' )->receive( $request );
		$this->assertSame( array( 'subscriber' => true ), get_user_meta( $user_id, $prefix . 'capabilities', true ) );
		$this->assertSame( '0', (string) get_user_meta( $user_id, $prefix . 'user_level', true ) );
	}
}
