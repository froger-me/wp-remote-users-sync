<?php
/**
 * Connected-site settings and action selection.
 *
 * @package Wprus
 */

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

/**
 * Check settings used for synchronization.
 *
 * @group wprus
 */
class SettingsTest extends Wprus_Feature_Test_Case {
	/** Incoming and outgoing settings are selected independently. */
	public function test_actions_are_selected_by_direction() {
		$this->assertCount( 1, $this->settings->get_sites( 'create', 'incoming' ) );
		$this->assertSame( array(), $this->settings->get_sites( 'create', 'outgoing' ) );
		$this->assertSame( array(), $this->settings->get_sites( 'create', 'invalid' ) );
	}

	/** A different host is not treated as the configured peer. */
	public function test_peer_lookup_requires_the_configured_host() {
		$this->assertIsArray( $this->settings->get_site( 'https://wprus-peer.test', 'update', 'incoming' ) );
		$this->assertFalse( $this->settings->get_site( 'https://unrelated.test', 'update', 'incoming' ) );
	}

	/** Default exclusion rules include ordinary private WordPress fields. */
	public function test_settings_exclude_wordpress_fields_from_metadata() {
		$rules = $this->settings->get_excluded_meta_request_rules();
		$this->assertContains( 'session_tokens', $rules['keys'] );
		$this->assertContains( '_application_passwords', $rules['keys'] );
		$this->assertContains( 'first_name', $rules['keys'] );
		$this->assertNotEmpty( $rules['regex'] );
	}
}
