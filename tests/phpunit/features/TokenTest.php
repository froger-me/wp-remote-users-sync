<?php
/**
 * Security tokens used between connected sites.
 *
 * @package Wprus
 */

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

/**
 * Check token creation and use.
 *
 * @group wprus
 * @group wprus-actions
 */
class TokenTest extends Wprus_Feature_Test_Case {
	/** An expired token cannot be used and is removed when checked. */
	public function test_expired_token_is_rejected() {
		global $wpdb;
		Wprus_Nonce::init( false, false, 1800 );
		$token = Wprus_Nonce::create_nonce();
		$table = Wprus::get_table( 'wprus_nonce' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Expire the disposable token without waiting for real time to pass.
		$wpdb->update( $table, array( 'expiry' => time() - 1 ), array( 'nonce' => $token ) );
		$this->assertFalse( Wprus_Nonce::validate_nonce( $token ) );
		$this->assertSame( 0, (int) Wprus_Nonce::get_nonce_expiry( $token ) );
	}

	/** Reusable tokens can be checked more than once. */
	public function test_reusable_token_is_valid_until_removed() {
		Wprus_Nonce::init( false, false, 1800 );
		$token = Wprus_Nonce::create_nonce( true );
		$this->assertIsArray( $token );
		$this->assertNotEmpty( $token['nonce'] );
		$this->assertGreaterThan( time(), (int) $token['expiry'] );
		$this->assertTrue( Wprus_Nonce::validate_nonce( $token['nonce'] ) );
		$this->assertTrue( Wprus_Nonce::validate_nonce( $token['nonce'] ) );
		$this->assertTrue( Wprus_Nonce::delete_nonce( $token['nonce'] ) );
		$this->assertFalse( Wprus_Nonce::validate_nonce( $token['nonce'] ) );
	}

	/** A single-use token cannot be reused. */
	public function test_single_use_token_is_consumed() {
		Wprus_Nonce::init( true, false, 1800 );
		$token = Wprus_Nonce::create_nonce();
		$this->assertTrue( Wprus_Nonce::validate_nonce( $token ) );
		$this->assertFalse( Wprus_Nonce::validate_nonce( $token ) );
		Wprus_Nonce::init( false, false, 1800 );
	}

	/** Empty and unissued tokens do not grant access. */
	public function test_unknown_or_empty_tokens_are_rejected() {
		Wprus_Nonce::init( false, false, 1800 );
		$this->assertFalse( Wprus_Nonce::validate_nonce( '' ) );
		$this->assertFalse( Wprus_Nonce::validate_nonce( 'not-a-token' ) );
	}
}
