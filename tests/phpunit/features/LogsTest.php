<?php
/**
 * Activity log behavior.
 *
 * @package Wprus
 */

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

/**
 * Check recording and display of activity messages.
 *
 * @group wprus
 */
class LogsTest extends Wprus_Feature_Test_Case {
	/**
	 * Previous log settings.
	 *
	 * @var array
	 */
	private $previous_log_settings;

	/** Restore the logger's shared settings after each case. */
	protected function tearDown(): void {
		if ( null !== $this->previous_log_settings ) {
			Wprus_Logger::$log_settings = $this->previous_log_settings;
		}
		parent::tearDown();
	}

	/** Disabling logs prevents new database entries. */
	public function test_disabled_logging_does_not_add_entries() {
		$this->previous_log_settings = Wprus_Logger::$log_settings;
		Wprus_Logger::$log_settings  = array(
			'enable'  => false,
			'min_num' => 100,
		);
		$count                       = Wprus_Logger::get_logs_count();
		Wprus_Logger::log( 'test-disabled-message', 'info', 'db_log' );
		$this->assertSame( $count, Wprus_Logger::get_logs_count() );
	}

	/** Enabled logging stores and safely displays a message. */
	public function test_enabled_logging_stores_and_escapes_messages() {
		$this->previous_log_settings = Wprus_Logger::$log_settings;
		Wprus_Logger::$log_settings  = array(
			'enable'  => true,
			'min_num' => 100,
		);
		$count                       = Wprus_Logger::get_logs_count();
		Wprus_Logger::log( '<script>test-log</script>', 'warning', 'db_log' );
		$this->assertSame( $count + 1, Wprus_Logger::get_logs_count() );
		$html = Wprus_Logger::get_logs();
		$this->assertStringContainsString( '&lt;script&gt;test-log&lt;/script&gt;', $html );
		$this->assertStringNotContainsString( '<script>test-log</script>', $html );
	}

	/** A received password is hidden in the activity log. */
	public function test_received_password_is_not_visible_in_the_log() {
		$this->previous_log_settings = Wprus_Logger::$log_settings;
		Wprus_Logger::$log_settings  = array(
			'enable'  => true,
			'min_num' => 100,
		);
		$request                     = $this->payload( 'alice' ) + array(
			'user_email' => 'alice@example.test',
			'user_pass'  => 'private-test-password',
		);
		$this->assertTrue( $this->action( 'create' )->receive( $request ) );
		$html = Wprus_Logger::get_logs();
		$this->assertStringNotContainsString( 'private-test-password', $html );
		$this->assertStringContainsString( '** HIDDEN **', $html );
	}

	/** An empty metadata request does not create an empty log message. */
	public function test_empty_metadata_changes_do_not_add_a_log_entry() {
		$this->previous_log_settings = Wprus_Logger::$log_settings;
		Wprus_Logger::$log_settings  = array(
			'enable'  => true,
			'min_num' => 100,
		);
		self::factory()->user->create( array( 'user_login' => 'alice' ) );
		$count = Wprus_Logger::get_logs_count();
		$this->assertFalse( $this->action( 'meta' )->receive( $this->payload( 'alice' ) + array( 'update' => array() ) ) );
		$this->assertSame( $count, Wprus_Logger::get_logs_count() );
	}

	/** Cleanup retains the configured number of most recent entries. */
	public function test_cleanup_keeps_the_configured_number_of_entries() {
		$this->previous_log_settings = Wprus_Logger::$log_settings;
		Wprus_Logger::$log_settings  = array(
			'enable'  => true,
			'min_num' => 2,
		);
		Wprus_Logger::log( 'first', 'info', 'db_log' );
		Wprus_Logger::log( 'second', 'info', 'db_log' );
		Wprus_Logger::log( 'third', 'info', 'db_log' );
		Wprus_Logger::clear_logs();
		$this->assertSame( 2, Wprus_Logger::get_logs_count() );
	}
}
