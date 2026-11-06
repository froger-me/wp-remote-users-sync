<?php
/**
 * Rules excluding user metadata from synchronization settings.
 *
 * @package Wprus
 */

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

/**
 * Check each default metadata exclusion family.
 *
 * @group wprus
 * @group wprus-unit
 */
class SettingsRulesTest extends Wprus_Feature_Test_Case {
	/** Settings filters can add exact keys and escaped wildcard patterns. */
	public function test_settings_filters_add_exact_and_pattern_exclusions() {
		$exact = static function ( $keys ) {
			$keys[] = 'private_field';
			return $keys;
		};
		$patterns = static function ( $keys ) {
			$keys[] = 'private\\_prefix\\_%';
			return $keys;
		};
		add_filter( 'wprus_excluded_meta_keys', $exact );
		add_filter( 'wprus_excluded_meta_keys_like', $patterns );
		try {
			$rules = $this->settings->get_excluded_meta_request_rules();
			$this->assertContains( 'private_field', $rules['keys'] );
			$custom = end( $rules['regex'] );
			$this->assertSame( 1, preg_match( $custom, 'private_prefix_note' ) );
			$this->assertSame( 0, preg_match( $custom, 'privateXprefixXnote' ) );
		} finally {
			remove_filter( 'wprus_excluded_meta_keys', $exact );
			remove_filter( 'wprus_excluded_meta_keys_like', $patterns );
		}
	}

	/**
	 * The converted exclusions match the intended metadata keys.
	 *
	 * @dataProvider metadata_keys
	 * @param string $key Metadata key.
	 * @param bool   $expected Whether it is excluded.
	 * @return void
	 */
	public function test_default_pattern_matches( $key, $expected ) {
		$rules   = $this->settings->get_excluded_meta_request_rules();
		$matched = in_array( $key, $rules['keys'], true );
		foreach ( $rules['regex'] as $pattern ) {
			$matched = $matched || 1 === preg_match( $pattern, $key );
		}
		$this->assertSame( $expected, $matched );
	}

	/**
	 * Supply excluded keys and nearby permitted examples.
	 *
	 * @return array Named examples.
	 */
	public static function metadata_keys() {
		return array(
			'capabilities'        => array( 'prefix_capabilities', true ),
			'capabilities suffix' => array( 'prefix_capabilities_extra', false ),
			'user level'          => array( 'prefix_user_level', true ),
			'user settings'       => array( 'prefix_user-settings', true ),
			'user settings time'  => array( 'prefix_user-settings-time', true ),
			'dashboard'           => array( 'prefix_dashboard_quick_press_last_post_id', true ),
			'plugin prefix'       => array( 'wprus_private', true ),
			'plugin suffix'       => array( 'private_wprus', true ),
			'plugin in middle'    => array( 'private_wprus_note', false ),
			'ordinary key'        => array( 'favourite_colour', false ),
			'case differences'    => array( 'PREFIX_CAPABILITIES', true ),
		);
	}
}
