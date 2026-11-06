<?php
/**
 * Receiving action permissions for connected sites.
 *
 * @package Wprus
 */

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- This is a field in a simulated request.

/**
 * Check each action's incoming permission and peer selection.
 *
 * @group wprus
 * @group wprus-actions
 */
class ActionPermissionsTest extends Wprus_Feature_Test_Case {
	/**
	 * Actions do not alter a user when disabled or received from another peer.
	 *
	 * @dataProvider blocked_requests
	 * @param string $action Action name.
	 * @param string $reason Why the action is blocked.
	 * @return void
	 */
	public function test_blocked_actions_leave_the_user_unchanged( $action, $reason ) {
		$id = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'user_email' => 'before@example.test',
				'user_pass'  => 'old-password',
				'role'       => 'subscriber',
			)
		);
		if ( 'disabled' === $reason ) {
			$GLOBALS['wprus_test_settings']['sites'][0]['incoming_actions'][ $action ] = false;
			$this->reload_settings();
		}
		$request = $this->payload( 'alice' ) + array(
			'user_email'        => 'changed@example.test',
			'user_pass'         => 'new-password',
			'remember'          => 0,
			'reassign_username' => false,
			'update'            => array(
				'custom_key' => array(
					array(
						'meta_value' => 'changed',
						'prev_value' => '',
					),
				),
			),
		);
		if ( 'unknown peer' === $reason ) {
			$request['base_url'] = 'https://unrelated.test';
		}
		wp_set_current_user( 0 );
		$method = in_array( $action, array( 'login', 'logout' ), true ) ? 'get' : 'post';
		$this->action( $action )->receive( $request, $method );
		$user = get_userdata( $id );
		$this->assertInstanceOf( WP_User::class, $user );
		$this->assertSame( 'before@example.test', $user->user_email );
		$this->assertSame( array( 'subscriber' ), $user->roles );
		$this->assertTrue( wp_check_password( 'old-password', $user->user_pass, $id ) );
		$this->assertFalse( metadata_exists( 'user', $id, 'custom_key' ) );
		$this->assertSame( 0, get_current_user_id() );
	}

	/**
	 * Supply actions and reasons for rejection.
	 *
	 * @return array Named cases.
	 */
	public static function blocked_requests() {
		$cases = array();
		foreach ( array( 'create', 'update', 'delete', 'password', 'meta', 'login', 'logout' ) as $action ) {
			foreach ( array( 'disabled', 'unknown peer' ) as $reason ) {
				$cases[ $action . ' ' . $reason ] = array( $action, $reason );
			}
		}
		return $cases;
	}
}
