<?php
/**
 * Required fields in connected-site actions.
 *
 * @package Wprus
 */

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

/**
 * Check that incomplete requests leave local users unchanged.
 *
 * @group wprus
 * @group wprus-actions
 */
class RequestValidationTest extends Wprus_Feature_Test_Case {
	/**
	 * Missing identity fields are rejected without warnings.
	 *
	 * @dataProvider incomplete_requests
	 * @param string $action Action name.
	 * @param string $missing Field to remove.
	 * @return void
	 */
	public function test_incomplete_request_does_not_change_a_user( $action, $missing ) {
		$id      = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'user_email' => 'before@example.test',
			)
		);
		$request = $this->payload( 'alice' ) + array(
			'user_email'        => 'after@example.test',
			'user_pass'         => 'changed-password',
			'remember'          => 0,
			'reassign_username' => false,
		);
		unset( $request[ $missing ] );
		wp_set_current_user( 0 );
		$method = in_array( $action, array( 'login', 'logout' ), true ) ? 'get' : 'post';
		$this->action( $action )->receive( $request, $method );
		$this->assertInstanceOf( WP_User::class, get_userdata( $id ) );
		$this->assertSame( 'before@example.test', get_userdata( $id )->user_email );
		$this->assertSame( 0, get_current_user_id() );
	}

	/**
	 * Supply actions and missing required fields.
	 *
	 * @return array Named requests.
	 */
	public static function incomplete_requests() {
		$cases = array();
		foreach ( array( 'create', 'update', 'delete', 'password', 'meta', 'login', 'logout' ) as $action ) {
			foreach ( array( 'username', 'base_url' ) as $field ) {
				$cases[ $action . ' without ' . $field ] = array( $action, $field );
			}
		}
		return $cases;
	}
}
