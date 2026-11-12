<?php
/**
 * Identity checks for local creation and incoming user actions.
 *
 * @package Wprus
 */

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

use AnyapeWPTestTools\HttpMock;

/** Exercise account identity checks with disposable WordPress users. */
class IdentityActionTest extends Wprus_Feature_Test_Case {
	/** A matching existing account can receive a legitimate email change. */
	public function test_email_change_uses_previous_email_for_verification() {
		$id = self::factory()->user->create(
			array(
				'user_login' => 'identity_email_change',
				'user_email' => 'old@example.test',
			)
		);

		$this->action( 'update' )->receive_checked(
			$this->payload( 'identity_email_change' ) + array(
				'identity_email' => 'OLD@example.test',
				'user_email'     => 'new@example.test',
				'display_name'   => 'Updated',
			)
		);

		$this->assertSame( 'new@example.test', get_userdata( $id )->user_email );
		$this->assertSame( 'Updated', get_userdata( $id )->display_name );
		$this->assertTrue( Wprus_Api_Identity::verify_incoming_identity( $this->payload( 'identity_email_change' ) + array( 'identity_email' => 'new@example.test' ), 'password' ) );
	}

	/** A conflicting account cannot be changed through Update or Password. */
	public function test_conflicting_account_cannot_be_changed() {
		$id      = self::factory()->user->create(
			array(
				'user_login' => 'identity_admin',
				'user_email' => 'owner@example.test',
				'user_pass'  => 'original-password',
				'role'       => 'administrator',
			)
		);
		$payload = $this->payload( 'identity_admin' ) + array(
			'identity_email' => 'attacker@example.test',
			'user_email'     => 'attacker2@example.test',
			'user_pass'      => 'attacker-password',
			'display_name'   => 'Changed',
			'roles'          => array( 'set' => 'subscriber' ),
		);

		$this->action( 'update' )->receive_checked( $payload );
		$this->action( 'password' )->receive_checked( $payload );

		$user = get_userdata( $id );
		$this->assertSame( 'owner@example.test', $user->user_email );
		$this->assertNotSame( 'Changed', $user->display_name );
		$this->assertSame( array( 'administrator' ), $user->roles );
		$this->assertTrue( wp_check_password( 'original-password', $user->user_pass, $id ) );
		$this->assertFalse( wp_check_password( 'attacker-password', $user->user_pass, $id ) );
	}

	/** Every user action stops before its handler when an existing identity differs. */
	public function test_all_incoming_handlers_reject_mismatched_existing_identity() {
		$id       = self::factory()->user->create(
			array(
				'user_login' => 'identity_target',
				'user_email' => 'owner@example.test',
			)
		);
		$handled  = 0;
		$callback = static function () use ( &$handled ) {
			++$handled;
		};
		add_action( 'wprus_before_handle_action_notification', $callback );

		try {
			foreach ( array( 'create', 'update', 'password', 'meta', 'login', 'logout', 'delete' ) as $endpoint ) {
				$this->action( $endpoint )->receive_checked( $this->payload( 'identity_target' ) + array( 'identity_email' => 'different@example.test' ) );
			}
		} finally {
			remove_action( 'wprus_before_handle_action_notification', $callback );
		}

		$this->assertSame( 0, $handled );
		$this->assertInstanceOf( WP_User::class, get_userdata( $id ) );
	}

	/** Existing accounts require both strings, while missing users reach normal action rules. */
	public function test_missing_identity_fields_are_rejected_for_existing_users() {
		self::factory()->user->create(
			array(
				'user_login' => 'identity_fields',
				'user_email' => 'owner@example.test',
			)
		);
		$this->assertFalse( Wprus_Api_Identity::verify_incoming_identity( $this->payload( 'identity_fields' ), 'password' ) );
		$this->assertFalse( Wprus_Api_Identity::verify_incoming_identity( $this->payload( 'identity_fields' ) + array( 'identity_email' => '' ), 'password' ) );
		$this->assertFalse( Wprus_Api_Identity::verify_incoming_identity( array( 'identity_email' => 'owner@example.test' ), 'password' ) );
		$this->assertTrue( Wprus_Api_Identity::verify_incoming_identity( $this->payload( 'identity_new' ), 'create' ) );
	}

	/** Unsupported local creation is rejected before WordPress writes the user. */
	public function test_unvalidated_local_creation_throws_before_insertion() {
		wp_set_current_user( 0 );
		$identity = new Wprus_Api_Identity( 'identity', $this->settings );
		add_filter( 'wp_pre_insert_user_data', array( $identity, 'require_validated_creation' ), PHP_INT_MAX, 4 );
		try {
			wp_insert_user(
				array(
					'user_login' => 'identity_unvalidated',
					'user_email' => 'unvalidated@example.test',
					'user_pass'  => 'password',
				)
			);
			$this->fail( 'The creation should have been rejected.' );
		} catch ( Wprus_Identity_Creation_Exception $exception ) {
			$this->assertStringContainsString( 'identity_unvalidated', $exception->getMessage() );
		} finally {
			remove_filter( 'wp_pre_insert_user_data', array( $identity, 'require_validated_creation' ), PHP_INT_MAX );
		}
		$this->assertFalse( get_user_by( 'login', 'identity_unvalidated' ) );
	}

