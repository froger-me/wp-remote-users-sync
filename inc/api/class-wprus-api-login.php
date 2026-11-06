<?php
/**
 * Send and receive user login actions.
 *
 * @package Wprus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/** Handle the Login action for connected sites. */
class Wprus_Api_Login extends Wprus_Api_Abstract {

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	/**
	 * Register the hook that sends local logins.
	 *
	 * @return void No value is returned.
	 */
	public function init_notification_hooks() {
		add_action( 'set_logged_in_cookie', array( $this, 'notify_remote' ), PHP_INT_MIN + 10, 6 );
	}

	/**
	 * State whether login actions can run after the page loads.
	 *
	 * @return bool Always true for the Login action.
	 */
	public function has_async_actions() {

		return true;
	}

	/**
	 * Register and run login actions during a silent redirect.
	 *
	 * @return void No value is returned.
	 */
	public function init_silent_async_redirect_hooks() {

		if ( ! has_action( 'set_logged_in_cookie', array( $this, 'fire_async_actions' ) ) ) {
			add_action( 'set_logged_in_cookie', array( $this, 'notify_remote' ), PHP_INT_MIN + 10, 6 );
		}

		$this->fire_redirect_async_actions();
	}

	/**
	 * Run pending login actions when WordPress sets a login cookie.
	 *
	 * @param string|null $logged_in_cookie Login cookie value.
	 * @param int|null    $expire           Cookie expiration time.
	 * @param int|null    $expiration       Session expiration time.
	 * @param int|null    $user_id          User ID.
	 * @param string|null $scheme           Login scheme.
	 * @param string|null $token            Session token.
	 * @return void No value is returned.
	 */
	public function fire_redirect_async_actions(
		$logged_in_cookie = null,
		$expire = null,
		$expiration = null,
		$user_id = null,
		$scheme = null,
		$token = null
	) {
		$this->fire_async_actions();
	}

