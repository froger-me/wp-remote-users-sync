<?php
/**
 * Login actions received from connected sites.
 *
 * @package Wprus
 */

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

/**
 * Check login behavior.
 *
 * @group wprus
 * @group wprus-actions
 */
class LoginActionTest extends Wprus_Feature_Test_Case {
	/**
	 * Remember-me values determine the length of a successful login.
	 *
	 * @dataProvider remember_values
	 * @param mixed $value Submitted value.
	 * @param bool  $present Whether the field is present.
	 * @param bool  $expected Whether the login should be remembered.
	 * @return void
	 */
	public function test_remember_value_controls_session_length( $value, $present, $expected ) {
		$id      = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		$request = $this->payload( 'alice' );
		if ( $present ) {
			$request['remember'] = $value;
		}
		wp_set_current_user( 0 );
		add_filter( 'send_auth_cookies', '__return_false' );
		$remembered = null;
		$filter = static function ( $expiration, $user_id, $remember ) use ( &$remembered ) {
			$remembered = $remember;
			return $expiration;
		};
		add_filter( 'auth_cookie_expiration', $filter, 10, 3 );
		try {
			$this->assertTrue( $this->action( 'login' )->receive( $request, 'get' ) );
			$this->assertSame( $id, get_current_user_id() );
			$this->assertSame( $expected, $remembered );
		} finally {
			remove_filter( 'send_auth_cookies', '__return_false' );
			remove_filter( 'auth_cookie_expiration', $filter, 10 );
			wp_set_current_user( 0 );
		}
	}

	/**
	 * Supply remember-me values and their expected meaning.
	 *
	 * @return array Named cases.
	 */
	public static function remember_values() {
		return array(
			'missing'     => array( null, false, false ),
			'empty'       => array( '', true, false ),
			'string zero' => array( '0', true, true ),
			'text'        => array( 'yes', true, true ),
			'negative'    => array( -1, true, true ),
			'zero'        => array( 0, true, false ),
			'one'         => array( 1, true, true ),
			'array'       => array( array( 1 ), true, false ),
		);
	}

	/** A permitted login creates a session for the existing user. */
	public function test_login_creates_a_session_for_the_named_user() {
		$id = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		add_filter( 'send_auth_cookies', '__return_false' );
		try {
			$this->assertTrue( $this->action( 'login' )->receive( $this->payload( 'alice' ) + array( 'remember' => 0 ), 'get' ) );
			$this->assertSame( $id, get_current_user_id() );
			$this->assertNotEmpty( WP_Session_Tokens::get_instance( $id )->get_all() );
		} finally {
			remove_filter( 'send_auth_cookies', '__return_false' );
			wp_set_current_user( 0 );
		}
	}

	/** A disabled login does not create a session. */
	public function test_disabled_login_does_not_create_a_session() {
		$id = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_actions']['login'] = false;
		$this->reload_settings();
		wp_set_current_user( 0 );
		$this->assertFalse( $this->action( 'login' )->receive( $this->payload( 'alice' ) + array( 'remember' => 0 ), 'get' ) );
		$this->assertSame( 0, get_current_user_id() );
		$this->assertEmpty( WP_Session_Tokens::get_instance( $id )->get_all() );
	}
}
