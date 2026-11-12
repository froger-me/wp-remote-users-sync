<?php
/**
 * Check user identities across connected sites before creating or changing accounts.
 *
 * @package Wprus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Handle remote identity checks and local registration validation. */
class Wprus_Api_Identity extends Wprus_Api_Abstract {
	/**
	 * Approved username and email pairs for this request.
	 *
	 * @var array
	 */
	private static $approved_creations = array();

	/**
	 * User IDs whose outgoing Create action must be skipped.
	 *
	 * @var array
	 */
	private static $blocked_creations = array();

	/**
	 * Original email for each user updated in this request.
	 *
	 * @var array
	 */
	private static $previous_emails = array();

	/**
	 * Exception handler installed before this class.
	 *
	 * @var callable|null
	 */
	private static $previous_exception_handler;

	/**
	 * Whether an authenticated incoming action is being handled.
	 *
	 * @var bool
	 */
	private static $handling_incoming = false;

	/**
	 * Set up identity checks that apply to both local and remote requests.
	 *
	 * @param string         $endpoint The endpoint name.
	 * @param Wprus_Settings $settings The connected-site settings.
	 * @param bool           $init_hooks Whether to register WordPress hooks.
	 */
	public function __construct( $endpoint, $settings, $init_hooks = false ) {
		parent::__construct( $endpoint, $settings, $init_hooks );

		if ( $init_hooks ) {
			add_filter( 'wprus_action_data', array( $this, 'attach_identity_email' ), PHP_INT_MAX, 3 );
			self::$previous_exception_handler = set_exception_handler( array( self::class, 'handle_uncaught_exception' ) );
		}
	}

	/** Register validation for local WordPress registrations. */
	public function init_notification_hooks() {
		add_filter( 'registration_errors', array( $this, 'validate_registration' ), 10, 3 );
		add_action( 'user_profile_update_errors', array( $this, 'validate_admin_registration' ), 10, 3 );
		add_filter( 'rest_pre_insert_user', array( $this, 'validate_rest_registration' ), 10, 2 );
		add_filter( 'wp_pre_insert_user_data', array( $this, 'require_validated_creation' ), PHP_INT_MAX, 4 );
		add_action( 'user_register', array( $this, 'check_created_user' ), PHP_INT_MIN + 100, 1 );
		add_action( 'profile_update', array( $this, 'remember_previous_email' ), PHP_INT_MIN + 100, 2 );
	}

	/** Answer an authenticated request with only username and email existence. */
	public function handle_notification() {
		$data = $this->get_data_post();

		if (
			! is_array( $data ) ||
			! isset( $data['username'], $data['user_email'], $data['base_url'] ) ||
			! is_string( $data['username'] ) ||
			'' === $data['username'] ||
			! is_string( $data['user_email'] ) ||
			! is_email( $data['user_email'] ) ||
			! is_string( $data['base_url'] ) ||
			! $this->settings->get_site( $data['base_url'] )
		) {
			wp_send_json_error( array( 'code' => 'invalid_identity_request' ), 400 );
		}

		wp_send_json_success(
			array(
				'username_exists' => (bool) username_exists( $data['username'] ),
				'email_exists'    => (bool) email_exists( $data['user_email'] ),
			)
		);
	}

