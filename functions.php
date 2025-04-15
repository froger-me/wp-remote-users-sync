<?php

if ( ! defined( 'ABSPATH' ) || ! defined( 'WPINC' ) ) {
	exit; // Exit if accessed directly
}

if ( ! function_exists( 'php_log' ) ) {
	function php_log( $message = '', $prefix = '' ) {
		$prefix   = $prefix ? ' ' . $prefix . ' => ' : ' => ';
		$trace    = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$caller   = end( $trace );
		$class    = isset( $caller['class'] ) ? $caller['class'] : '';
		$type     = isset( $caller['type'] ) ? $caller['type'] : '';
		$function = isset( $caller['function'] ) ? $caller['function'] : '';
		$context  = $class . $type . $function . $prefix;

		error_log( $context . print_r( $message, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r, WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

if ( ! function_exists( 'wp_hash_password' ) ) {
	function wp_hash_password(
		#[\SensitiveParameter]
		$password
	) {
		global $wp_hasher;

		if ( version_compare( $GLOBALS['wp_version'], '6.8', '<' ) ) {

			if ( empty( $wp_hasher ) ) {
				require_once ABSPATH . WPINC . '/class-phpass.php';

				$wp_hasher = new PasswordHash( 8, true ); // @codingStandardsIgnoreLine
			}

			do_action( 'wprus_password', $password );

			return $wp_hasher->HashPassword( trim( $password ) );
		}

		if ( ! empty( $wp_hasher ) ) {
			do_action( 'wprus_password', $password );

			return $wp_hasher->HashPassword( trim( $password ) );
		}

		if ( strlen( $password ) > 4096 ) {
			do_action( 'wprus_password', '*' );

			return '*';
		}

		$algorithm = apply_filters( 'wp_hash_password_algorithm', PASSWORD_BCRYPT );
		$options   = apply_filters( 'wp_hash_password_options', array(), $algorithm );

		do_action( 'wprus_password', $password );

		if ( PASSWORD_BCRYPT !== $algorithm ) {
			return password_hash( $password, $algorithm, $options );
		}

		$password_to_hash = base64_encode( hash_hmac( 'sha384', trim( $password ), 'wp-sha384', true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		return '$wp' . password_hash( $password_to_hash, $algorithm, $options );
	}
}

if ( ! function_exists( 'wprus_log' ) ) {

	function wprus_log( $expression, $extend = '', $destination = 'error_log' ) {

		Wprus_Logger::log( $expression, $extend, $destination );
	}
}

if ( ! function_exists( 'wprus_get_template' ) ) {
	function wprus_get_template( $template_name, $args = array(), $load = true, $required_once = false ) {
		$template_name = apply_filters( 'wprus_get_template_name', $template_name, $args );
		$template_args = apply_filters( 'wprus_get_template_args', $args, $template_name );

		if ( ! empty( $template_args ) ) {

			foreach ( $template_args as $key => $arg ) {
				$key = is_numeric( $key ) ? 'var_' . $key : $key;

				set_query_var( $key, $arg );
			}
		}

		return Wprus::locate_template( $template_name, $load, $required_once );
	}
}

if ( ! function_exists( 'wprus_get_admin_template' ) ) {
	function wprus_get_admin_template( $template_name, $args = array(), $load = true, $required_once = false ) {
		$template_name = apply_filters( 'wprus_get_admin_template_name', $template_name, $args );
		$template_args = apply_filters( 'wprus_get_admin_template_args', $args, $template_name );

		if ( ! empty( $template_args ) ) {

			foreach ( $template_args as $key => $arg ) {
				$key = is_numeric( $key ) ? 'var_' . $key : $key;

				set_query_var( $key, $arg );
			}
		}

		return Wprus::locate_admin_template( $template_name, $load, $required_once );
	}
}

if ( ! function_exists( 'wprus_set_auth_cookie' ) ) {

	function wprus_set_auth_cookie( $user_id, $remember = false, $secure = '', $token = '' ) {

		if ( $remember ) {
			$expiration = time() + apply_filters( 'auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user_id, $remember );
			$expire     = $expiration + ( 12 * HOUR_IN_SECONDS );
		} else {
			$expiration = time() + apply_filters( 'auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user_id, $remember );
			$expire     = 0;
		}

		if ( '' === $secure ) {
			$secure = is_ssl();
		}

		$secure_logged_in_cookie = $secure && 'https' === wp_parse_url( get_option( 'home' ), PHP_URL_SCHEME );
		$secure                  = apply_filters( 'secure_auth_cookie', $secure, $user_id );
		$secure_logged_in_cookie = apply_filters( 'secure_logged_in_cookie', $secure_logged_in_cookie, $user_id, $secure );

		if ( $secure ) {
			$auth_cookie_name = SECURE_AUTH_COOKIE;
			$scheme           = 'secure_auth';
		} else {
			$auth_cookie_name = AUTH_COOKIE;
			$scheme           = 'auth';
		}

		if ( '' === $token ) {
			$manager = WP_Session_Tokens::get_instance( $user_id );
			$token   = $manager->create( $expiration );
		}

		$auth_cookie      = wp_generate_auth_cookie( $user_id, $expiration, $scheme, $token );
		$logged_in_cookie = wp_generate_auth_cookie( $user_id, $expiration, 'logged_in', $token );

		do_action( 'set_auth_cookie', $auth_cookie, $expire, $expiration, $user_id, $scheme, $token );
		do_action( 'set_logged_in_cookie', $logged_in_cookie, $expire, $expiration, $user_id, 'logged_in', $token );

		if ( ! apply_filters( 'send_auth_cookies', true ) ) {

			return;
		}

		if ( PHP_VERSION_ID < 70300 ) {
			setcookie(
				$auth_cookie_name,
				$auth_cookie,
				$expire,
				PLUGINS_COOKIE_PATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);
			setcookie(
				$auth_cookie_name,
				$auth_cookie,
				$expire,
				ADMIN_COOKIE_PATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);
			setcookie(
				LOGGED_IN_COOKIE,
				$logged_in_cookie,
				$expire,
				COOKIEPATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);

			if ( COOKIEPATH !== SITECOOKIEPATH ) {
				setcookie( LOGGED_IN_COOKIE, $logged_in_cookie, $expire, SITECOOKIEPATH . '; SameSite=None', COOKIE_DOMAIN, true, false );
			}
		} else {
			setcookie(
				$auth_cookie_name,
				$auth_cookie,
				array(
					'expires'  => $expire,
					'path'     => PLUGINS_COOKIE_PATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);
			setcookie(
				$auth_cookie_name,
				$auth_cookie,
				array(
					'expires'  => $expire,
					'path'     => ADMIN_COOKIE_PATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);
			setcookie(
				LOGGED_IN_COOKIE,
				$logged_in_cookie,
				array(
					'expires'  => $expire,
					'path'     => COOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);

			if ( COOKIEPATH !== SITECOOKIEPATH ) {

				setcookie(
					LOGGED_IN_COOKIE,
					$logged_in_cookie,
					array(
						'expires'  => $expire,
						'path'     => SITECOOKIEPATH,
						'domain'   => COOKIE_DOMAIN,
						'secure'   => true,
						'httponly' => false,
						'samesite' => 'None',
					)
				);
			}
		}
	}
}

if ( ! function_exists( 'wprus_clear_auth_cookie' ) ) {

	function wprus_clear_auth_cookie() {
		do_action( 'clear_auth_cookie' );

		if ( ! apply_filters( 'send_auth_cookies', true ) ) {
			return;
		}

		if ( PHP_VERSION_ID < 70300 ) {
			setcookie(
				AUTH_COOKIE,
				' ',
				time() - YEAR_IN_SECONDS,
				ADMIN_COOKIE_PATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);
			setcookie(
				SECURE_AUTH_COOKIE,
				' ',
				time() - YEAR_IN_SECONDS,
				ADMIN_COOKIE_PATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);
			setcookie(
				AUTH_COOKIE,
				' ',
				time() - YEAR_IN_SECONDS,
				PLUGINS_COOKIE_PATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);
			setcookie(
				SECURE_AUTH_COOKIE,
				' ',
				time() - YEAR_IN_SECONDS,
				PLUGINS_COOKIE_PATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);
			setcookie(
				LOGGED_IN_COOKIE,
				' ',
				time() - YEAR_IN_SECONDS,
				COOKIEPATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);
			setcookie(
				LOGGED_IN_COOKIE,
				' ',
				time() - YEAR_IN_SECONDS,
				SITECOOKIEPATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);
			setcookie(
				'wp-settings-' . get_current_user_id(),
				' ',
				time() - YEAR_IN_SECONDS,
				SITECOOKIEPATH . '; SameSite=None',
				'',
				true,
				false
			);
			setcookie(
				'wp-settings-time-' . get_current_user_id(),
				' ',
				time() - YEAR_IN_SECONDS,
				SITECOOKIEPATH . '; SameSite=None',
				'',
				true,
				false
			);
			setcookie(
				AUTH_COOKIE,
				' ',
				time() - YEAR_IN_SECONDS,
				COOKIEPATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);
			setcookie(
				AUTH_COOKIE,
				' ',
				time() - YEAR_IN_SECONDS,
				SITECOOKIEPATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);
			setcookie(
				SECURE_AUTH_COOKIE,
				' ',
				time() - YEAR_IN_SECONDS,
				COOKIEPATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);
			setcookie(
				SECURE_AUTH_COOKIE,
				' ',
				time() - YEAR_IN_SECONDS,
				SITECOOKIEPATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);
			setcookie(
				USER_COOKIE,
				' ',
				time() - YEAR_IN_SECONDS,
				COOKIEPATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);
			setcookie(
				PASS_COOKIE,
				' ',
				time() - YEAR_IN_SECONDS,
				COOKIEPATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);
			setcookie(
				USER_COOKIE,
				' ',
				time() - YEAR_IN_SECONDS,
				SITECOOKIEPATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);
			setcookie(
				PASS_COOKIE,
				' ',
				time() - YEAR_IN_SECONDS,
				SITECOOKIEPATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);
			setcookie(
				'wp-postpass_' . COOKIEHASH,
				' ',
				time() - YEAR_IN_SECONDS,
				COOKIEPATH . '; SameSite=None',
				COOKIE_DOMAIN,
				true,
				false
			);
		} else {
			setcookie(
				AUTH_COOKIE,
				' ',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => ADMIN_COOKIE_PATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);
			setcookie(
				SECURE_AUTH_COOKIE,
				' ',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => ADMIN_COOKIE_PATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);
			setcookie(
				AUTH_COOKIE,
				' ',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => PLUGINS_COOKIE_PATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);
			setcookie(
				SECURE_AUTH_COOKIE,
				' ',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => PLUGINS_COOKIE_PATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);
			setcookie(
				LOGGED_IN_COOKIE,
				' ',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => COOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);
			setcookie(
				LOGGED_IN_COOKIE,
				' ',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => SITECOOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);

			setcookie(
				'wp-settings-' . get_current_user_id(),
				' ',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => SITECOOKIEPATH,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);
			setcookie(
				'wp-settings-time-' . get_current_user_id(),
				' ',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => SITECOOKIEPATH,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);

			setcookie(
				AUTH_COOKIE,
				' ',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => COOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);
			setcookie(
				AUTH_COOKIE,
				' ',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => SITECOOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);
			setcookie(
				SECURE_AUTH_COOKIE,
				' ',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => COOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);
			setcookie(
				SECURE_AUTH_COOKIE,
				' ',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => SITECOOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);

			setcookie(
				USER_COOKIE,
				' ',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => COOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);
			setcookie(
				PASS_COOKIE,
				' ',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => COOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);
			setcookie(
				USER_COOKIE,
				' ',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => SITECOOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);
			setcookie(
				PASS_COOKIE,
				' ',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => SITECOOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);

			setcookie(
				'wp-postpass_' . COOKIEHASH,
				' ',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => COOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => true,
					'httponly' => false,
					'samesite' => 'None',
				)
			);
		}
	}
}
