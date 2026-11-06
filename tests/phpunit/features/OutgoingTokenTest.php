<?php
/**
 * Token responses received from a connected site.
 *
 * @package Wprus
 */

use AnyapeWPTestTools\HttpMock;

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

/**
 * Check that outgoing actions require a usable token response.
 *
 * @group wprus
 * @group wprus-actions
 */
class OutgoingTokenTest extends Wprus_Feature_Test_Case {
	/** A valid token is reused for later actions to the same site. */
	public function test_valid_token_is_reused_for_the_same_user_and_site() {
		require_once dirname( __DIR__, 2 ) . '/support/class-wprus-test-peer.php';
		self::factory()->user->create( array( 'user_login' => 'alice' ) );
		$peer                 = new Wprus_Test_Peer();
		$peer->users['alice'] = array();
		$reply                = static function ( $arguments, $url ) use ( $peer ) {
			return $peer->receive( $arguments, $url );
		};
		HttpMock::queue( 'https://wprus-peer.test/wprus/token/', $reply );
		HttpMock::queue( 'https://wprus-peer.test/wprus/update/', $reply, $reply );
		$api = $this->action( 'update' );
		$api->fire_action(
			'https://wprus-peer.test',
			array(
				'username'     => 'alice',
				'display_name' => 'First',
			)
		);
		$api->fire_action(
			'https://wprus-peer.test',
			array(
				'username'     => 'alice',
				'display_name' => 'Second',
			)
		);
		$this->assertSame( 'Second', $peer->users['alice']['display_name'] );
		$this->assertSame( array( '/wprus/token/', '/wprus/update/', '/wprus/update/' ), array_column( $peer->requests, 'path' ) );
	}

	/** Malformed JSON cannot cause an action to be sent. */
	public function test_malformed_token_response_does_not_send_the_action() {
		self::factory()->user->create( array( 'user_login' => 'alice' ) );
		HttpMock::queue( 'https://wprus-peer.test/wprus/token/', HttpMock::malformed_json() );
		$sent = false;
		HttpMock::queue(
			'https://wprus-peer.test/wprus/create/',
			static function () use ( &$sent ) {
				$sent = true;
				return HttpMock::response();
			}
		);
		$this->action( 'create' )->fire_action( 'https://wprus-peer.test', array( 'username' => 'alice' ) );
		$this->assertFalse( $sent );
	}

	/**
	 * An incomplete or expired token cannot be used for an action.
	 *
	 * @dataProvider incomplete_tokens
	 * @param array $token Token response.
	 * @return void
	 */
	public function test_incomplete_token_does_not_send_the_action( $token ) {
		self::factory()->user->create( array( 'user_login' => 'alice' ) );
		HttpMock::queue( 'https://wprus-peer.test/wprus/token/', HttpMock::response( wp_json_encode( $token ) ) );
		$sent = false;
		HttpMock::queue(
			'https://wprus-peer.test/wprus/create/',
			static function () use ( &$sent ) {
				$sent = true;
				return HttpMock::response();
			}
		);
		$this->action( 'create' )->fire_action( 'https://wprus-peer.test', array( 'username' => 'alice' ) );
		$this->assertFalse( $sent );
	}

	/**
	 * Supply responses that cannot be used as tokens.
	 *
	 * @return array Named cases.
	 */
	public static function incomplete_tokens() {
		return array(
			'no expiry' => array( array( 'nonce' => 'test-token' ) ),
			'no nonce'  => array( array( 'expiry' => time() + 600 ) ),
			'empty nonce' => array( array( 'nonce' => '', 'expiry' => time() + 600 ) ),
			'bad expiry' => array( array( 'nonce' => 'test-token', 'expiry' => 'invalid' ) ),
			'expired' => array( array( 'nonce' => 'test-token', 'expiry' => time() - 1 ) ),
		);
	}
}
