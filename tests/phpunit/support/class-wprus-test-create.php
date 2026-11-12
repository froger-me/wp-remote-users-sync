<?php
/**
 * Shared helpers for incoming action feature tests.
 *
 * @package Wprus
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- The request helpers are kept together for the action tests.

/** Supply a decoded request to the real action handler. */
trait Wprus_Test_Incoming_Input {
	/**
	 * Receive a decoded request using the normal handler.
	 *
	 * @param array  $payload Request data.
	 * @param string $method Request method.
	 * @return mixed Handler result.
	 */
	public function receive( $payload, $method = 'post' ) {
		$this->method          = $method;
		$this->data            = array(
			'get'  => null,
			'post' => null,
		);
		$this->data[ $method ] = $payload;
		return $this->handle_notification();
	}

	/**
	 * Receive through the common identity check before the action handler.
	 *
	 * @param array  $payload Request data.
	 * @param string $method Request method.
	 */
	public function receive_checked( $payload, $method = 'post' ) {
		$this->method          = $method;
		$this->data            = array(
			'get'  => null,
			'post' => null,
		);
		$this->data[ $method ] = $payload;
		$this->handle_request();
	}
}

/** Test access to the Create action. */
class Wprus_Test_Create extends Wprus_Api_Create {
	use Wprus_Test_Incoming_Input;
}
/** Test access to the Update action. */
class Wprus_Test_Update extends Wprus_Api_Update {
	use Wprus_Test_Incoming_Input;
}
/** Test access to the Delete action. */
class Wprus_Test_Delete extends Wprus_Api_Delete {
	use Wprus_Test_Incoming_Input;
}
/** Test access to the Password action. */
class Wprus_Test_Password extends Wprus_Api_Password {
	use Wprus_Test_Incoming_Input;
}
/** Test access to the Metadata action. */
class Wprus_Test_Meta extends Wprus_Api_Meta {
	use Wprus_Test_Incoming_Input;
}
/** Test access to the Login action. */
class Wprus_Test_Login extends Wprus_Api_Login {
	use Wprus_Test_Incoming_Input;
}
/** Test access to the Logout action. */
class Wprus_Test_Logout extends Wprus_Api_Logout {
	use Wprus_Test_Incoming_Input;

	/**
	 * Supply the user whose queued actions would normally be saved during a request.
	 *
	 * @param int $user_id Local user ID.
	 * @return void
	 */
	public function set_pending_user( $user_id ) {
		$this->async_user_id = $user_id;
	}
}

/** Run real action handlers with disposable WordPress users. */
abstract class Wprus_Feature_Test_Case extends \AnyapeWPTestTools\IntegrationTestCase {
	/**
	 * Settings for this action.
	 *
	 * @var Wprus_Settings
	 */
	protected $settings;

	/** Prepare independent settings for each case. */
	protected function setUp(): void {
		parent::setUp();
		// PHP tests share one process; a real web request starts these arrays empty.
		foreach ( array( 'approved_creations', 'blocked_creations', 'previous_emails' ) as $property_name ) {
			$property = new ReflectionProperty( Wprus_Api_Identity::class, $property_name );
			$property->setAccessible( true );
			$property->setValue( null, array() );
		}
		if ( isset( $GLOBALS['wprus_test_ready'][1]['identity'] ) ) {
			$identity = $GLOBALS['wprus_test_ready'][1]['identity'];
			remove_filter( 'wp_pre_insert_user_data', array( $identity, 'require_validated_creation' ), PHP_INT_MAX );
			remove_action( 'user_register', array( $identity, 'check_created_user' ), PHP_INT_MIN + 100 );
		}
		$GLOBALS['wprus_test_settings'] = wprus_test_settings();
		$this->settings                 = new Wprus_Settings( array( 'login', 'logout', 'create', 'update', 'delete', 'password', 'role', 'meta' ) );
	}

	/**
	 * Construct an incoming action with the real role and password handlers.
	 *
	 * @param string $action Action name.
	 * @return object Action handler.
	 */
	protected function action( $action ) {
		$class = 'Wprus_Test_' . ucfirst( $action );
		$api   = new $class( $action, $this->settings );
		$api->init_role_handler( new Wprus_Api_Role( 'role', $this->settings ) );
		$api->init_password_handler( new Wprus_Api_Password( 'password', $this->settings ) );
		return $api;
	}

	/**
	 * Apply changed settings to newly constructed handlers.
	 *
	 * @return void
	 */
	protected function reload_settings() {
		$this->settings = new Wprus_Settings( array( 'login', 'logout', 'create', 'update', 'delete', 'password', 'role', 'meta' ) );
	}

	/**
	 * Build a request identifying the peer and target user.
	 *
	 * @param string $username Login name.
	 * @return array Request fields.
	 */
	protected function payload( $username ) {
		return array(
			'username' => $username,
			'base_url' => 'https://wprus-peer.test',
		);
	}
}
