<?php
/**
 * Password changes received from connected sites.
 *
 * @package Wprus
 */

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

/**
 * Check plugin features.
 *
 * @group wprus
 * @group wprus-actions
 */
class PasswordActionTest extends Wprus_Feature_Test_Case {
	/** A permitted password change creates a usable password. */
	public function test_changes_password_for_existing_user() {
		$id = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'user_pass'  => 'old-password',
			)
		);
		$this->assertTrue( $this->action( 'password' )->receive( $this->payload( 'alice' ) + array( 'user_pass' => 'New password !' ) ) );
		$this->assertTrue( wp_check_password( 'New password !', get_userdata( $id )->user_pass, $id ) );
		$this->assertFalse( wp_check_password( 'old-password', get_userdata( $id )->user_pass, $id ) );
	}

	/** A disabled password action keeps the original password. */
	public function test_disabled_password_keeps_existing_password() {
		$id = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'user_pass'  => 'old-password',
			)
		);
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_actions']['password'] = false;
		$this->reload_settings();
		$this->assertFalse( $this->action( 'password' )->receive( $this->payload( 'alice' ) + array( 'user_pass' => 'new-password' ) ) );
		$this->assertTrue( wp_check_password( 'old-password', get_userdata( $id )->user_pass, $id ) );
	}

	/** Profile updates respect the separate incoming password setting. */
	public function test_update_does_not_change_password_when_password_action_is_disabled() {
		$id = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'user_pass'  => 'old-password',
			)
		);
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_actions']['password'] = false;
		$this->reload_settings();
		$this->assertTrue(
			$this->action( 'update' )->receive(
				$this->payload( 'alice' ) + array(
					'user_pass'    => 'new-password',
					'display_name' => 'Changed',
				)
			)
		);
		$this->assertTrue( wp_check_password( 'old-password', get_userdata( $id )->user_pass, $id ) );
		$this->assertSame( 'Changed', get_userdata( $id )->display_name );
	}
}
