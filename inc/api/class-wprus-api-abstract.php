<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class Wprus_Api_Abstract {
	const TOKEN_EXPIRY_BUFFER = MINUTE_IN_SECONDS / 2;

	/**
	 * The encryption settings.
	 *
	 * @var array
	 */
	protected static $encryption_settings;
	/**
	 * The browser support settings.
	 *
	 * @var array
	 */
	protected static $browser_support_settings;
	/**
	 * The whitelist of IP ranges.
	 *
	 * @var array
	 */
	protected static $ip_whitelist;
	/**
	 * The settings class.
	 *
	 * @var string
	 */
	protected static $settings_class;
	/**
	 * Whether the token can be requested - used to retry if failed first.
	 *
	 * @var bool
	 */
	protected static $can_request_token = true;

	/**
	 * The settings object.
	 *
	 * @var Wprus_Settings|mixed
	 */
	protected $settings;
	/**
	 * The endpoint method - 'post' or 'get'.
	 *
	 * @var string
	 */
	protected $method;
	/**
	 * The endpoint key.
	 *
	 * @var string
	 */
	protected $endpoint;
	/**
	 * The data received from remote sites.
	 *
	 * @var array
	 */
	protected $data;
	/**
	 * The user ID used to save and fire async actions.
	 *
	 * @var int
	 */
	protected $async_user_id;
	/**
	 * Whether the current request is received from a website.
	 *
	 * @var bool
	 */
	protected $doing_remote_action = false;
	/**
	 * The list of async actions to add to the footer.
	 *
	 * @var array
	 */
	protected $async_actions;
	/**
	 * The role handler object.
	 *
	 * @var Wprus_Api_Role|mixed
	 */
	protected $role_handler;
	/**
	 * The password handler object.
	 *
	 * @var Wprus_Api_Password|mixed
	 */
	protected $password_handler;
	/**
	 * Whether the current action is authorized
	 *
	 * @var bool
	 */
	protected $is_authorized_remote;


	/**
	 * Constructor
	 *
	 * @param string $endpoint The endpoint key of the current instance.
	 * @param Wprus_Settings|mixed $settings The settings object.
	 * @param bool $init_hooks Whether to add WordPress action and filter hooks on object creation ; default `false`.
	 */
	public function __construct( $endpoint, $settings, $init_hooks = false ) {
		$this->endpoint            = $endpoint;
		$this->settings            = $settings;
		$this->doing_remote_action = self::is_doing_remote_action();
		$this->async_actions       = array();

		$this->init();
		$this->init_data();

		if ( $init_hooks ) {

			if ( $this->doing_remote_action ) {
				add_action( 'init', array( $this, 'init_remote_hooks_authorization' ), PHP_INT_MIN + 100, 0 );
				add_action( 'init', array( $this, 'init_remote_hooks' ), PHP_INT_MIN + 100, 0 );
			} else {
				add_action( 'init', array( $this, 'init_local_hooks' ), PHP_INT_MIN + 100, 0 );
				add_action(
					'wp_ajax_wprus_' . $endpoint . '_notify_ping_remote',
					array( $this, 'notify_ping_remote' ),
					10,
					0
				);

				if ( $this->has_async_actions() ) {

					if ( ! has_action( 'init', array( $this, 'set_pending_async_actions_user_id' ) ) ) {
						add_action( 'init', array( $this, 'set_pending_async_actions_user_id' ), PHP_INT_MIN + 100, 0 );
					}

					add_action( 'init', array( $this, 'init_async_hooks' ), PHP_INT_MIN + 100, 0 );
				}
			}

			add_filter( 'wprus_wp_endpoints', array( $this, 'add_action_endpoints' ), 10, 1 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	/**
	 * Determine whether the current request is received from a remote website.
	 *
	 * @return bool whether the current request comes from a remote site
	 */
	public static function is_doing_remote_action() {
		return strpos( $_SERVER['REQUEST_URI'], '/wprus/' ) !== false;
	}

	/**
	 * Encrypt data to be sent to remote sites
	 *
	 * @param mixed $data The data to encrypt.
	 * @return string the encrypted bundle
	 */
	public function encrypt_data( $data ) {
		$bundle = Wprus_Crypto::encrypt(
			wp_json_encode( $data, JSON_UNESCAPED_UNICODE ),
			self::$encryption_settings['aes_key'],
			self::$encryption_settings['hmac_key']
		);

		return $bundle;
	}

	/**
	 * Decrypt data received from remote sites
	 *
	 * @param string $bundle The bundle to decrypt.
	 * @return mixed The decrypted data
	 */
	public function decrypt_data( $bundle ) {
		$data    = false;
		$payload = false;

		try {
			$payload = Wprus_Crypto::decrypt(
				$bundle,
				self::$encryption_settings['aes_key'],
				self::$encryption_settings['hmac_key']
			);
		} catch ( Exception $e ) {
			Wprus_Logger::log( 'Could not decrypt data: ' . $e->getMessage(), 'alert', 'db_log' );
		}

		if ( $payload ) {
			$data = json_decode( $payload, true );
		} else {
			Wprus_Logger::log( __( 'Could not decrypt data: invalid bundle.', 'wprus' ) );
		}

		return $data;
	}

	/**
	 * Initialise the current API endpoint with its configuration
	 *
	 */
	public function init() {
		self::$settings_class = get_class( $this->settings );

		if ( ! isset( self::$encryption_settings ) ) {
			self::$encryption_settings = self::$settings_class::get_option( 'encryption' );
		}

		if ( ! isset( self::$browser_support_settings ) ) {
			self::$browser_support_settings = self::$settings_class::get_option( 'browser_support' );
		}

		if ( ! isset( self::$ip_whitelist ) ) {
			$ip_whitelist = self::$settings_class::get_option( 'ip_whitelist' );

			if ( ! empty( $ip_whitelist ) ) {
				$ip_whitelist       = array_filter( array_map( 'trim', explode( "\n", $ip_whitelist ) ) );
				self::$ip_whitelist = array_map(
					function ( $ip ) {

						return preg_match( '/\//', $ip ) ? $ip : $ip . '/32';
					},
					$ip_whitelist
				);
			} else {
				self::$ip_whitelist = false;
			}
		}
	}

	/**
	 * Handle notifications from remote websites when an action is triggered
	 *
	 * @return $result whether the operation was successful
	 */
	public function handle_notification() {
		return null;
	}

	/**
	 * Initialize the hooks to trigger when the action is triggered
	 */
	public function init_notification_hooks() { }

	/**
	 * Handle request for security tokens and reply with JSON data
	 *
	 */
	public function handle_token_request() {
		$data       = $this->get_data_post();
		$token_info = false;

		if ( $data && isset( $data['method'] ) ) {
			$token_info = Wprus_Nonce::create_nonce( true );
		}

		if ( $token_info ) {
			$action_label   = isset( $data['action'] ) ? $data['action'] : __( 'Token', 'wprus' );
			$settings_class = self::$settings_class;
			$class_vars     = get_class_vars( $settings_class );

			if ( isset( $class_vars['actions'][ $action_label ] ) ) {
				$action_label = $class_vars['actions'][ $action_label ];
			}

			Wprus_Logger::log(
				// translators: %1$s is the remote site ; %2$s is the action
				sprintf( __( 'Token created: site %1$s - action "%2$s"', 'wprus' ), $data['base_url'], $action_label ),
				'info',
				'db_log'
			);
			wp_send_json( $token_info );
		}

		Wprus_Logger::log( __( 'Failed to create token - invalid payload', 'wprus' ), 'alert', 'db_log' );

		exit();
	}

	/**
	 * Inititialise WordPress action hooks to process remote sites requests
	 *
	 */
	public function init_remote_hooks() {
		$data = $this->get_data();

		if ( isset( $data['ping'] ) ) {
			add_action( 'wprus_api_' . $this->endpoint, array( $this, 'handle_ping_notification' ), 10, 0 );
		} else {
			add_action( 'wprus_api_' . $this->endpoint, array( $this, 'handle_request' ), 10, 0 );
		}

		add_action( 'wprus_api_token', array( $this, 'handle_token_request' ), 10, 0 );
	}

	/**
	 * Inititialise WordPress action hooks to authorise remote sites requests
	 *
	 */
	public function init_remote_hooks_authorization() {
		add_action( 'wprus_api_' . $this->endpoint, array( $this, 'authorize_notification' ), 5, 0 );
	}

	/**
	 * Inititialise the endpoint's role handler object
	 *
	 */
	public function init_role_handler( $role_api ) {
		$this->role_handler = $role_api;
	}

	/**
	 * Inititialise the endpoint's password handler object
	 *
	 */
	public function init_password_handler( $password_api ) {
		$this->password_handler = $password_api;
	}

	/**
	 * Process a remote site request
	 *
	 */
	public function handle_request() {
		$data = $this->get_data();

		if ( method_exists( $this, 'handle_notification' ) ) {
			do_action( 'wprus_before_handle_action_notification', $this->endpoint, $data );

			$result = $this->handle_notification();

			do_action( 'wprus_after_handle_action_notification', $this->endpoint, $data, $result );

			if ( $this->needs_redirect() ) {
				$url = isset( $data['callback_url'] ) ? $data['callback_url'] : home_url();

				wp_redirect( $url, 303 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect

				exit();
			}
		}

		add_filter( 'wprus_api_needs_redirect', array( $this, 'needs_redirect' ), 10, 0 );
	}

	/**
	 * Authorise or deny a remote site request
	 *
	 */
	public function authorize_notification() {
		$token_filter         = FILTER_UNSAFE_RAW;
		$token                = filter_input( INPUT_GET, 'token', $token_filter );
		$token                = ( ! $token ) ? filter_input( INPUT_POST, 'token', $token_filter ) : $token;
		$is_authorized_remote = false;
		$origin               = false;
		$remote_data          = $this->get_data();

		if ( isset( $remote_data['base_url'] ) ) {
			$origin = $remote_data['base_url'];
		}

		if ( 'get' === $this->method ) {
			Wprus_Nonce::init( true, false, self::$encryption_settings['token_expiry'] );
		}

		if ( ! Wprus_Nonce::validate_nonce( $token ) ) {
			$message = __( 'Unauthorized access (invalid token)', 'wprus' );
		} else {

			if ( 'get' === $this->method ) {
				$is_authorized_remote = true;
			} elseif ( 'post' === $this->method ) {

				if ( self::$ip_whitelist ) {
					foreach ( self::$ip_whitelist as $range ) {
						if ( $this->cidr_match( $_SERVER['REMOTE_ADDR'], $range ) ) {
							$is_authorized_remote = true;
							break;
						}
					}
				} else {
					$is_authorized_remote = true;
				}
			}

			$is_authorized_remote = apply_filters(
				'wprus_is_authorized_remote',
				$is_authorized_remote,
				$this->method,
				$_SERVER['REMOTE_ADDR'],
				self::$ip_whitelist
			);

			if ( $is_authorized_remote ) {
				$message = __( 'Access granted', 'wprus' );
			} elseif ( 'post' === $this->method && self::$ip_whitelist ) {
				// translators: %s is the remote IP address
				$message = sprintf( __( 'Unauthorized access (invalid remote IP address %s)', 'wprus' ), $_SERVER['REMOTE_ADDR'] );
			} else {
				$message = __( 'Unauthorized access (invalid method)', 'wprus' );
			}
		}

		if ( 'get' === $this->method ) {
			Wprus_Nonce::init( false, false, self::$encryption_settings['token_expiry'] );
		}

		$message                   .= ( $origin ) ? ' - ' . $origin : '';
		$log_type                   = ( $is_authorized_remote ) ? 'success' : 'alert';
		$this->is_authorized_remote = $is_authorized_remote;

		Wprus_Logger::log( $message, $log_type, 'db_log' );

		if ( ! $is_authorized_remote ) {
			do_action( 'wprus_unauthorized_access', $this->endpoint, $remote_data, $token, $this );

			if ( isset( $remote_data['ping'] ) ) {
				$message = __( 'The remote website encountered the following error: ', 'wprus' ) . $message;

				wp_send_json_error( $message );
			} elseif ( $this->needs_redirect() ) {
				$url = isset( $remote_data['callback_url'] ) ? $remote_data['callback_url'] : home_url();

				wp_redirect( $url, 303 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect

				exit();
			} else {

				exit();
			}
		}

		do_action( 'wprus_authorized_access', $this->endpoint, $remote_data, $token, $this );
	}

	/**
	 * Inititialise WordPress action hooks to send requests to remote sites
	 *
	 */
	public function init_local_hooks() {
		$init_notification_hooks = apply_filters( 'wprus_init_notification_hooks', true );

		if ( ! $init_notification_hooks ) {

			return;
		}

		if ( method_exists( $this, 'init_notification_hooks' ) ) {
			do_action( 'wprus_before_init_notification_hooks', $this->endpoint, $this );

			$this->init_notification_hooks();

			do_action( 'wprus_after_init_notification_hooks', $this->endpoint, $this );
		}
	}

	/**
	 * Inititialise WordPress action hooks used to process redirected async actions
	 *
	 */
	public function init_async_redirect_hooks() {

		if ( ! $this->is_silent_async_action_redirect() ) {

			if ( ! has_action( 'wp_head', array( $this, 'fire_async_actions' ) ) ) {
				add_action( 'wp_head', array( $this, 'fire_async_actions' ), PHP_INT_MIN + 100, 0 );
			}

			if ( ! has_action( 'admin_head', array( $this, 'fire_async_actions' ) ) ) {
				add_action( 'admin_head', array( $this, 'fire_async_actions' ), PHP_INT_MIN + 100, 0 );
			}

			if ( ! has_action( 'login_head', array( $this, 'fire_async_actions' ) ) ) {
				add_action( 'login_head', array( $this, 'fire_async_actions' ), PHP_INT_MIN + 100, 0 );
			}
		} else {
			$this->init_silent_async_redirect_hooks();
		}
	}

	/**
	 * Inititialise WordPress action hooks used to process async actions
	 *
	 */
	public function init_async_hooks() {
		$init_notification_hooks = apply_filters( 'wprus_init_notification_hooks', true );

		if ( ! $init_notification_hooks ) {

			return;
		}

		if ( ! $this->needs_redirect() ) {

			if ( ! has_action( 'wp_footer', array( $this, 'fire_async_actions' ) ) ) {
				add_action( 'wp_footer', array( $this, 'fire_async_actions' ), PHP_INT_MIN + 100, 0 );
			}

			if ( ! has_action( 'admin_footer', array( $this, 'fire_async_actions' ) ) ) {
				add_action( 'admin_footer', array( $this, 'fire_async_actions' ), PHP_INT_MIN + 100, 0 );
			}

			if ( ! has_action( 'login_footer', array( $this, 'fire_async_actions' ) ) ) {
				add_action( 'login_footer', array( $this, 'fire_async_actions' ), PHP_INT_MIN + 100, 0 );
			}
		} else {
			$this->init_async_redirect_hooks();
		}

		if ( ! has_action( 'shutdown', array( $this, 'save_async_actions' ) ) ) {
			add_action( 'shutdown', array( $this, 'save_async_actions' ), 10, 0 );
		}
	}

	/**
	 * Inititialise WordPress action hooks used to process silent redirected async actions
	 *
	 */
	public function init_silent_async_redirect_hooks() {}

	/**
	 * Determine whether the endpoint can handle async actions.
	 *
	 * @return bool whether the endpoint can handle async actions
	 */
	public function has_async_actions() {
		return false;
	}

	/**
	 * Determines wether the endpoint needs to redirect the current page
	 *
	 * @return bool wether the endpoint needs to redirect the current page
	 */
	public function needs_redirect() {
		return false;
	}

	/**
	 * Determines wether the endpoint redirections need to be silent or output content
	 *
	 * @return bool wether the endpoint redirections need to be silent or output content
	 */
	public function is_silent_async_action_redirect() {
		return false;
	}

	/**
	 * Inititialise data received from remote sites
	 *
	 */
	public function init_data() {
		$data_get     = filter_input( INPUT_GET, 'wprusdata', FILTER_UNSAFE_RAW );
		$data_post    = filter_input( INPUT_POST, 'wprusdata', FILTER_UNSAFE_RAW );
		$this->method = ( $data_post ) ? 'post' : 'get';
		$this->data   = array(
			'get'  => ( $data_get ) ? $this->decrypt_data( $data_get ) : null,
			'post' => ( $data_post ) ? $this->decrypt_data( $data_post ) : null,
		);
	}

	/**
	 * Add endpoint entries to a list of endpoints
	 *
	 * @param array $endpoints The list of endpoints
	 * @return array The list of endpoints
	 */
	public function add_action_endpoints( $endpoints ) {
		$endpoints = array_merge(
			$endpoints,
			$this->get_endpoints()
		);

		return $endpoints;
	}

	/**
	 * Get the site information where the specified endpoint is active for the specify direction
	 *
	 * @param string $endpoint The endpoints key
	 * @param string $site_url The URL of the site ; `false` will use the URL of the local site
	 * @param string $direction The direction for which the endpoint is active - 'incoming' or 'outgoing' ; 'incoming by default'
	 * @return array|bool The site information ; `false` if no corresponding site information
	 */
	public function get_active_site_for_action( $endpoint, $site_url = false, $direction = 'incoming' ) {
		$site_url = ( $site_url ) ? $site_url : get_option( 'home' );
		$site     = $this->settings->get_site( $site_url, $endpoint, $direction );

		return $site;
	}

	/**
	 * Handle requests for test pings and reply with JSON data
	 *
	 */
	public function handle_ping_notification() {
		$data = $this->get_data_post();

		if ( empty( $data ) ) {
			Wprus_Logger::log( __( 'A ping was received but the initiator did not use matching encryption and signature action keys', 'wprus' ), 'info', 'db_log' );
			wp_send_json_error( __( 'Mismatch Encryption settings - please make sure the action keys are correctly configured on both sites.', 'wprus' ) );
		}

		if (
			empty( $data['base_url'] ) ||
			empty( $data['direction'] ) ||
			(
				'incoming' !== $data['direction'] &&
				'outgoing' !== $data['direction']
			)
		) {
			Wprus_Logger::log( __( 'A ping was received but the initiator did not send the proper data.', 'wprus' ), 'info', 'db_log' );
			wp_send_json_error( __( 'Malformed data - the test request was received but the data could not be extracted.', 'wprus' ) );
		}

		$site            = $this->get_active_site_for_action( $this->endpoint, $data['base_url'], $data['direction'] );
		$action_label    = $this->endpoint;
		$direction_label = ( 'incoming' === $data['direction'] ) ? __( 'incoming', 'wprus' ) : __( 'outgoing', 'wprus' );
		$remote_addr     = $_SERVER['REMOTE_ADDR'];
		$settings_class  = self::$settings_class;
		$class_vars      = get_class_vars( $settings_class );

		if ( isset( $class_vars['actions'][ $action_label ] ) ) {
			$action_label = $class_vars['actions'][ $action_label ];
		}

		if ( $site ) {
			do_action( 'wprus_ping_success', $this->endpoint, $data, $remote_addr );

			// translators: %1$s is the sync action name, %2$s is the remote site URL, %3$s is the remote IP, %4$s is the direction
			$message = __( 'Ping received for activated action "%1$s" from %2$s with remote IP %3$s (%4$s)', 'wprus' );

			Wprus_Logger::log(
				sprintf(
					$message,
					$action_label,
					$data['base_url'],
					$remote_addr,
					$direction_label
				),
				'success',
				'db_log'
			);

			// translators: %1$s is the sync action name, %2$s is the current site URL, %3$s is the remote IP, %4$s is the direction
			$message = __( 'Ping success for action "%1$s" from %2$s with remote IP %3$s (%4$s)', 'wprus' );

			wp_send_json_success(
				sprintf(
					$message,
					$action_label,
					get_option( 'home' ),
					$remote_addr,
					$direction_label
				)
			);
		}

		do_action( 'wprus_ping_failure', $this->endpoint, $data, $remote_addr );

		// translators: %1$s is the sync action name, %2$s is the remote site URL, %3$s is the remote IP, %4$s is the direction
		$message = __( 'Ping received for deactivated action "%1$s" from %2$s with remote IP %3$s (%4$s)', 'wprus' );

		Wprus_Logger::log(
			sprintf(
				$message,
				$action_label,
				$data['base_url'],
				$remote_addr,
				$direction_label
			),
			'warning',
			'db_log'
		);

		// translators: %1$s is the sync action name, %2$s is the current site URL, %3$s is the remote IP, %4$s is the direction
		$message = __( '"%1$s" action is not activated on %2$s with remote IP %3$s (%4$s).', 'wprus' );
		$message = sprintf(
			$message,
			$action_label,
			get_option( 'home' ),
			$remote_addr,
			$direction_label
		);

		wp_send_json_error( $message );
	}

	/**
	 * Send a test ping request to a remote website and send the response to the user interface
	 *
	 */
	public function notify_ping_remote() {
		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_UNSAFE_RAW );

		if ( ! wp_verify_nonce( $nonce, 'wprus_ping_nonce' ) ) {
			wp_send_json_error( __( 'Error: unauthorized access - please reload the page and try again.', 'wprus' ) );
		}

		$url          = filter_input( INPUT_POST, 'site_url', FILTER_VALIDATE_URL );
		$direction    = filter_input( INPUT_POST, 'direction', FILTER_UNSAFE_RAW );
		$data         = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
		$success      = false;
		$payload      = false;
		$default_data = array(
			'ping'      => 1,
			'direction' => ( 'incoming' === $direction ) ? 'outgoing' : 'incoming',
		);

		if ( $data ) {
			$data = array_merge( $default_data, $data );
		} else {
			$data = $default_data;
		}

		$response = $this->fire_action(
			$url,
			$data,
			true,
			5
		);

		do_action( 'wprus_ping_fired', $this->endpoint, $data, $response );

		if ( 200 === absint( $response['response_code'] ) ) {
			$data = json_decode( $response['body'], true );

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$payload  = __( 'Error contacting the remote site: ', 'wprus' );
				$payload .= __( 'Payload error - ' ) . json_last_error_msg();
			} else {

				if ( $data['success'] ) {
					$success = true;
				}

				$payload = $data['data'];
			}
		}

		if ( ! $success && ! $payload ) {
			$payload = __( 'Error contacting the remote site: ', 'wprus' );

			if (
				! empty( $response['response_code'] ) &&
				200 !== $response['response_code'] &&
				! empty( $response['response_message'] )
			) {
				$payload .= $response['response_code'] . ' - ' . $response['response_message'];
			} else {
				// translators: %s is the error code
				$payload .= ( ! empty( $response['response_code'] ) ) ? sprintf( __( ' - code: %s - ', 'wprus' ), $response['response_code'] ) : '';
				$payload .= __( 'an undefined error occured. Please make sure the address is correct and try again.', 'wprus' );
				$payload .= "\n";
				$payload .= __( 'On the remote site, please make sure the plugin is activated and that the permalinks are up to date by visiting the permalinks settings page.', 'wprus' );
			}

			if ( 404 === absint( $response['response_code'] ) ) {
				$payload .= "\n";
				$payload .= __( 'On the remote site, please make sure the permalinks are not using the "Plain" option.', 'wprus' );
			}
		}

		$log_type = ( $success ) ? 'success' : 'alert';

		Wprus_Logger::log( $payload, $log_type, 'db_log' );

		if ( ! $success ) {
			Wprus_Logger::log(
				array(
					'message' => 'Response data received from the remote site: ',
					$response,
				),
				'info',
				'db_log'
			);
		}

		if ( $success ) {
			wp_send_json_success( $payload );
		}

		wp_send_json_error( $payload );
	}

	/**
	 * Persist the user ID used to save and fire async actions.
	 *
	 */
	public function set_pending_async_actions_user_id() {

		if ( 0 !== get_current_user_id() ) {
			$this->async_user_id = get_current_user_id();

			$this->setcookie(
				'wprus_user_pending_async_actions',
				bin2hex( $this->encrypt_data( get_current_user_id() ) )
			);
		}
	}

	/**
	 * Add async action to the list of async actions to add to the footer
	 *
	 */
	public function add_async_action( $url, $data ) {
		$data['base_url'] = get_option( 'home' );
		$data['url']      = $url;

		$this->async_actions[] = $data;
	}

	/**
	 * Persist the list of async actions to add to the footer
	 *
	 */
	public function save_async_actions() {

		if ( ! empty( $this->async_actions ) && $this->async_user_id ) {
			update_user_meta(
				$this->async_user_id,
				'wprus_' . $this->endpoint . '_pending_async_actions',
				$this->async_actions
			);
		}
	}

	/**
	 * Fire the async actions
	 *
	 */
	public function fire_async_actions() {
		$location_headers = preg_grep( '/^Location:/i', headers_list() );

		if ( ! empty( $location_headers ) ) {

			return;
		}

		if ( ! empty( $this->async_actions ) ) {
			$user_id = get_current_user_id() ? get_current_user_id() : $this->async_user_id;
			$actions = $this->async_actions;
		} else {

			if ( is_user_logged_in() ) {
				$user_id = get_current_user_id();
			} else {
				$cookie  = filter_input( INPUT_COOKIE, 'wprus_user_pending_async_actions' );
				$user_id = ( $cookie ) ? $this->decrypt_data( hex2bin( $cookie ) ) : false;
			}

			$user    = ( $user_id ) ? get_user_by( 'ID', $user_id ) : false;
			$actions = ( $user ) ? get_user_meta( $user->ID, 'wprus_' . $this->endpoint . '_pending_async_actions', true ) : false;
		}

		if ( $actions && $user_id ) {
			do_action( 'wprus_before_firing_async_actions', $this->endpoint, $actions, $user_id );

			if ( $this->needs_redirect() ) {
				$this->do_async_actions_redirect( $actions, $user_id );
				do_action( 'wprus_after_firing_async_actions', $this->endpoint, $actions, $user_id );

				exit();
			} else {
				$this->print_async_actions_markup( $actions, $user_id );
				do_action( 'wprus_after_firing_async_actions', $this->endpoint, $actions, $user_id );
			}
		}
	}

	/**
	 * Fire a sync action
	 *
	 * @param string $url The remote website's URL
	 * @param mixed $data The data to send
	 * @param bool $blocking whether the request needs to wait for a response - default `false`
	 * @param int $timeout the maximum time to wait for a response in seconds - default `1`
	 * @param string $endpoint the endpoint to send the request to - default `null` ; will use the $endpoint attribute value of the instance
	 * @return array Response data
	 */
	public function fire_action( $url, $data, $blocking = false, $timeout = 1, $endpoint = null ) {
		$data['base_url'] = get_option( 'home' );
		$endpoint         = $endpoint ? $endpoint : $this->endpoint;
		$timeout          = apply_filters( 'wprus_fire_action_timeout', $timeout, $endpoint, $url, $blocking );

		do_action( 'wprus_before_firing_action', $endpoint, $url, $data );

		$data     = apply_filters( 'wprus_action_data', $data, $endpoint, $url );
		$body     = array(
			'wprusdata' => $this->encrypt_data( $data ),
			'token'     => $this->get_token( $url, $data['username'], 'post' ),
		);
		$response = wp_safe_remote_post(
			trailingslashit( $url ) . 'wprus/' . trailingslashit( $endpoint ),
			array(
				'body'     => $body,
				'blocking' => $blocking,
				'compress' => true,
				'timeout'  => $timeout,
			)
		);
		$headers  = wp_remote_retrieve_headers( $response );

		if ( ! empty( $headers ) ) {
			$headers = $headers->getAll();
		}

		do_action( 'wprus_after_firing_action', $endpoint, $url, $data, $response );

		return array(
			'headers'          => $headers,
			'response_code'    => wp_remote_retrieve_response_code( $response ),
			'response_message' => wp_remote_retrieve_response_message( $response ),
			'body'             => wp_remote_retrieve_body( $response ),
		);
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	/**
	 * Get security token for the specified remote site URL and username
	 *
	 * @param string $url The remote website's URL
	 * @param string $username The WordPress user login name
	 * @param string $method The request method - `'get'` or `'post'` ; if `'get'`, the token will not be stored ; default `'post'`
	 * @return string|bool The security token
	 */
	protected function get_token( $url, $username, $method = 'post' ) {
		$user        = get_user_by( 'login', $username );
		$tokens_info = ( $user ) ? get_user_meta( $user->ID, 'wprus_api_tokens', true ) : null;
		$tokens_info = ( null !== $tokens_info && ! $tokens_info ) ? array() : $tokens_info;

		if ( null === $tokens_info ) {

			return false;
		}

		if ( isset( $tokens_info[ $url ] ) && 'get' === $method ) {
			unset( $tokens_info[ $url ] );
		}

		if (
			! isset( $tokens_info[ $url ] ) ||
			$tokens_info[ $url ]['expiry'] <= time() - self::TOKEN_EXPIRY_BUFFER
		) {
			$payload    = array(
				'action'   => $this->endpoint,
				'method'   => $method,
				'base_url' => $url,
			);
			$token_info = $this->get_remote_token( $payload );

			if ( $token_info ) {
				$tokens_info[ $url ] = $token_info;
			} elseif ( isset( $tokens_info[ $url ] ) ) {
				unset( $tokens_info[ $url ] );
			}

			if ( 'post' === $method ) {
				update_user_meta( $user->ID, 'wprus_api_tokens', $tokens_info );
			}
		}

		return isset( $tokens_info[ $url ]['nonce'] ) ? $tokens_info[ $url ]['nonce'] : false;
	}

	/**
	 * Renew the security token for a specific payload
	 *
	 * @param array $payload The payload to send to the remote site
	 * @return array|bool The security token information on success - `false` on failure
	 */
	protected function get_remote_token( $payload ) {

		if ( self::$can_request_token ) {
			$timeout = apply_filters( 'wprus_request_token_timeout', 1 );

			// translators: %s is the url of the caller
			Wprus_Logger::log( sprintf( __( 'Renewing token for %s', 'wprus' ), $payload['base_url'] ), 'info', 'db_log' );
		} else {
			$timeout = apply_filters( 'wprus_request_token_retry_timeout', 5 );
		}

		$token_info = false;
		$response   = wp_remote_post(
			trailingslashit( $payload['base_url'] ) . 'wprus/token/',
			array(
				'body'     => array(
					'wprusdata' => $this->encrypt_data( $payload ),
				),
				'compress' => true,
				'timeout'  => $timeout,
			)
		);

		$body = wp_remote_retrieve_body( $response );

		if ( $body && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$token_info = json_decode( $body, true );
		} elseif ( self::$can_request_token ) {
			Wprus_Logger::log(
				sprintf(
					// translators: %s is the url of the caller
					__( 'Failed to renew token for %s - retrying...', 'wprus' ),
					$payload['base_url']
				),
				'warning',
				'db_log'
			);

			self::$can_request_token = false;
			$token_info              = $this->get_remote_token( $payload );
		}

		if ( ! $token_info && ! self::$can_request_token ) {
			Wprus_Logger::log(
				sprintf(
					// translators: %s is the url of the caller
					__( 'Failed to renew token for %s', 'wprus' ),
					$payload['base_url']
				),
				'alert',
				'db_log'
			);

			if ( is_wp_error( $response ) ) {
				Wprus_Logger::log(
					array(
						'message' => 'A WordPress error was triggered: ',
						$response->get_error_code() . ' ' . $response->get_error_message(),
					),
					'info',
					'db_log'
				);
			} else {
				$headers = wp_remote_retrieve_headers( $response );

				if ( ! empty( $headers ) ) {
					$headers = $headers->getAll();
				}

				Wprus_Logger::log(
					array(
						'message' => 'Response data received from the remote site: ',
						array(
							'headers'          => $headers,
							'response_code'    => wp_remote_retrieve_response_code( $response ),
							'response_message' => wp_remote_retrieve_response_message( $response ),
							'body'             => wp_remote_retrieve_body( $response ),
						),
					),
					'info',
					'db_log'
				);
			}
		}

		return $token_info;
	}

	/**
	 * Set cookie helper
	 *
	 * @param string $name The name of the cookie
	 * @param mixed $value The cookie value
	 * @param int $expire The time the cookie expires ; this is a Unix timestamp so is in number of seconds since the epoch - default `0`
	 * @return array|bool The security token information on success - `false` on failure
	 */
	protected function setcookie( $name, $value, $expire = 0 ) {

		if ( ! headers_sent( $file, $line ) ) {

			if ( PHP_VERSION_ID < 70300 ) {
				setcookie( $name, $value, $expire, COOKIEPATH ? COOKIEPATH . '; SameSite=None' : '/; SameSite=None', COOKIE_DOMAIN, true, false );
			} else {
				setcookie(
					$name,
					$value,
					array(
						'expires'  => $expire,
						'path'     => COOKIEPATH ? COOKIEPATH : '/',
						'domain'   => COOKIE_DOMAIN,
						'secure'   => true,
						'httponly' => false,
						'samesite' => 'None',
					)
				);
			}
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			trigger_error(  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				esc_html( $name . 'cookie cannot be set - headers already sent by ' . $file . 'on line' . $line ),
				E_USER_NOTICE
			);
		}
	}

	/**
	 * Get data received from remote site with GET method
	 *
	 * @return mixed The data
	 */
	protected function get_data_get() {
		$data_get = $this->data['get'];

		return $data_get;
	}

	/**
	 * Get data received from remote site with POST method
	 *
	 * @return mixed The data
	 */
	protected function get_data_post() {
		$data_post = $this->data['post'];

		return $data_post;
	}

	/**
	 * Get data received from remote site with both GET and POST methods
	 *
	 * @return array The data
	 */
	protected function get_data() {
		$data = array();

		if ( ! empty( $this->data['get'] ) && is_array( $this->data['get'] ) ) {
			$data = array_merge( $data, $this->data['get'] );
		} elseif ( ! empty( $this->data['get'] ) ) {
			$data[] = $this->data['get'];
		}

		if ( ! empty( $this->data['post'] ) && is_array( $this->data['post'] ) ) {
			$data = array_merge( $data, $this->data['post'] );
		} elseif ( ! empty( $this->data['post'] ) ) {
			$data[] = $this->data['post'];
		}

		return $data;
	}

	/**
	 * Check whether the data received from remote site is valid
	 * @param array The data to validate
	 * @return bool Whether the data is valid
	 */
	protected function validate( $data ) {
		$valid =
			isset( $data['username'] ) &&
			! empty( $data['username'] ) &&
			isset( $data['base_url'] ) &&
			! empty( $data['base_url'] );

		return $valid;
	}

	/**
	 * Sanitize data received from remote site
	 * @param array The data to sanitize
	 * @return bool The sanitized data
	 */
	protected function sanitize( $data ) {
		return $data;
	}

	/**
	 * Get endpoints entries for this instance
	 *
	 * @return array The instance's endpoint entries
	 */
	protected function get_endpoints() {
		return array(
			$this->endpoint => $this->endpoint,
		);
	}

	/**
	 * Get the URL to redirect to when performing async actions requiring redirections
	 *
	 * @param string $ajax_fallback The URL to fallback to in case we're in an ajax request ; default false, which will result to home_url()
	 * @return string the current URL
	 */
	protected function get_redirect_url( $ajax_fallback = false ) {
		$parts = wp_parse_url( home_url() );
		$url   = $parts['scheme'] . '://' . $parts['host'] . add_query_arg( null, null );

		if ( false !== strpos( $url, 'admin-ajax.php' ) ) {
			$ajax_fallback = ( $ajax_fallback ) ? $ajax_fallback : home_url();
			$url           = apply_filters( 'wprus_get_redirect_url_ajax', $ajax_fallback, $this->endpoint );
		}

		return $url;
	}

	/**
	 * Redirect the page to perform asyn actions with fallback output for javascript redirection
	 *
	 * @param array $actions The async actions to fire
	 * @param int $user_id The ID of the user for which the actions are to be fired
	 */
	protected function do_async_actions_redirect( $actions, $user_id ) {
		$data                 = array_pop( $actions );
		$data['callback_url'] = $this->get_redirect_url();
		$url                  = trailingslashit( $data['url'] );
		$async_url            = $url . 'wprus/' . trailingslashit( $this->endpoint );

		unset( $data['url'] );

		$data      = apply_filters( 'wprus_action_data', $data, $this->endpoint, $url );
		$args      = array(
			'wprusdata' => rawurlencode( $this->encrypt_data( $data ) ),
			'token'     => rawurlencode( $this->get_token( $url, $data['username'], 'get' ) ),
		);
		$async_url = add_query_arg( $args, $async_url );
		$output    = $this->get_async_action_output( $async_url, true );

		update_user_meta( $user_id, 'wprus_' . $this->endpoint . '_pending_async_actions', $actions );
		Wprus_Logger::log(
			sprintf(
				// translators: %1$s is the url of the endpoint ; %2$s is the action called
				__( 'Ready to redirect to %1$s async URL in %2$s', 'wprus' ),
				$async_url,
				current_filter()
			),
			'info',
			'db_log'
		);

		if ( $this->is_silent_async_action_redirect() ) {
			wp_redirect( $async_url, 303 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		}

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Print the markup used by async actions
	 *
	 * @param array $actions The async actions to fire
	 * @param int $user_id The ID of the user for which the actions are to be fired
	 */
	protected function print_async_actions_markup( $actions, $user_id ) {
		$output = '';

		foreach ( $actions as $action_index => $data ) {
			$url       = trailingslashit( $data['url'] );
			$async_url = $url . 'wprus/' . trailingslashit( $this->endpoint );

			unset( $data['url'] );

			$data      = apply_filters( 'wprus_action_data', $data, $this->endpoint, $url );
			$args      = array(
				'wprusdata' => rawurlencode( $this->encrypt_data( $data ) ),
				'token'     => rawurlencode( $this->get_token( $url, $data['username'], 'get' ) ),
			);
			$async_url = add_query_arg( $args, $async_url );
			$output   .= $this->get_async_action_output( $async_url );

			Wprus_Logger::log(
				sprintf(
					// translators: %1$s is the url of the script ; %2$s is the action called
					__( 'Added %1$s async URL in %2$s', 'wprus' ),
					$async_url,
					current_filter()
				),
				'info',
				'db_log'
			);
		}

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		do_action( 'wprus_after_firing_async_actions', $this->endpoint, $actions, $user_id );
		delete_user_meta( $user_id, 'wprus_' . $this->endpoint . '_pending_async_actions' );

		$this->async_actions = array();
	}

	/**
	 * Get the output needed to perform an async action
	 *
	 * @param string $async_url The URL of an action
	 * @param bool $redirect Whether the page needs to be redirected - if true, gets the fallback output for javascript redirection ; if false, gets the iframe used to perform the action ; default false
	 */
	protected function get_async_action_output( $async_url, $redirect = false ) {

		if ( ! $redirect ) {
			return '<iframe style="display:none" src="' . $async_url . '"></iframe>'; // phpcs:ignor
		}

		ob_start();

		wprus_get_template( 'redirect-processing.php' );

		$output  = ob_get_clean();
		$search  = array(
			'/\>[^\S ]+/s',
			'/[^\S ]+\</s',
			'/(\s)+/s',
			'/<!--(.|\s)*?-->/',
		);
		$replace = array(
			'>',
			'<',
			'\\1',
			'',
		);
		$output  = preg_replace( $search, $replace, $output );

		ob_start();

		wprus_get_template(
			'redirect-processing-script.php',
			array(
				'async_url' => $async_url,
				'output'    => $output,
			)
		);

		$output = ob_get_clean();

		return $output;
	}

	/**
	 * check if ip is in range
	 *
	 * @param string $ip The ip address to check against the range
	 * @param string $range The CIDR
	 * @return bool True if the ip is in range, false otherwise
	 */
	protected function cidr_match( $ip, $range ) {
		list ( $subnet, $bits ) = explode( '/', $range );
		$ip                     = ip2long( $ip );
		$subnet                 = ip2long( $subnet );

		if ( ! $ip || ! $subnet || ! $bits ) {

			return false;
		}

		$mask    = -1 << ( 32 - $bits );
		$subnet &= $mask; // in case the supplied subnet was not correctly aligned

		return ( $ip & $mask ) === $subnet;
	}
}
