<?php
/**
 * User deletion received from connected sites.
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
class DeleteActionTest extends Wprus_Feature_Test_Case {
	/** Deletion reassigns content to the requested local username. */
	public function test_deletes_named_user_and_reassigns_content() {
		$id    = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		$other = self::factory()->user->create( array( 'user_login' => 'bob' ) );
		$post  = self::factory()->post->create( array( 'post_author' => $id ) );
		$this->action( 'delete' )->receive( $this->payload( 'alice' ) + array( 'reassign_username' => 'bob' ) );
		if ( is_multisite() ) {
			$this->assertFalse( is_user_member_of_blog( $id, get_current_blog_id() ) );
			$this->assertInstanceOf( WP_User::class, get_userdata( $id ) );
		} else {
			$this->assertFalse( get_userdata( $id ) );
		}
		$this->assertSame( $other, (int) get_post( $post )->post_author );
	}

	/** A missing reassignment account is reported after deletion. */
	public function test_missing_reassignment_reports_deleted_account() {
		$id  = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		$api = $this->action( 'delete' );
		$this->assertFalse( $api->receive( $this->payload( 'alice' ) + array( 'reassign_username' => 'missing' ) ) );
		$this->assert_response_error( $api, 'reassignment_user_not_found', 422 );
		list( , $error ) = $api->response_error();
		$this->assertTrue( $error['details']['account_deleted'] );
		$this->assertFalse( $error['details']['content_reassigned'] );
		$this->assertFalse( get_userdata( $id ) );
	}

	/** Disabled incoming deletion leaves the account present. */
	public function test_disabled_delete_does_not_remove_user() {
		$id = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		$GLOBALS['wprus_test_settings']['sites'][0]['incoming_actions']['delete'] = false;
		$this->reload_settings();
		$api = $this->action( 'delete' );
		$api->receive( $this->payload( 'alice' ) );
		$this->assert_response_error( $api, 'delete_not_allowed', 403 );
		$this->assertInstanceOf( WP_User::class, get_userdata( $id ) );
	}
}
