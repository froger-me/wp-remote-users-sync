<?php
/**
 * Verify the encryption and signature used for messages.
 *
 * @package Wprus
 */

/**
 * Check plugin features.
 *
 * @group wprus
 * @group wprus-unit
 */
class CryptoTest extends PHPUnit\Framework\TestCase {
	/** A fixed message produced by the protocol decrypts correctly. */
	public function test_fixed_protocol_example_decrypts() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read a local test example.
		$vector = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/support/protocol-vectors.json' ), true );
		$this->assertSame( $vector['message'], Wprus_Crypto::decrypt( $vector['bundle'], $vector['aes_key'], $vector['hmac_key'] ) );
	}

	/** Encryption preserves Unicode and changes the encrypted bytes each time. */
	public function test_encrypted_messages_round_trip_and_use_fresh_random_values() {
		$message = '{"name":"测试 Alice","value":0}';
		$first   = Wprus_Crypto::encrypt( $message, 'encryption', 'signature' );
		$second  = Wprus_Crypto::encrypt( $message, 'encryption', 'signature' );
		$this->assertIsString( $first );
		$this->assertNotSame( $first, $second );
		$this->assertSame( $message, Wprus_Crypto::decrypt( $first, 'encryption', 'signature' ) );
		$this->assertSame( $message, Wprus_Crypto::decrypt( $second, 'encryption', 'signature' ) );
	}

	/** An incorrect signature key cannot verify a message. */
	public function test_wrong_signature_key_rejects_the_message() {
		$encrypted = Wprus_Crypto::encrypt( 'message', 'encryption', 'signature' );
		$this->assertFalse( Wprus_Crypto::decrypt( $encrypted, 'encryption', 'other-signature' ) );
	}

	/** Encoded binary values are reversible, including forward slashes. */
	public function test_binary_encoding_round_trip() {
		$bytes = "\xff\xee\x00\x80";
		$this->assertSame( $bytes, Wprus_Crypto::base64url_decode( Wprus_Crypto::base64url_encode( $bytes ) ) );
		$this->assertStringNotContainsString( '/', Wprus_Crypto::base64url_encode( $bytes ) );
	}
}
