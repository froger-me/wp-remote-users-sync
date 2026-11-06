<?php
/**
 * Messages sent to a simulated connected site.
 *
 * @package Wprus
 */

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

use AnyapeWPTestTools\HttpMock;

/**
 * Check plugin features.
 *
 * @group wprus
 * @group wprus-actions
 */
class OutgoingActionsTest extends Wprus_Feature_Test_Case {
	/**
	 * Set up the simulated peer for a specific action.
	 *
	 * @param string $action Action to receive.
	 * @return Wprus_Test_Peer Simulated connected site.
	 */
	private function peer_for( $action ) {
		require_once dirname( __DIR__, 2 ) . '/support/class-wprus-test-peer.php';
		$peer  = new Wprus_Test_Peer();
		$reply = static function ( $arguments, $url ) use ( $peer ) {
			return $peer->receive( $arguments, $url );
		};
		HttpMock::queue( 'https://wprus-peer.test/wprus/token/', $reply );
		HttpMock::queue( 'https://wprus-peer.test/wprus/' . $action . '/', $reply );
		return $peer;
	}

	/** A local user creation sends an encrypted message to an independent peer. */
	public function test_create_action_sends_a_profile_to_the_simulated_site() {
		$peer = $this->peer_for( 'create' );
		$GLOBALS['wprus_test_settings']['sites'][0]['outgoing_actions']['create'] = true;
		$this->reload_settings();
		$api = $this->action( 'create' );
		add_action( 'user_register', array( $api, 'notify_remote' ), 10, 1 );
		try {
			$id = wp_insert_user(
				array(
					'user_login' => 'peer_alice',
					'user_pass'  => 'password',
					'user_email' => 'alice@example.test',
				)
			);
		} finally {
			remove_action( 'user_register', array( $api, 'notify_remote' ), 10 );
		}
		$this->assertNotWPError( $id );
		$this->assertArrayHasKey( 'peer_alice', $peer->users );
		$this->assertSame( 'alice@example.test', $peer->users['peer_alice']['user_email'] );
		$this->assertCount( 2, $peer->requests );
		$this->assertSame( '/wprus/token/', $peer->requests[0]['path'] );
		$this->assertSame( '/wprus/create/', $peer->requests[1]['path'] );
	}

	/** A profile change sends the updated fields to the simulated site. */
	public function test_profile_update_sends_the_changed_profile() {
		$id                        = self::factory()->user->create(
			array(
				'user_login' => 'peer_alice',
				'user_email' => 'before@example.test',
			)
		);
		$peer                      = $this->peer_for( 'update' );
		$peer->users['peer_alice'] = array( 'user_email' => 'before@example.test' );
		$GLOBALS['wprus_test_settings']['sites'][0]['outgoing_actions']['update'] = true;
		$this->reload_settings();
		$api = $this->action( 'update' );
		add_action( 'profile_update', array( $api, 'track_updates' ), 10, 1 );
		try {
			$result = wp_update_user(
				array(
					'ID'         => $id,
					'user_email' => 'changed@example.test',
				)
			);
			$api->notify_remote();
		} finally {
			remove_action( 'profile_update', array( $api, 'track_updates' ), 10 );
		}
		$this->assertNotWPError( $result );
		$this->assertSame( 'changed@example.test', $peer->users['peer_alice']['user_email'] );
		$this->assertSame( '/wprus/update/', $peer->requests[1]['path'] );
	}

	/** An allowed role change is sent as part of the user's update. */
	public function test_role_change_sends_only_selected_roles() {
		$id                        = self::factory()->user->create(
			array(
				'user_login' => 'peer_alice',
				'role'       => 'subscriber',
			)
		);
		$peer                      = $this->peer_for( 'update' );
		$peer->users['peer_alice'] = array();
		$GLOBALS['wprus_test_settings']['sites'][0]['outgoing_actions']['update'] = true;
		$GLOBALS['wprus_test_settings']['sites'][0]['outgoing_actions']['role']   = true;
		$GLOBALS['wprus_test_settings']['sites'][0]['outgoing_roles']             = array( 'editor' );
		$this->reload_settings();
		$api = $this->action( 'update' );
		add_action( 'profile_update', array( $api, 'track_updates' ), 10, 1 );
		try {
			$result = wp_update_user(
				array(
					'ID'   => $id,
					'role' => 'editor',
				)
			);
			$api->notify_remote();
		} finally {
			remove_action( 'profile_update', array( $api, 'track_updates' ), 10 );
		}
		$this->assertNotWPError( $result );
		$this->assertSame( 'editor', $peer->users['peer_alice']['roles']['set'] );
		$this->assertFalse( $peer->users['peer_alice']['roles']['add'] );
	}

