<?php
/**
 * Logout actions received from connected sites.
 *
 * @package Wprus
 */

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

/**
 * Check logout behavior.
 *
 * @group wprus
 * @group wprus-actions
 */
class LogoutActionTest extends Wprus_Feature_Test_Case {
	/** Forced logout ends the named user's sessions without ending another user's. */
	public function test_forced_logout_ends_only_the_named_users_sessions() {
		$alice = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		$bob   = self::factory()->user->create( array( 'user_login' => 'bob' ) );
		WP_Session_Tokens::get_instance( $alice )->create( time() + HOUR_IN_SECONDS );
		WP_Session_Tokens::get_instance( $alice )->create( time() + HOUR_IN_SECONDS );
		WP_Session_Tokens::get_instance( $bob )->create( time() + HOUR_IN_SECONDS );
		$api      = $this->action( 'logout' );
		$property = new ReflectionProperty( Wprus_Api_Abstract::class, 'browser_support_settings' );
		$property->setAccessible( true );
		$original = $property->getValue();
		try {
			$property->setValue(
				null,
				array(
					'force_login_logout_strict'         => true,
					'force_disable_login_logout_strict' => false,
					'silent_login_logout_strict'        => false,
				)
			);
			wp_set_current_user( $alice );
			$this->assertTrue( $api->receive( $this->payload( 'alice' ), 'get' ) );
			$this->assertEmpty( WP_Session_Tokens::get_instance( $alice )->get_all() );
			$this->assertNotEmpty( WP_Session_Tokens::get_instance( $bob )->get_all() );
		} finally {
			$property->setValue( null, $original );
			wp_set_current_user( 0 );
		}
	}

	/** A request for another user must not log out the current user. */
	public function test_logout_does_not_change_another_users_session() {
		$alice = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		$bob   = self::factory()->user->create( array( 'user_login' => 'bob' ) );
		WP_Session_Tokens::get_instance( $bob )->create( time() + HOUR_IN_SECONDS );
		wp_set_current_user( $bob );
		$this->assertFalse( $this->action( 'logout' )->receive( $this->payload( 'alice' ), 'get' ) );
		$this->assertSame( $bob, get_current_user_id() );
		$this->assertNotEmpty( WP_Session_Tokens::get_instance( $bob )->get_all() );
		$this->assertInstanceOf( WP_User::class, get_userdata( $alice ) );
	}

	/** A logout request for an unknown user fails. */
	public function test_unknown_user_is_not_logged_out() {
		wp_set_current_user( 0 );
		$this->assertFalse( $this->action( 'logout' )->receive( $this->payload( 'unknown_user' ), 'get' ) );
	}
}