	/** A verified incoming Create can insert a new account through the shared handler. */
	public function test_incoming_create_is_allowed_through_final_creation_check() {
		$identity = new Wprus_Api_Identity( 'identity', $this->settings );
		add_filter( 'wp_pre_insert_user_data', array( $identity, 'require_validated_creation' ), PHP_INT_MAX, 4 );
		wp_set_current_user( 0 );
		try {
			$this->action( 'create' )->receive_checked(
				$this->payload( 'identity_incoming' ) + array(
					'identity_email' => 'incoming@example.test',
					'user_email'     => 'incoming@example.test',
				)
			);
		} finally {
			remove_filter( 'wp_pre_insert_user_data', array( $identity, 'require_validated_creation' ), PHP_INT_MAX );
		}
		$this->assertInstanceOf( WP_User::class, get_user_by( 'login', 'identity_incoming' ) );
	}

	/** A third-party callback receives the result and can reject through its own error flow. */
	public function test_other_registration_callback_receives_remote_conflict() {
		$identity = new Wprus_Api_Identity( 'identity', $this->settings );
		HttpMock::queue(
			'https://wprus-peer.test/wprus/token/',
			HttpMock::response(
				wp_json_encode(
					array(
						'nonce'  => 'identity-token',
						'expiry' => time() + 600,
					)
				)
			)
		);
		HttpMock::queue(
			'https://wprus-peer.test/wprus/identity/',
			HttpMock::response(
				wp_json_encode(
					array(
						'success' => true,
						'data'    => array(
							'username_exists' => true,
							'email_exists'    => false,
						),
					)
				)
			)
		);
		$received_result = null;
		$callback        = static function ( $username, $email, $data, $result ) use ( &$received_result ) {
			$received_result = $result;
			if ( is_wp_error( $result ) ) {
				throw new Wprus_Identity_Creation_Exception( 'Handled by the registration system.' );
			}
		};
		add_action( 'wprus_validate_user_creation', $callback, 10, 4 );
		add_filter( 'wp_pre_insert_user_data', array( $identity, 'require_validated_creation' ), PHP_INT_MAX, 4 );
		wp_set_current_user( 0 );
		try {
			wp_insert_user(
				array(
					'user_login' => 'identity_third_party',
					'user_email' => 'thirdparty@example.test',
					'user_pass'  => 'password',
				)
			);
			$this->fail( 'The registration system should reject the conflict.' );
		} catch ( Wprus_Identity_Creation_Exception $exception ) {
			$this->assertSame( 'Handled by the registration system.', $exception->getMessage() );
		} finally {
			remove_filter( 'wp_pre_insert_user_data', array( $identity, 'require_validated_creation' ), PHP_INT_MAX );
			remove_action( 'wprus_validate_user_creation', $callback, 10 );
		}
		$this->assertWPError( $received_result );
		$this->assertSame( 'wprus_username_exists', $received_result->get_error_code() );
		$this->assertFalse( get_user_by( 'login', 'identity_third_party' ) );
	}

	/** Remote checks accept only complete, trustworthy replies. */
	public function test_remote_identity_check_rejects_malformed_reply() {
		$identity = new Wprus_Api_Identity( 'identity', $this->settings );
		HttpMock::queue(
			'https://wprus-peer.test/wprus/token/',
			HttpMock::response(
				wp_json_encode(
					array(
						'nonce'  => 'identity-token',
						'expiry' => time() + 600,
					)
				)
			)
		);
		HttpMock::queue( 'https://wprus-peer.test/wprus/identity/', HttpMock::response( '{"success":true,"data":{"username_exists":"false","email_exists":false}}' ) );
		$result = $identity->check_remote_identity( 'identity_candidate', 'candidate@example.test' );
		$this->assertWPError( $result );
		$this->assertSame( 'wprus_identity_unavailable', $result->get_error_code() );
	}

	/** A connected site's existing username rejects registration. */
	public function test_remote_username_conflict_rejects_registration() {
		$identity = new Wprus_Api_Identity( 'identity', $this->settings );
		HttpMock::queue(
			'https://wprus-peer.test/wprus/token/',
			HttpMock::response(
				wp_json_encode(
					array(
						'nonce'  => 'identity-token',
						'expiry' => time() + 600,
					)
				)
			)
		);
		HttpMock::queue(
			'https://wprus-peer.test/wprus/identity/',
			HttpMock::response(
				wp_json_encode(
					array(
						'success' => true,
						'data'    => array(
							'username_exists' => true,
							'email_exists'    => false,
						),
					)
				)
			)
		);
		wp_set_current_user( 0 );
		$errors = $identity->validate_registration( new WP_Error(), 'identity_candidate', 'candidate@example.test' );
		$this->assertSame( 'wprus_username_exists', $errors->get_error_code() );
		$this->assertStringContainsString( 'username is already in use', $errors->get_error_message() );
	}

