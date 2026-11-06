<?php
/**
 * Prepare disposable WordPress data for browser tests.
 *
 * @package Wprus
 */

if ( defined( 'WPRUS_TEST_FIXTURES_LOADED' ) ) {
	return;
}

$wprus_manifest_path = getenv( 'ANYAPE_WP_TEST_TOOLS_E2E_MANIFEST' );
$wprus_manifest      = $wprus_manifest_path && is_file( $wprus_manifest_path ) ? json_decode( file_get_contents( $wprus_manifest_path ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- This local file is created by the test runner.
$wprus_selected      = false;
foreach ( $wprus_manifest['plugins'] ?? array() as $wprus_extension ) {
	if ( 'wp-remote-users-sync' === ( $wprus_extension['slug'] ?? '' ) && ! empty( $wprus_extension['tests_enabled'] ) ) {
		$wprus_selected = true;
		break;
	}
}
if ( ! $wprus_selected ) {
	return;
}
define( 'WPRUS_TEST_FIXTURES_LOADED', true );

// Load the shared test settings.
require_once dirname( __DIR__ ) . '/support/settings.php';
$wprus_test_settings = wprus_test_settings();
$wprus_test_settings['sites'][0]['outgoing_actions']['create'] = true;
update_option( 'wprus', $wprus_test_settings );
delete_option( 'wprus_tests_peer' );
delete_option( 'wprus_tests_peer_mode' );
update_option( 'default_role', 'subscriber' );

$wprus_test_user = get_user_by( 'login', 'wprus_test_alice' );
if ( ! $wprus_test_user ) {
	$wprus_test_user = wp_insert_user(
		array(
			'user_login' => 'wprus_test_alice',
			'user_email' => 'wprus-alice@example.test',
			'user_pass'  => 'wprus-test-password',
			'role'       => 'subscriber',
		)
	);
	if ( is_wp_error( $wprus_test_user ) ) {
		WP_CLI::error( $wprus_test_user->get_error_message() );
	}
}
$wprus_test_user_id = $wprus_test_user instanceof WP_User ? $wprus_test_user->ID : $wprus_test_user;
update_user_meta( $wprus_test_user_id, 'test_note', 'test fixture' );
require_once dirname( __DIR__ ) . '/support/class-wprus-test-peer.php';
$wprus_test_peer                            = new Wprus_Test_Peer();
$wprus_test_peer->users['wprus_test_alice'] = array( 'logged_in' => false );
update_option( 'wprus_tests_peer', $wprus_test_peer, false );

$wprus_test_loader = "<?php\nif ( defined( 'ANYAPE_WP_TEST_TOOLS_E2E' ) && ANYAPE_WP_TEST_TOOLS_E2E ) {\n    require WP_PLUGIN_DIR . '/wp-remote-users-sync/tests/e2e/support/local-control.php';\n}\n";
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- The test runner restores the temporary must-use plugin.
if ( false === file_put_contents( WPMU_PLUGIN_DIR . '/zz-wprus-tests.php', $wprus_test_loader ) ) {
	WP_CLI::error( 'Could not install the temporary browser-test helper.' );
}
set_transient( 'wprus_flush', true );