	/**
	 * Check one connected site, rejecting unreliable replies.
	 *
	 * @param array  $site Connected-site settings.
	 * @param string $username Username to check.
	 * @param string $email Email address to check.
	 * @return array|WP_Error Existence results or an error.
	 */
	public function check_remote_site( $site, $username, $email ) {
		if ( ! is_array( $site ) || empty( $site['url'] ) ) {
			return new WP_Error( 'wprus_identity_unavailable' );
		}

		$token_info = $this->get_remote_token(
			array(
				'action'   => 'identity',
				'method'   => 'post',
				'base_url' => $site['url'],
			)
		);

		if ( ! $token_info || empty( $token_info['nonce'] ) ) {
			return new WP_Error( 'wprus_identity_unavailable' );
		}

		$response = wp_safe_remote_post(
			trailingslashit( $site['url'] ) . 'wprus/identity/',
			array(
				'timeout' => 5,
				'body'    => array(
					'wprusdata' => $this->encrypt_data(
						array(
							'username'   => $username,
							'user_email' => $email,
							'base_url'   => home_url(),
						)
					),
					'token'     => $token_info['nonce'],
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'wprus_identity_unavailable' );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if (
			! is_array( $body ) ||
			! isset( $body['success'], $body['data'] ) ||
			true !== $body['success'] ||
			! is_array( $body['data'] ) ||
			! isset( $body['data']['username_exists'], $body['data']['email_exists'] ) ||
			! is_bool( $body['data']['username_exists'] ) ||
			! is_bool( $body['data']['email_exists'] )
		) {
			return new WP_Error( 'wprus_identity_unavailable' );
		}

		return $body['data'];
	}

	/**
	 * Check every connected site, regardless of its action settings.
	 *
	 * @param string $username Username to check.
	 * @param string $email Email address to check.
	 * @return true|WP_Error True when clear everywhere, or an error.
	 */
	public function check_remote_identity( $username, $email ) {
		foreach ( $this->settings->get_sites() as $site ) {
			$result = $this->check_remote_site( $site, $username, $email );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( $result['username_exists'] ) {
				return new WP_Error( 'wprus_username_exists' );
			}
			if ( $result['email_exists'] ) {
				return new WP_Error( 'wprus_email_exists' );
			}
		}

		return true;
	}

	/** Whether the current user can manage other users. */
	public static function can_manage_users() {
		foreach ( array( 'manage_network_users', 'promote_users', 'remove_users', 'edit_users', 'add_users', 'create_users', 'delete_users' ) as $capability ) {
			if ( current_user_can( $capability ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate a registration through WordPress's normal error handling.
	 *
	 * @param WP_Error $errors Existing registration errors.
	 * @param string   $username Username being registered.
	 * @param string   $email Email address being registered.
	 * @return WP_Error Registration errors.
	 */
	public function validate_registration( $errors, $username, $email ) {
		if ( $errors->has_errors() || self::can_manage_users() ) {
			return $errors;
		}

		$result = $this->check_remote_identity( $username, $email );

		if ( is_wp_error( $result ) ) {
			$errors->add( $result->get_error_code(), $this->get_public_error_message( $result ) );
		} else {
			$this->approve_creation( $username, $email );
		}

		return $errors;
	}

	/**
	 * Show a clear error without exposing connected-site details.
	 *
	 * @param WP_Error $error Error returned by a connected-site check.
	 * @return string Message suitable for a registration form.
	 */
	public function get_public_error_message( $error ) {
		switch ( $error->get_error_code() ) {
			case 'wprus_username_exists':
				return __( 'This username is already in use. Please choose another.', 'wprus' );
			case 'wprus_email_exists':
				return __( 'This email address is already in use.', 'wprus' );
			default:
				return __( 'We could not complete your registration right now. Please try again later.', 'wprus' );
		}
	}

	/**
	 * Validate a user created from the WordPress administration screen.
	 *
	 * @param WP_Error $errors Existing registration errors.
	 * @param bool     $update Whether this is an existing account.
	 * @param WP_User  $user Proposed user data.
	 */
	public function validate_admin_registration( $errors, $update, $user ) {
		if ( ! $update ) {
			$this->validate_registration( $errors, $user->user_login, $user->user_email );
		}
	}

	/**
	 * Validate a user created through the WordPress REST API.
	 *
	 * @param WP_User|WP_Error $prepared_user Proposed user or earlier error.
	 * @param WP_REST_Request  $request The REST request.
	 * @return WP_User|WP_Error Proposed user or registration error.
	 */
	public function validate_rest_registration( $prepared_user, $request ) {
		if ( ! is_wp_error( $prepared_user ) && ! $request->get_param( 'id' ) ) {
			$errors = $this->validate_registration( new WP_Error(), $prepared_user->user_login, $prepared_user->user_email );
			if ( $errors->has_errors() ) {
				return $errors;
			}
		}

		return $prepared_user;
	}

	/**
	 * Approve a pair for exactly one later insertion in this request.
	 *
	 * @param string $username Approved username.
	 * @param string $email Approved email address.
	 */
	public function approve_creation( $username, $email ) {
		$key                              = $this->creation_key( $username, $email );
		self::$approved_creations[ $key ] = ( self::$approved_creations[ $key ] ?? 0 ) + 1;
	}

	/**
	 * Require validation before any local unprivileged user is inserted.
	 *
	 * @param array $data WordPress user fields after sanitizing.
	 * @param bool  $update Whether this is an existing account.
	 * @param int   $user_id Existing user ID, when updating.
	 * @param array $userdata Original user fields supplied to WordPress.
	 * @return array User fields when creation is allowed.
	 * @throws Wprus_Identity_Creation_Exception When a registration method has no validation.
	 */
	public function require_validated_creation( $data, $update, $user_id, $userdata ) {
		if ( $update || self::$handling_incoming || self::can_manage_users() ) {
			return $data;
		}

		$username = isset( $data['user_login'] ) ? $data['user_login'] : '';
		$email    = isset( $data['user_email'] ) ? $data['user_email'] : '';
		$key      = $this->creation_key( $username, $email );

		if ( ! empty( self::$approved_creations[ $key ] ) ) {
			--self::$approved_creations[ $key ];
			return $data;
		}

		if ( has_action( 'wprus_validate_user_creation' ) ) {
			$result = $this->check_remote_identity( $username, $email );
			do_action( 'wprus_validate_user_creation', $username, $email, $data, $result );
			return $data;
		}

		$message = sprintf( 'WPRUS rejected an unvalidated user creation for "%s".', $username );
		$this->log_creation_failure( $message );
		throw new Wprus_Identity_Creation_Exception( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Check again after insertion and skip Create if the result is uncertain.
	 *
	 * @param int $user_id Newly created user's ID.
	 */
	public function check_created_user( $user_id ) {
		if ( self::$handling_incoming || self::can_manage_users() ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$result = $this->check_remote_identity( $user->user_login, $user->user_email );
		if ( is_wp_error( $result ) ) {
			self::$blocked_creations[ $user_id ] = true;
			Wprus_Logger::log(
				sprintf( 'Outgoing Create action for user %d was skipped: %s.', $user_id, $result->get_error_code() ),
				'alert',
				'db_log'
			);
		}
	}

	/**
	 * Whether the current request blocked this user's outgoing Create action.
	 *
	 * @param int $user_id User ID to check.
	 * @return bool Whether Create should be skipped.
	 */
	public static function is_creation_blocked( $user_id ) {
		return ! empty( self::$blocked_creations[ $user_id ] );
	}

	/**
	 * Keep the email from before the first profile update in this request.
	 *
	 * @param int     $user_id Updated user's ID.
	 * @param WP_User $old_user_data User data before the update.
	 */
	public function remember_previous_email( $user_id, $old_user_data ) {
		if ( $old_user_data instanceof WP_User && ! isset( self::$previous_emails[ $user_id ] ) ) {
			self::$previous_emails[ $user_id ] = $old_user_data->user_email;
		}
	}

	/**
	 * Add the sender's identifying email to outgoing action data.
	 *
	 * @param array  $data Outgoing action data.
	 * @param string $endpoint Action name.
	 * @param string $url Connected-site URL.
	 * @return array Action data with the source email.
	 */
	public function attach_identity_email( $data, $endpoint, $url ) {
		return self::attach_source_identity( $data, $endpoint );
	}

	/**
	 * Preserve the sender's identity when an action is queued.
	 *
	 * @param array  $data Outgoing action data.
	 * @param string $endpoint Action name.
	 * @return array Action data with the source email.
	 */
	public static function attach_source_identity( $data, $endpoint ) {
		if ( ! is_array( $data ) || ! isset( $data['username'] ) || 'identity' === $endpoint || isset( $data['identity_email'] ) ) {
			return $data;
		}

		$user = get_user_by( 'login', $data['username'] );
		if ( ! $user ) {
			return $data;
		}

		$data['identity_email'] = 'update' === $endpoint && isset( self::$previous_emails[ $user->ID ] )
			? self::$previous_emails[ $user->ID ]
			: $user->user_email;

		return $data;
	}

	/**
	 * Reject operations against an existing account with another identity.
	 *
	 * @param array  $data Incoming action data.
	 * @param string $endpoint Action name.
	 * @return bool Whether the account identity matches or does not exist locally.
	 */
	public static function verify_incoming_identity( $data, $endpoint ) {
		if ( ! is_array( $data ) || ! isset( $data['username'] ) || ! is_string( $data['username'] ) || '' === $data['username'] ) {
			return false;
		}

		$user = get_user_by( 'login', $data['username'] );
		if ( ! $user ) {
			return true;
		}

		return isset( $data['identity_email'] ) &&
			is_string( $data['identity_email'] ) &&
			'' !== $data['identity_email'] &&
			$user->user_login === $data['username'] &&
			0 === strcasecmp( $user->user_email, $data['identity_email'] );
	}

	/**
	 * Mark the authenticated incoming action while its handler runs.
	 *
	 * @param bool $handling Whether an incoming action is being handled.
	 */
	public static function set_handling_incoming( $handling ) {
		self::$handling_incoming = (bool) $handling;
	}

	/**
	 * Display a normal WordPress error page for uncaught creation failures.
	 *
	 * @param Throwable $exception Uncaught exception.
	 * @throws Throwable When no earlier exception handler exists.
	 */
	public static function handle_uncaught_exception( Throwable $exception ) {
		if ( $exception instanceof Wprus_Identity_Creation_Exception ) {
			wp_die(
				esc_html__( 'We could not create your account right now. Please try again later or contact the site administrator.', 'wprus' ),
				esc_html__( 'Account not created', 'wprus' ),
				array( 'response' => 409 )
			);
			return;
		}

		if ( is_callable( self::$previous_exception_handler ) ) {
			call_user_func( self::$previous_exception_handler, $exception );
			return;
		}

		throw $exception;
	}

	/**
	 * Use the values after WordPress has sanitized them.
	 *
	 * @param string $username Username supplied to WordPress.
	 * @param string $email Email address supplied to WordPress.
	 * @return string Key for the approved username and email.
	 */
	private function creation_key( $username, $email ) {
		return wp_json_encode( array( sanitize_user( (string) $username, true ), sanitize_email( (string) $email ) ) );
	}

	/**
	 * Log an unsupported creation even when its caller catches the exception.
	 *
	 * @param string $message Technical reason for the rejection.
	 */
	private function log_creation_failure( $message ) {
		error_log( '[WPRUS] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Required record of a rejected creation.
		Wprus_Logger::log( $message, 'alert', 'db_log' );
	}
}