	/** A matching email on another site also rejects registration. */
	public function test_remote_email_conflict_rejects_registration() {
		$identity = new Wprus_Api_Identity( 'identity', $this->settings );
		HttpMock::queue(
			'https://wprus-peer.test/wprus/token/',
			HttpMock::response(
				wp_json_encode(
					array(
						'nonce'  => 'identity-token',
						'expiry' => time() + 600,
					)
				)
			)
		);
		HttpMock::queue(
			'https://wprus-peer.test/wprus/identity/',
			HttpMock::response(
				wp_json_encode(
					array(
						'success' => true,
						'data'    => array(
							'username_exists' => false,
							'email_exists'    => true,
						),
					)
				)
			)
		);
		$result = $identity->check_remote_identity( 'identity_candidate', 'candidate@example.test' );
		$this->assertWPError( $result );
		$this->assertSame( 'wprus_email_exists', $result->get_error_code() );
	}

	/** Registration works when there is no connected site to check. */
	public function test_no_connected_sites_allows_validated_registration() {
		$GLOBALS['wprus_test_settings']['sites'] = array();
		$this->reload_settings();
		$identity = new Wprus_Api_Identity( 'identity', $this->settings );
		wp_set_current_user( 0 );
		$this->assertFalse( $identity->validate_registration( new WP_Error(), 'identity_alone', 'alone@example.test' )->has_errors() );
		$data = array(
			'user_login' => 'identity_alone',
			'user_email' => 'alone@example.test',
		);
		$this->assertSame( $data, $identity->require_validated_creation( $data, false, null, array() ) );
	}

	/** Every listed user management capability allows local account creation. */
	public function test_each_user_management_capability_bypasses_remote_creation_checks() {
		$id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $id );
		$user                  = get_userdata( $id );
		$capabilities          = array( 'manage_network_users', 'promote_users', 'remove_users', 'edit_users', 'add_users', 'create_users', 'delete_users' );
		$allow_each_capability = static function ( $required, $requested ) use ( $capabilities ) {
			return in_array( $requested, $capabilities, true ) ? array( $requested ) : $required;
		};
		add_filter( 'map_meta_cap', $allow_each_capability, 10, 2 );
		foreach ( $capabilities as $capability ) {
			$user->add_cap( $capability );
			wp_set_current_user( 0 );
			wp_set_current_user( $id );
			$this->assertTrue( Wprus_Api_Identity::can_manage_users(), $capability );
			$user->remove_cap( $capability );
		}
		remove_filter( 'map_meta_cap', $allow_each_capability, 10 );
		wp_set_current_user( 0 );
		wp_set_current_user( $id );
		$this->assertFalse( Wprus_Api_Identity::can_manage_users() );
	}

	/** A failed second check skips only this user's outgoing Create action. */
	public function test_post_creation_conflict_suppresses_create_without_user_metadata() {
		$id       = self::factory()->user->create(
			array(
				'user_login' => 'identity_postcheck',
				'user_email' => 'postcheck@example.test',
			)
		);
		$identity = new Wprus_Api_Identity( 'identity', $this->settings );
		HttpMock::queue(
			'https://wprus-peer.test/wprus/token/',
			HttpMock::response(
				wp_json_encode(
					array(
						'nonce'  => 'identity-token',
						'expiry' => time() + 600,
					)
				)
			)
		);
		HttpMock::queue(
			'https://wprus-peer.test/wprus/identity/',
			HttpMock::response(
				wp_json_encode(
					array(
						'success' => true,
						'data'    => array(
							'username_exists' => true,
							'email_exists'    => false,
						),
					)
				)
			)
		);
		wp_set_current_user( 0 );
		$identity->check_created_user( $id );

		$this->assertTrue( Wprus_Api_Identity::is_creation_blocked( $id ) );
		$this->assertFalse( metadata_exists( 'user', $id, 'wprus_identity_blocked' ) );
		$this->assertInstanceOf( WP_User::class, get_userdata( $id ) );

		$GLOBALS['wprus_test_settings']['sites'][0]['outgoing_actions']['create'] = true;
		$this->reload_settings();
		$sent     = 0;
		$callback = static function () use ( &$sent ) {
			++$sent;
		};
		add_action( 'wprus_before_firing_action', $callback );
		try {
			$this->action( 'create' )->notify_remote( $id );
		} finally {
			remove_action( 'wprus_before_firing_action', $callback );
		}
		$this->assertSame( 0, $sent );
	}

	/** Different local and remote roles do not affect identity matching. */
	public function test_matching_identity_accepts_different_roles() {
		$id = self::factory()->user->create(
			array(
				'user_login' => 'identity_roles',
				'user_email' => 'same@example.test',
				'role'       => 'administrator',
			)
		);
		$this->assertTrue(
			Wprus_Api_Identity::verify_incoming_identity(
				$this->payload( 'identity_roles' ) + array(
					'identity_email' => 'same@example.test',
					'roles'          => array( 'customer' ),
				),
				'update'
			)
		);
		$this->assertInstanceOf( WP_User::class, get_userdata( $id ) );
	}
}
