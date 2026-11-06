<?php
/**
 * Plugin hooks used by other code.
 *
 * @package Wprus
 */

use AnyapeWPTestTools\HttpMock;

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

/**
 * Check objects and outgoing events supplied to integrations.
 *
 * @group wprus
 */
class ExtensionHooksTest extends Wprus_Feature_Test_Case {
	/** The ready action supplies the initialized plugin objects. */
	public function test_ready_action_supplies_the_plugin_objects() {
		$this->assertArrayHasKey( 'wprus_test_ready', $GLOBALS );
		list( $plugin, $api, $settings, $logger ) = $GLOBALS['wprus_test_ready'];
		$this->assertInstanceOf( Wprus::class, $plugin );
		$this->assertInstanceOf( Wprus_Settings::class, $settings );
		$this->assertInstanceOf( Wprus_Logger::class, $logger );
		foreach ( array( 'create', 'update', 'delete', 'password', 'role', 'meta', 'login', 'logout' ) as $name ) {
			$this->assertArrayHasKey( $name, $api );
			$this->assertInstanceOf( Wprus_Api_Abstract::class, $api[ $name ] );
		}
	}

	/** Outgoing data filters and before/after actions receive the intended values. */
	public function test_outgoing_hooks_receive_action_data() {
		require_once dirname( __DIR__, 2 ) . '/support/class-wprus-test-peer.php';
		self::factory()->user->create( array( 'user_login' => 'alice' ) );
		$peer                 = new Wprus_Test_Peer();
		$peer->users['alice'] = array();
		$reply                = static function ( $arguments, $url ) use ( $peer ) {
			return $peer->receive( $arguments, $url );
		};
		HttpMock::queue( 'https://wprus-peer.test/wprus/token/', $reply );
		HttpMock::queue( 'https://wprus-peer.test/wprus/update/', $reply );
		$events = array();
		$before = static function ( $action, $url, $data ) use ( &$events ) {
			$events[] = array( 'before', $action, $url, $data );
		};
		$after  = static function ( $action, $url, $data ) use ( &$events ) {
			$events[] = array( 'after', $action, $url, $data );
		};
		$filter = static function ( $data ) {
			$data['display_name'] = 'Filtered name';
			return $data;
		};
		add_action( 'wprus_before_firing_action', $before, 10, 3 );
		add_action( 'wprus_after_firing_action', $after, 10, 3 );
		add_filter( 'wprus_action_data', $filter );
		try {
			$this->action( 'update' )->fire_action(
				'https://wprus-peer.test',
				array(
					'username'     => 'alice',
					'display_name' => 'Original',
				)
			);
			$this->assertSame( 'Filtered name', $peer->users['alice']['display_name'] );
			$this->assertSame( array( 'before', 'after' ), array_column( $events, 0 ) );
			$this->assertSame( 'update', $events[0][1] );
			$this->assertSame( 'Original', $events[0][3]['display_name'] );
			$this->assertSame( 'Filtered name', $events[1][3]['display_name'] );
		} finally {
			remove_action( 'wprus_before_firing_action', $before, 10 );
			remove_action( 'wprus_after_firing_action', $after, 10 );
			remove_filter( 'wprus_action_data', $filter );
		}
	}
}
