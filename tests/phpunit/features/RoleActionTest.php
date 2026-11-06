<?php
/**
 * Role changes carried by Create and Update.
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
class RoleActionTest extends Wprus_Feature_Test_Case {
	/** Merge keeps an existing custom role and selects the strongest built-in role. */
	public function test_merge_preserves_custom_roles_and_the_strongest_builtin_role() {
		add_role( 'wprus_test_custom', 'Test custom role', array( 'read' => true ) );
		$id   = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'role'       => 'subscriber',
			)
		);
		$user = get_userdata( $id );
		$user->add_role( 'wprus_test_custom' );
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_roles_merge'] = true;
		$this->reload_settings();
		$request = $this->payload( 'alice' ) + array(
			'roles' => array(
				'set' => 'editor',
				'add' => array(),
			),
		);
		try {
			$this->assertTrue( $this->action( 'update' )->receive( $request ) );
			$this->assertEqualsCanonicalizing( array( 'editor', 'wprus_test_custom' ), get_userdata( $id )->roles );
		} finally {
			remove_role( 'wprus_test_custom' );
		}
	}

	/** An allowed additional role is added without removing the main role. */
	public function test_update_adds_an_allowed_additional_role() {
		$id = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'role'       => 'subscriber',
			)
		);
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_roles'] = array( 'editor' );
		$this->reload_settings();
		$request = $this->payload( 'alice' ) + array(
			'roles' => array(
				'set' => false,
				'add' => array( 'editor' ),
			),
		);
		$this->assertTrue( $this->action( 'update' )->receive( $request ) );
		$this->assertEqualsCanonicalizing( array( 'subscriber', 'editor' ), get_userdata( $id )->roles );
	}

	/** An accepted role may be set through an update. */
	public function test_update_applies_an_accepted_role() {
		$id = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'role'       => 'subscriber',
			)
		);
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_roles'] = array( 'editor' );
		$this->reload_settings();
		$request = $this->payload( 'alice' ) + array(
			'roles' => array(
				'set' => 'editor',
				'add' => array(),
			),
		);
		$this->assertTrue( $this->action( 'update' )->receive( $request ) );
		$this->assertSame( array( 'editor' ), get_userdata( $id )->roles );
	}

	/** A role outside the accepted list is not assigned. */
	public function test_update_does_not_apply_a_disallowed_role() {
		$id = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'role'       => 'subscriber',
			)
		);
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_roles'] = array( 'subscriber' );
		$this->reload_settings();
		$request = $this->payload( 'alice' ) + array(
			'roles' => array(
				'set' => 'administrator',
				'add' => array(),
			),
		);
		$this->assertTrue( $this->action( 'update' )->receive( $request ) );
		$this->assertSame( array( 'subscriber' ), get_userdata( $id )->roles );
	}

	/** Disabling Role keeps roles unchanged while allowing other updates. */
	public function test_disabled_role_leaves_existing_role_unchanged() {
		$id = self::factory()->user->create(
			array(
				'user_login' => 'alice',
				'role'       => 'subscriber',
			)
		);
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_actions']['role'] = false;
		$this->reload_settings();
		$request = $this->payload( 'alice' ) + array(
			'display_name' => 'Changed',
			'roles'        => array(
				'set' => 'administrator',
				'add' => array(),
			),
		);
		$this->assertTrue( $this->action( 'update' )->receive( $request ) );
		$this->assertSame( array( 'subscriber' ), get_userdata( $id )->roles );
		$this->assertSame( 'Changed', get_userdata( $id )->display_name );
	}
}
