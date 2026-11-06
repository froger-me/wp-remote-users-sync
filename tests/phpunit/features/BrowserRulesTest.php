<?php
/**
 * Browser-dependent login and logout choices.
 *
 * @package Wprus
 */

// Load the shared action helpers.
require_once dirname( __DIR__ ) . '/support/class-wprus-test-create.php';

// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.WP.GlobalVariablesOverride.Prohibited -- The test saves, changes, and restores these browser globals.

/**
 * Check when login requests require a redirect.
 *
 * @group wprus
 * @group wprus-actions
 */
class BrowserRulesTest extends Wprus_Feature_Test_Case {
	/**
	 * Browser and configuration choose the expected login behavior.
	 *
	 * @dataProvider browser_cases
	 * @param bool   $safari Whether WordPress detects Safari.
	 * @param string $agent Browser user agent.
	 * @param bool   $force Whether redirects are forced.
	 * @param bool   $disable Whether redirects are disabled.
	 * @param bool   $expected Whether a redirect is needed.
	 * @return void
	 */
	public function test_redirect_choice( $safari, $agent, $force, $disable, $expected ) {
		global $is_safari;
		$original_safari = $is_safari;
		$original_agent  = $_SERVER['HTTP_USER_AGENT'] ?? null;
		$api             = $this->action( 'login' );
		$property        = new ReflectionProperty( Wprus_Api_Abstract::class, 'browser_support_settings' );
		$property->setAccessible( true );
		$original_settings = $property->getValue();
		try {
			$is_safari                  = $safari;
			$_SERVER['HTTP_USER_AGENT'] = $agent;
			$property->setValue(
				null,
				array(
					'force_login_logout_strict'         => $force,
					'force_disable_login_logout_strict' => $disable,
					'silent_login_logout_strict'        => false,
				)
			);
			$this->assertSame( $expected, $api->needs_redirect() );
		} finally {
			$is_safari = $original_safari;
			if ( null === $original_agent ) {
				unset( $_SERVER['HTTP_USER_AGENT'] );
			} else {
				$_SERVER['HTTP_USER_AGENT'] = $original_agent;
			}
			$property->setValue( null, $original_settings );
		}
	}

	/**
	 * Supply browser and setting combinations.
	 *
	 * @return array Named combinations.
	 */
	public static function browser_cases() {
		return array(
			'ordinary browser'          => array( false, 'Chrome', false, false, false ),
			'Safari'                    => array( true, 'Safari', false, false, true ),
			'iPhone'                    => array( false, 'iPhone', false, false, true ),
			'iPad'                      => array( false, 'iPad', false, false, true ),
			'forced'                    => array( false, 'Chrome', true, false, true ),
			'disabled despite Safari'   => array( true, 'Safari', false, true, false ),
			'disabled overrides forced' => array( false, 'Chrome', true, true, false ),
		);
	}
}
