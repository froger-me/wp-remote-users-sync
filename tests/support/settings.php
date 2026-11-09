<?php
/**
 * Settings for the disposable connected site.
 *
 * @package Wprus
 */

/**
 * Build settings for one simulated connected site.
 *
 * @return array Plugin settings.
 */
function wprus_test_settings() {
	$actions = array( 'login', 'logout', 'create', 'update', 'delete', 'password', 'role', 'meta' );

	return array(
		'encryption'      => array(
			'aes_key'      => 'wprus-test-encryption-key',
			'hmac_key'     => 'wprus-test-signature-key',
			'token_expiry' => 1800,
		),
		'logs'            => array(
			'enable'  => false,
			'min_num' => 100,
		),
		'browser_support' => array(
			'force_login_logout_strict'         => false,
			'force_disable_login_logout_strict' => false,
			'silent_login_logout_strict'        => false,
		),
		'ip_whitelist'    => '',
		'sites'           => array(
			array(
				'url'                  => 'https://wprus-peer.test',
				'incoming_actions'     => array_fill_keys( $actions, true ),
				'outgoing_actions'     => array_fill_keys( $actions, false ),
				'incoming_roles'       => array(),
				'outgoing_roles'       => array(),
				'incoming_roles_merge' => false,
				'incoming_meta'        => array( 'favourite_colour', 'custom_key', 'allowed_key', 'test_note' ),
				'outgoing_meta'        => array(),
			),
		),
	);
}
