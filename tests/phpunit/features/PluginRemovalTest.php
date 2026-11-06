<?php
/**
 * Data retained when the plugin is removed.
 *
 * @package Wprus
 */

use AnyapeWPTestTools\Lifecycle;

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

/**
 * Check plugin removal in the separate disposable database.
 *
 * @group wprus
 * @group destructive
 */
class PluginRemovalTest extends Wprus_Feature_Test_Case {
	/**
	 * Plugin-owned test data and tables are removed on uninstall.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @return void
	 */
	public function test_removal_clears_plugin_data_and_tables() {
		global $wpdb;
		$id = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		update_option( 'wprus_test_owned', 'remove-option' );
		update_user_meta( $id, 'wprus_test_owned', 'remove-metadata' );
		update_user_meta( $id, 'thirdparty_wprus_preference', 'remove-reserved-metadata' );
		$nonce_table = Wprus::get_table( 'wprus_nonce' );
		$logs_table  = Wprus::get_table( 'wprus_logs' );
		if ( ! defined( 'WPRUS_PLUGIN_BASEFILE' ) ) {
			define( 'WPRUS_PLUGIN_BASEFILE', WPRUS_PLUGIN_FILE );
		}
		// WordPress normally changes table operations to temporary tables in tests.
		// This destructive case inspects and then recreates plugin tables in the guarded test database.
		Lifecycle::assert_safe_database();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		try {
			Lifecycle::uninstall( 'wp-remote-users-sync/wprus.php' );
			wp_cache_delete( 'wprus_test_owned', 'options' );
			wp_cache_delete( 'alloptions', 'options' );
			$this->assertFalse( get_option( 'wprus_test_owned' ) );
			$this->assertFalse( metadata_exists( 'user', $id, 'wprus_test_owned' ) );
			$this->assertFalse( metadata_exists( 'user', $id, 'thirdparty_wprus_preference' ) );
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Inspect the test database schema after removal.
			$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $nonce_table ) ) ) );
			$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $logs_table ) ) ) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		} finally {
			Lifecycle::activate( 'wp-remote-users-sync/wprus.php' );
			add_filter( 'query', array( $this, '_create_temporary_tables' ) );
			add_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		}
	}

	/**
	 * Removing the plugin preserves unrelated data whose name contains wprus.
	 *
	 * @dataProvider unrelated_data_types
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @param string $type Kind of data to inspect.
	 * @return void
	 */
	public function test_removal_preserves_unrelated_data( $type ) {
		$id = self::factory()->user->create( array( 'user_login' => 'alice' ) );
		update_option( 'shop_wprus_preference', 'keep-option' );
		update_user_meta( $id, 'thirdparty_wprusXpreference', 'keep-metadata' );
		// The test process already loaded the plugin, unlike a normal removal request.
		if ( ! defined( 'WPRUS_PLUGIN_BASEFILE' ) ) {
			define( 'WPRUS_PLUGIN_BASEFILE', WPRUS_PLUGIN_FILE );
		}
		try {
			Lifecycle::uninstall( 'wp-remote-users-sync/wprus.php' );
			if ( 'option' === $type ) {
				wp_cache_delete( 'shop_wprus_preference', 'options' );
				wp_cache_delete( 'alloptions', 'options' );
				$this->assertSame( 'keep-option', get_option( 'shop_wprus_preference' ) );
			} else {
				$this->assertSame( 'keep-metadata', get_user_meta( $id, 'thirdparty_wprusXpreference', true ) );
			}
		} finally {
			Lifecycle::activate( 'wp-remote-users-sync/wprus.php' );
		}
	}

	/**
	 * Supply unrelated data types.
	 *
	 * @return array Named cases.
	 */
	public static function unrelated_data_types() {
		return array(
			'option'   => array( 'option' ),
			'metadata' => array( 'metadata' ),
		);
	}
}
