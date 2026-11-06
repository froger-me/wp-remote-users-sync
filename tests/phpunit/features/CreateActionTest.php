<?php
/**
 * Create users from connected-site requests.
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
class CreateActionTest extends Wprus_Feature_Test_Case {
	/** A normal request creates the requested profile using the default role. */
	public function test_creates_user_with_permitted_profile_fields() {
		update_option( 'default_role', 'subscriber' );
		$request = $this->payload( 'wprus_test_alice' ) + array(
			'user_login' => 'another_login',
			'user_email' => 'alice@example.test',
			'first_name' => 'Alice',
			'role'       => 'administrator',
			'meta_input' => array( 'secret' => 'changed' ),
		);
		$this->assertTrue( $this->action( 'create' )->receive( $request ) );
		$user = get_user_by( 'login', 'wprus_test_alice' );
		$this->assertInstanceOf( WP_User::class, $user );
		$this->assertSame( 'alice@example.test', $user->user_email );
		$this->assertSame( 'Alice', $user->first_name );
		$this->assertSame( array( 'subscriber' ), $user->roles );
		$this->assertFalse( metadata_exists( 'user', $user->ID, 'secret' ) );
		$this->assertFalse( get_user_by( 'login', 'another_login' ) );
	}

	/** A supplied ID cannot turn Create into an update of another account. */
	public function test_create_cannot_overwrite_an_existing_user_by_id() {
		$id      = self::factory()->user->create(
			array(
				'user_login' => 'existing_user',
				'user_email' => 'before@example.test',
				'role'       => 'subscriber',
			)
		);
		$request = $this->payload( 'new_user' ) + array(
			'ID'         => $id,
			'user_email' => 'new@example.test',
			'role'       => 'administrator',
		);
		$this->assertTrue( $this->action( 'create' )->receive( $request ) );
		$this->assertSame( 'before@example.test', get_userdata( $id )->user_email );
		$this->assertSame( array( 'subscriber' ), get_userdata( $id )->roles );
		$this->assertNotFalse( get_user_by( 'login', 'new_user' ) );
	}

	/** An existing login is rejected and the original account remains unchanged. */
	public function test_existing_login_is_not_overwritten() {
		$id = self::factory()->user->create(
			array(
				'user_login' => 'existing_user',
				'user_email' => 'before@example.test',
			)
		);
		$this->assertFalse( $this->action( 'create' )->receive( $this->payload( 'existing_user' ) + array( 'user_email' => 'after@example.test' ) ) );
		$this->assertSame( 'before@example.test', get_userdata( $id )->user_email );
	}

	/** Disabled incoming Create does not create an account. */
	public function test_disabled_create_is_ignored() {
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_actions']['create'] = false;
		$this->reload_settings();
		$this->assertFalse( $this->action( 'create' )->receive( $this->payload( 'disabled_user' ) + array( 'user_email' => 'disabled@example.test' ) ) );
		$this->assertFalse( get_user_by( 'login', 'disabled_user' ) );
	}
}
