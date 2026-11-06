<?php
/**
 * Invalid metadata requests and exclusion rules.
 *
 * @package Wprus
 */

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- These values are fields in simulated requests.

/**
 * Check metadata requests that cannot be applied.
 *
 * @group wprus
 * @group wprus-actions
 */
class MetadataRuleValidationTest extends Wprus_Feature_Test_Case {
	/** A request for a missing user is rejected without warnings. */
	public function test_missing_user_does_not_produce_a_warning_or_change_other_users() {
		$id     = self::factory()->user->create( array( 'user_login' => 'existing_user' ) );
		$result = $this->action( 'meta' )->receive(
			$this->payload( 'missing_user' ) + array(
				'update' => array(
					'custom_key' => array(
						array(
							'meta_value' => 'changed',
							'prev_value' => '',
						),
					),
				),
			)
		);
		$this->assertFalse( $result );
		$this->assertFalse( metadata_exists( 'user', $id, 'custom_key' ) );
	}

	/**
	 * An invalid exclusion pattern does not emit warnings or block unrelated keys.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @return void
	 */
	public function test_invalid_filter_pattern_is_handled_without_a_warning() {
		$id = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		Wprus_Logger::$log_settings = array( 'enable' => true, 'min_num' => 100 );
		add_filter(
			'wprus_incoming_meta_exclusions',
			static function ( $rules ) {
				$rules['regex'][] = '/[invalid/';
				return $rules;
			}
		);
		$result = $this->action( 'meta' )->receive(
			$this->payload( 'alice' ) + array(
				'update' => array(
					'custom_key' => array(
						array(
							'meta_value' => 'accepted',
							'prev_value' => '',
						),
					),
				),
			)
		);
		$this->assertTrue( $result );
		$this->assertSame( 'accepted', get_user_meta( $id, 'custom_key', true ) );
		$this->assertStringContainsString( 'Ignored an invalid metadata exclusion expression.', Wprus_Logger::get_logs() );
	}
}
