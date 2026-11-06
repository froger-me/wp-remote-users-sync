<?php
/**
 * Allowed IP address and range checks.
 *
 * @package Wprus
 */

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

/**
 * Check IP address and range matching.
 *
 * @group wprus
 * @group wprus-unit
 */
class NetworkRulesTest extends Wprus_Feature_Test_Case {
	/** A single address is given a full-length mask before it is checked. */
	public function test_a_single_address_is_normalized_to_an_exact_range() {
		$property = new ReflectionProperty( Wprus_Api_Abstract::class, 'ip_whitelist' );
		$property->setAccessible( true );
		$original = $property->getValue();
		try {
			$GLOBALS['wprus_test_settings']['ip_whitelist'] = '192.0.2.9';
			$this->reload_settings();
			$property->setValue( null, null );
			$this->action( 'create' );
			$this->assertSame( array( '192.0.2.9/32' ), $property->getValue() );
		} finally {
			$property->setValue( null, $original );
		}
	}

	/**
	 * An address matches only a range that contains it.
	 *
	 * @dataProvider network_cases
	 * @param string $address Address to check.
	 * @param string $range Configured address range.
	 * @param bool   $expected Expected result.
	 * @return void
	 */
	public function test_address_matches_expected_range( $address, $range, $expected ) {
		$method = new ReflectionMethod( Wprus_Api_Abstract::class, 'cidr_match' );
		$method->setAccessible( true );
		$this->assertSame( $expected, $method->invoke( $this->action( 'create' ), $address, $range ) );
	}

	/**
	 * Supply addresses, boundaries, and malformed ranges.
	 *
	 * @return array Named cases.
	 */
	public static function network_cases() {
		return array(
			'exact address'       => array( '192.0.2.9', '192.0.2.9/32', true ),
			'different address'   => array( '192.0.2.10', '192.0.2.9/32', false ),
			'range first address' => array( '192.0.2.0', '192.0.2.0/24', true ),
			'range last address'  => array( '192.0.2.255', '192.0.2.0/24', true ),
			'outside range'       => array( '192.0.3.0', '192.0.2.0/24', false ),
			'invalid mask'        => array( '192.0.2.9', '192.0.2.0/33', false ),
			'negative mask'       => array( '192.0.2.9', '192.0.2.0/-1', false ),
			'text mask'          => array( '192.0.2.9', '192.0.2.0/text', false ),
			'missing mask'       => array( '192.0.2.9', '192.0.2.0', false ),
			'extra separator'    => array( '192.0.2.9', '192.0.2.0/24/1', false ),
			'zero address'       => array( '0.0.0.0', '0.0.0.0/32', true ),
			'invalid address'     => array( 'not-an-address', '192.0.2.0/24', false ),
			'all IPv4 addresses'  => array( '192.0.2.9', '0.0.0.0/0', true ),
		);
	}
}