	/**
	 * Check whether this browser needs a redirect for login synchronization.
	 *
	 * @return bool Whether to redirect.
	 */
	public function needs_redirect() {
		global $is_safari;

		return (
			(
				self::$browser_support_settings['force_login_logout_strict'] ||
				$is_safari ||
				(
					isset( $_SERVER['HTTP_USER_AGENT'] ) &&
					1 === preg_match( '/iPhone|iPad/', sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) )
				)
			) &&
			! self::$browser_support_settings['force_disable_login_logout_strict']
		);
	}

	/**
	 * Check whether login redirects should avoid visible page output.
	 *
	 * @return bool Whether to use a silent redirect.
	 */
	public function is_silent_async_action_redirect() {
		return self::$browser_support_settings['silent_login_logout_strict'];
	}

	/**
	 * Receive a login action allowed by this site's settings.
	 *
	 * @return bool Whether the login succeeded.
	 */
	public function handle_notification() {
		$result = false;
		$data   = $this->get_data_get();

		if ( ! $this->validate( $data ) ) {
			Wprus_Logger::log(
				array(
					'message' => __( 'Login action failed - received invalid data.', 'wprus' ),
					'data'    => $data,
				),
				'alert',
				'db_log'
			);

			return false;
		}

		$data = $this->sanitize( $data );
		$site = $this->get_active_site_for_action( $this->endpoint, $data['base_url'] );

		if ( $site ) {
			$user = get_user_by( 'login', $data['username'] );

			if ( $user ) {
				$result = true;

				wp_set_current_user( $user->ID, $data['username'] );
				wprus_set_auth_cookie( $user->ID, $data['remember'] );
				do_action( 'wp_login', $data['username'], $user );
				Wprus_Logger::log(
					sprintf(
						// translators: %1$s is the username, %2$s is the caller.
						__( 'Login action - successfully logged in user "%1$s" from %2$s.', 'wprus' ),
						$data['username'],
						$site['url']
					),
					'success',
					'db_log'
				);
			} else {
				Wprus_Logger::log(
					sprintf(
						// translators: %1$s is the username, %2$s is the caller.
						__( 'Login action aborted - user "%1$s" from %2$s does not exist locally.', 'wprus' ),
						$data['username'],
						$site['url']
					),
					'warning',
					'db_log'
				);
			}
		} elseif ( ! $site ) {
			Wprus_Logger::log(
				sprintf(
					// translators: %s is the url of the caller.
					__( 'Login action failed - incoming login action not enabled for %s', 'wprus' ),
					$data['base_url']
				),
				'alert',
				'db_log'
			);
		}

		return $result;
	}

	/**
	 * Queue a local login for connected sites.
	 *
	 * @param string $logged_in_cookie Login cookie value.
	 * @param int    $expire           Cookie expiration time.
	 * @param int    $expiration       Session expiration time.
	 * @param int    $user_id          User ID.
	 * @param string $scheme           Login scheme.
	 * @param string $token            Session token.
	 * @return void No value is returned.
	 */
	public function notify_remote( $logged_in_cookie, $expire, $expiration, $user_id, $scheme, $token ) {
		$user                = get_user_by( 'ID', $user_id );
		$this->async_user_id = $user->ID;
		$sites               = $this->settings->get_sites( $this->endpoint, 'outgoing' );

		if ( ! empty( $sites ) ) {
			$remember = filter_input( INPUT_POST, 'rememberme', FILTER_UNSAFE_RAW );

			Wprus_Logger::log(
				sprintf(
					// translators: %s is the username.
					__( 'Login action - enqueueing asynchronous actions for username "%s"', 'wprus' ),
					$user->user_login
				),
				'info',
				'db_log'
			);

			foreach ( $sites as $index => $site ) {
				$this->add_async_action(
					$site['url'],
					array(
						'username' => $user->user_login,
						'remember' => ( $remember ) ? 1 : 0,
					)
				);
			}
		}
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	/**
	 * Check required fields for an incoming login action.
	 *
	 * @param array $data Received request data.
	 * @return bool Whether the login request is valid.
	 */
	protected function validate( $data ) {
		$valid =
			parent::validate( $data ) &&
			username_exists( $data['username'] );

		if ( $this->needs_redirect() ) {
			$valid = $valid && isset( $data['callback_url'] ) && filter_var( $data['callback_url'], FILTER_VALIDATE_URL );
		}

		return $valid;
	}

	/**
	 * Convert the incoming remember flag to a boolean value.
	 *
	 * @param array $data Received request data.
	 * @return array Request data with a boolean remember flag.
	 */
	protected function sanitize( $data ) {
		$remember         = $data['remember'] ?? false;
		$data['remember'] = is_string( $remember ) ? '' !== $remember : ( is_scalar( $remember ) && (bool) $remember );

		return $data;
	}

	/**
	 * Preserve WordPress's requested login destination during synchronization.
	 *
	 * @param string|false $ajax_fallback Destination for an asynchronous request.
	 * @return string Login destination URL.
	 */
	protected function get_redirect_url( $ajax_fallback = false ) {
		$parts = wp_parse_url( home_url() );
		$url   = $parts['scheme'] . '://' . $parts['host'] . add_query_arg( null, null );

		if ( false !== strpos( $url, 'admin-ajax.php' ) ) {
			$ajax_fallback = ( $ajax_fallback ) ? $ajax_fallback : home_url();
			$url           = apply_filters( 'wprus_get_redirect_url_ajax', $ajax_fallback, $this->endpoint );
		}

		$requested_redirect_to = isset( $_REQUEST['redirect_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( false !== strpos( $url, 'wp-login.php' ) ) {

			if ( isset( $_REQUEST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$redirect_to   = $requested_redirect_to;
				$secure_cookie = is_ssl();

				if ( $secure_cookie && false !== strpos( $redirect_to, 'wp-admin' ) ) {
					$redirect_to = preg_replace( '|^http://|', 'https://', $redirect_to );
				}
			} else {
				$redirect_to = admin_url();
			}

			$url = apply_filters( 'login_redirect', $redirect_to, $requested_redirect_to, wp_get_current_user() );
		}

		return $url;
	}
}
