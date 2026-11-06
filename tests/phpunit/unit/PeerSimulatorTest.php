<?php
/**
 * Check the simulated connected site's request handling.
 *
 * @package Wprus
 */

// Load the simulated site.
require_once dirname( __DIR__, 2 ) . '/support/class-wprus-test-peer.php';

/**
 * Check that the simulated site verifies requests before changing its users.
 *
 * @group wprus
 * @group wprus-unit
 */
class PeerSimulatorTest extends PHPUnit\Framework\TestCase {
	/** A valid token and signed message create a user on the simulated site. */
	public function test_valid_token_allows_a_user_to_be_created() {
		$peer       = new Wprus_Test_Peer();
		$token      = $peer->receive(
			array( 'body' => array( 'wprusdata' => $this->message( array( 'method' => 'post' ) ) ) ),
			'https://wprus-peer.test/wprus/token/'
		);
		$token_data = json_decode( $token['body'], true );
		$response   = $peer->receive(
			array(
				'body' => array(
					'token'     => $token_data['nonce'],
					'wprusdata' => $this->message(
						array(
							'username'   => 'alice',
							'user_email' => 'alice@example.test',
						)
					),
				),
			),
			'https://wprus-peer.test/wprus/create/'
		);
		$this->assertSame( 200, $response['response']['code'] );
		$this->assertSame( 'alice@example.test', $peer->users['alice']['user_email'] );
	}

	/** A token not issued by the simulated site cannot create a user. */
	public function test_unknown_token_does_not_create_a_user() {
		$peer     = new Wprus_Test_Peer();
		$response = $peer->receive(
			array(
				'body' => array(
					'token'     => 'unknown',
					'wprusdata' => $this->message( array( 'username' => 'alice' ) ),
				),
			),
			'https://wprus-peer.test/wprus/create/'
		);
		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( array(), $peer->users );
	}

	/** A message with a different signature cannot be accepted. */
	public function test_wrong_signature_is_rejected() {
		$peer     = new Wprus_Test_Peer();
		$message  = Wprus_Crypto::encrypt( '{"username":"alice"}', 'wprus-test-encryption-key', 'different-key' );
		$response = $peer->receive(
			array( 'body' => array( 'wprusdata' => $message ) ),
			'https://wprus-peer.test/wprus/create/'
		);
		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( array(), $peer->users );
	}

	/**
	 * Encrypt test data with the keys shared by the two simulated parties.
	 *
	 * @param array $data Message data.
	 * @return string Encrypted message.
	 */
	private function message( $data ) {
		return Wprus_Crypto::encrypt( wp_json_encode( $data ), 'wprus-test-encryption-key', 'wprus-test-signature-key' );
	}
}
