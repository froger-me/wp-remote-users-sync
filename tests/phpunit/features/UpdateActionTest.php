<?php
/**
 * Update users from connected-site requests.
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
class UpdateActionTest extends Wprus_Feature_Test_Case {
	/** Update the named user while ignoring an ID, login, role and custom metadata. */
	public function test_updates_named_user_without_accepting_unrelated_fields() {
		$alice   = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'user_email' => 'alice@example.test',
				'role'       => 'subscriber',
			)
		);
		$bob     = self::factory()->user->create(
			array(
				'user_login' => 'bob',
				'user_email' => 'bob@example.test',
			)
		);
		$request = $this->payload( 'alice' ) + array(
			'ID'         => $bob,
			'user_login' => 'bob',
			'user_email' => 'changed@example.test',
			'role'       => 'administrator',
			'meta_input' => array( 'private_note' => 'changed' ),
		);
		$this->assertTrue( $this->action( 'update' )->receive( $request ) );
		$this->assertSame( 'alice', get_userdata( $alice )->user_login );
		$this->assertSame( 'changed@example.test', get_userdata( $alice )->user_email );
		$this->assertSame( array( 'subscriber' ), get_userdata( $alice )->roles );
		$this->assertFalse( metadata_exists( 'user', $alice, 'private_note' ) );
		$this->assertSame( 'bob@example.test', get_userdata( $bob )->user_email );
	}

	/** Existing users can update without a login field in the request. */
	public function test_updates_existing_user_without_a_separate_login_field() {
		$id = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		$this->assertTrue( $this->action( 'update' )->receive( $this->payload( 'alice' ) + array( 'display_name' => 'Changed' ) ) );
		$this->assertSame( 'alice', get_userdata( $id )->user_login );
		$this->assertSame( 'Changed', get_userdata( $id )->display_name );
	}

	/** Update creates a missing user only when Create is also enabled. */
	public function test_missing_user_requires_create_permission() {
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_actions']['create'] = false;
		$this->reload_settings();
		$this->assertFalse( (bool) $this->action( 'update' )->receive( $this->payload( 'missing' ) + array( 'user_email' => 'missing@example.test' ) ) );
		$this->assertFalse( get_user_by( 'login', 'missing' ) );
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_actions']['create'] = true;
		$this->reload_settings();
		$this->assertTrue( $this->action( 'update' )->receive( $this->payload( 'missing' ) + array( 'user_email' => 'missing@example.test' ) ) );
		$this->assertNotFalse( get_user_by( 'login', 'missing' ) );
	}

	/** Disabled incoming updates leave the existing profile unchanged. */
	public function test_disabled_update_does_not_change_user() {
		$id = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'user_email' => 'before@example.test',
			)
		);
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_actions']['update'] = false;
		$this->reload_settings();
		$this->assertFalse( $this->action( 'update' )->receive( $this->payload( 'alice' ) + array( 'user_email' => 'after@example.test' ) ) );
		$this->assertSame( 'before@example.test', get_userdata( $id )->user_email );
	}
}
