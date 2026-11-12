<?php
/**
 * Simulate the other site's token and user requests without a network.
 *
 * @package Wprus
 */

/** Keep the simulated site's users and request history separate from WordPress. */
class Wprus_Test_Peer {
	/**
	 * Received requests.
	 *
	 * @var array
	 */
	public $requests = array();
	/**
	 * Simulated users keyed by login.
	 *
	 * @var array
	 */
	public $users = array();
	/**
	 * Issued token values.
	 *
	 * @var array
	 */
	private $tokens = array();

	/**
	 * Process one request sent using WordPress HTTP functions.
	 *
	 * @param array  $arguments Request arguments.
	 * @param string $url Request address.
	 * @return array|WP_Error Simulated response.
	 */
	public function receive( $arguments, $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$body = isset( $arguments['body'] ) && is_array( $arguments['body'] ) ? $arguments['body'] : array();
		if ( 'GET' === ( $arguments['method'] ?? '' ) ) {
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $body );
		}
		$encrypted = isset( $body['wprusdata'] ) ? $body['wprusdata'] : '';
		$plain     = Wprus_Crypto::decrypt( $encrypted, 'wprus-test-encryption-key', 'wprus-test-signature-key' );
		$data      = is_string( $plain ) ? json_decode( $plain, true ) : null;
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'wprus_test_invalid_message', 'The simulated site could not verify the message.' );
		}
		$this->requests[] = array(
			'path' => $path,
			'data' => $data,
		);
		if ( '/wprus/token/' === $path ) {
			$token                  = 'peer-token-' . ( count( $this->tokens ) + 1 );
			$this->tokens[ $token ] = ( isset( $data['method'] ) && 'get' === $data['method'] ) ? 'get' : 'post';
			return $this->response(
				wp_json_encode(
					array(
						'nonce'  => $token,
						'expiry' => time() + 1800,
					)
				)
			);
		}
		if ( empty( $body['token'] ) || empty( $this->tokens[ $body['token'] ] ) ) {
			return new WP_Error( 'wprus_test_invalid_token', 'The simulated site rejected the token.' );
		}
		if ( 'get' === $this->tokens[ $body['token'] ] ) {
			unset( $this->tokens[ $body['token'] ] );
		}
		if (
			'/wprus/wp-admin/' === $path &&
			'ping' === ( $data['action'] ?? '' ) &&
			! empty( $data['data']['target_action'] ) &&
			in_array( $data['data']['direction'] ?? '', array( 'incoming', 'outgoing' ), true )
		) {
			return $this->response(
				wp_json_encode(
					array(
						'success' => true,
						'data'    => 'Simulated connection succeeded.',
					)
				)
			);
		}
		if ( '/wprus/wp-admin/' === $path ) {
			return new WP_Error( 'wprus_test_unexpected_action', 'The simulated site received an unexpected administrative action.' );
		}
		if ( '/wprus/identity/' === $path ) {
			$email = isset( $data['user_email'] ) ? $data['user_email'] : '';
			return $this->response(
				wp_json_encode(
					array(
						'success' => true,
						'data'    => array(
							'username_exists' => isset( $this->users[ $data['username'] ?? '' ] ),
							'email_exists'    => in_array( $email, array_column( $this->users, 'user_email' ), true ),
						),
					)
				)
			);
		}
		$username = isset( $data['username'] ) ? $data['username'] : '';
		if ( '/wprus/create/' === $path && '' !== $username ) {
			$this->users[ $username ] = $data;
		} elseif ( '/wprus/update/' === $path && isset( $this->users[ $username ] ) ) {
			$this->users[ $username ] = array_merge( $this->users[ $username ], $data );
		} elseif ( '/wprus/delete/' === $path ) {
			unset( $this->users[ $username ] );
		} elseif ( '/wprus/password/' === $path && isset( $this->users[ $username ] ) && isset( $data['user_pass'] ) ) {
			$this->users[ $username ]['user_pass'] = $data['user_pass'];
		} elseif ( in_array( $path, array( '/wprus/login/', '/wprus/logout/' ), true ) && isset( $this->users[ $username ] ) ) {
			$this->users[ $username ]['logged_in'] = '/wprus/login/' === $path;
		} elseif ( '/wprus/meta/' === $path && isset( $this->users[ $username ] ) && isset( $data['add'] ) ) {
			foreach ( $data['add'] as $key => $values ) {
				foreach ( $values as $value ) {
					$this->users[ $username ]['meta'][ $key ][] = $value['meta_value'];
				}
			}
		} else {
			return new WP_Error( 'wprus_test_unexpected_action', 'The simulated site received an unexpected action.' );
		}
		return $this->response( '' );
	}

	/**
	 * Create a WordPress-compatible response.
	 *
	 * @param string $body Response body.
	 * @return array Response fields.
	 */
	private function response( $body ) {
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