	/** A local login queues only the enabled connected sites. */
	public function test_local_login_queues_the_enabled_peer() {
		$id = self::factory()->user->create( array( 'user_login' => 'peer_alice' ) );
		$GLOBALS['wprus_test_settings']['sites'][0]['outgoing_actions']['login'] = true;
		$this->reload_settings();
		$api = $this->action( 'login' );
		add_filter( 'send_auth_cookies', '__return_false' );
		add_action( 'set_logged_in_cookie', array( $api, 'notify_remote' ), 10, 6 );
		try {
			wp_set_auth_cookie( $id );
			$api->save_async_actions();
		} finally {
			remove_action( 'set_logged_in_cookie', array( $api, 'notify_remote' ), 10 );
			remove_filter( 'send_auth_cookies', '__return_false' );
		}
		$queued = get_user_meta( $id, 'wprus_login_pending_async_actions', true );
		$this->assertIsArray( $queued );
		$this->assertCount( 1, $queued );
		$this->assertSame( 'peer_alice', $queued[0]['username'] );
		$this->assertSame( 'https://wprus-peer.test', $queued[0]['url'] );
	}

	/** Clearing a local login cookie queues the enabled peer's logout. */
	public function test_local_logout_queues_the_enabled_peer() {
		$id = self::factory()->user->create( array( 'user_login' => 'peer_alice' ) );
		$GLOBALS['wprus_test_settings']['sites'][0]['outgoing_actions']['logout'] = true;
		$this->reload_settings();
		$api = $this->action( 'logout' );
		$api->set_pending_user( $id );
		wp_set_current_user( $id );
		add_filter( 'send_auth_cookies', '__return_false' );
		add_action( 'clear_auth_cookie', array( $api, 'notify_remote' ), 10, 0 );
		try {
			wp_clear_auth_cookie();
			$api->save_async_actions();
		} finally {
			remove_action( 'clear_auth_cookie', array( $api, 'notify_remote' ), 10 );
			remove_filter( 'send_auth_cookies', '__return_false' );
			wp_set_current_user( 0 );
		}
		$queued = get_user_meta( $id, 'wprus_logout_pending_async_actions', true );
		$this->assertIsArray( $queued );
		$this->assertCount( 1, $queued );
		$this->assertSame( 'peer_alice', $queued[0]['username'] );
		$this->assertSame( 'https://wprus-peer.test', $queued[0]['url'] );
	}

	/** Deleting a local user sends the username to the simulated site. */
	public function test_deleting_a_user_sends_the_delete_action() {
		$id                        = self::factory()->user->create( array( 'user_login' => 'peer_alice' ) );
		$peer                      = $this->peer_for( 'delete' );
		$peer->users['peer_alice'] = array( 'user_email' => 'alice@example.test' );
		$GLOBALS['wprus_test_settings']['sites'][0]['outgoing_actions']['delete'] = true;
		$this->reload_settings();
		$api = $this->action( 'delete' );
		add_action( 'delete_user', array( $api, 'notify_remote' ), 10, 2 );
		try {
			$this->assertTrue( wp_delete_user( $id ) );
		} finally {
			remove_action( 'delete_user', array( $api, 'notify_remote' ), 10 );
		}
		$this->assertArrayNotHasKey( 'peer_alice', $peer->users );
		$this->assertSame( '/wprus/delete/', $peer->requests[1]['path'] );
	}

	/** A WordPress password reset sends the new password to the peer. */
	public function test_password_reset_sends_the_new_password() {
		$id                        = self::factory()->user->create( array( 'user_login' => 'peer_alice' ) );
		$user                      = get_userdata( $id );
		$peer                      = $this->peer_for( 'password' );
		$peer->users['peer_alice'] = array();
		$GLOBALS['wprus_test_settings']['sites'][0]['outgoing_actions']['password'] = true;
		$this->reload_settings();
		$api = $this->action( 'password' );
		add_action( 'password_reset', array( $api, 'schedule_shutdown' ), 10, 2 );
		try {
			reset_password( $user, 'new-password' );
			$api->notify_remote();
		} finally {
			remove_action( 'password_reset', array( $api, 'schedule_shutdown' ), 10 );
			remove_action( 'shutdown', array( $api, 'notify_remote' ), PHP_INT_MAX - 100 );
		}
		$this->assertSame( 'new-password', $peer->users['peer_alice']['user_pass'] );
		$this->assertSame( '/wprus/password/', $peer->requests[1]['path'] );
	}

	/** A selected WordPress metadata addition reaches the peer. */
	public function test_selected_metadata_addition_is_sent() {
		$id                        = self::factory()->user->create( array( 'user_login' => 'peer_alice' ) );
		$peer                      = $this->peer_for( 'meta' );
		$peer->users['peer_alice'] = array();
		$GLOBALS['wprus_test_settings']['sites'][0]['outgoing_actions']['meta'] = true;
		$GLOBALS['wprus_test_settings']['sites'][0]['outgoing_meta']            = array( 'test_note' );
		$this->reload_settings();
		$api = $this->action( 'meta' );
		add_filter( 'add_user_metadata', array( $api, 'track_user_meta_add' ), 10, 5 );
		try {
			add_user_meta( $id, 'test_note', 'hello' );
			$api->notify_remote();
		} finally {
			remove_filter( 'add_user_metadata', array( $api, 'track_user_meta_add' ), 10 );
		}
		$this->assertSame( array( 'hello' ), $peer->users['peer_alice']['meta']['test_note'] );
		$this->assertSame( '/wprus/meta/', $peer->requests[1]['path'] );
	}
}
