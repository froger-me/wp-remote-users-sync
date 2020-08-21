<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Wprus_Import_Export {
	const RESULTS_PER_QUERY = 10000;

	protected $spl;

	public function __construct( $init_hooks = false ) {

		if ( $init_hooks ) {
			add_action( 'parse_request', array( $this, 'parse_request' ), 10, 0 );
			add_action( 'wp', array( get_class(), 'register_files_cleanup' ) );
			add_action( 'wprus_logs_cleanup', array( get_class(), 'clear_files' ) );
			add_action( 'wp_ajax_wprus_import_users', array( $this, 'import' ), 10, 0 );
			add_action( 'wp_ajax_wprus_export_users', array( $this, 'export' ), 10, 0 );

			add_filter( 'wprus_init_notification_hooks', array( $this, 'init_notification_hooks' ), 10, 1 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/


	public static function register_files_cleanup() {

		if ( ! wp_next_scheduled( 'wprus_files_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wprus_files_cleanup' );
		}
	}

	public static function clear_files( $force = false ) {

		if ( defined( 'WP_SETUP_CONFIG' ) || defined( 'WP_INSTALLING' ) ) {

			return;
		}

		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			wp_die( esc_html__( 'Error: WordPress File System experienced an unknown problem.', 'wprus' ) );
		}

		$wp_filesystem->delete( trailingslashit( $wp_filesystem->wp_content_dir() ) . 'wprus', true );
	}

	public function init_notification_hooks( $init ) {
		$bypass = filter_input( INPUT_POST, 'doing_import_export_operation', FILTER_VALIDATE_INT );

		if ( (bool) $bypass ) {

			return false;
		}

		return $init;
	}

	public function parse_request() {
		global $wp;

		if ( isset( $wp->query_vars['wprus_file'], $wp->query_vars['nonce'] ) ) {
			$file_name = $wp->query_vars['wprus_file'];
			$nonce     = $wp->query_vars['nonce'];

			$this->download( $file_name, $nonce );
		}
	}

	public function export() {
		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );

		if ( ! wp_verify_nonce( $nonce, 'wprus_import_export_nonce' ) ) {
			wp_send_json_error(
				array(
					'message'   => __( 'Error: unauthorized access - please reload the page and try again.', 'wprus' ),
					'file_url'  => false,
					'file_name' => false,
				)
			);
		}

		$file_info = $this->get_export_file();

		if ( ! $file_info ) {
			wp_send_json_error(
				array(
					'message'   => __( 'Error: Unable to create the export file', 'wprus' ),
					'file_url'  => false,
					'file_name' => false,
				)
			);
		}

		$offset     = absint( filter_input( INPUT_POST, 'offset', FILTER_VALIDATE_INT ) );
		$max        = absint( filter_input( INPUT_POST, 'max', FILTER_VALIDATE_INT ) );
		$keep_role  = absint( filter_input( INPUT_POST, 'keep_role', FILTER_VALIDATE_INT ) );
		$user_roles = filter_input( INPUT_POST, 'user_roles', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );
		$meta_keys  = filter_input( INPUT_POST, 'meta_keys', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );
		$pages      = null;
		$limit      = self::RESULTS_PER_QUERY;
		$aborted    = false;

		if ( 0 < $max ) {
			$pages = ceil( $max / self::RESULTS_PER_QUERY ) - 1;
			$limit = ( self::RESULTS_PER_QUERY < $max ) ? self::RESULTS_PER_QUERY : $max;
		}

		$users_data  = $this->get_users_data( $offset, $limit, $keep_role, $user_roles, $meta_keys );
		$result      = $this->write_to_export_file( $file_info['path'], $users_data );
		$num_results = ( $result ) ? 0 : count( $users_data );

		while ( ! empty( $users_data ) || ( null !== $pages && 0 < $pages ) ) {

			if ( ! $result ) {
				$aborted = true;

				break;
			} else {
				$num_results += count( $users_data );
			}

			if ( 0 < $max ) {
				$limit = ( $max <= $offset + self::RESULTS_PER_QUERY ) ? $max - $offset : self::RESULTS_PER_QUERY;
			} else {
				$limit = self::RESULTS_PER_QUERY;
			}

			if ( 0 === $limit ) {

				break;
			}

			$pages      = ( null === $pages ) ? null : $pages - 1;
			$offset    += self::RESULTS_PER_QUERY;
			$users_data = $this->get_users_data( $offset, $limit, $keep_role, $user_roles, $meta_keys );
			$result     = $this->write_to_export_file( $file_info['path'], $users_data );
		}

		if ( $aborted ) {
			wp_send_json_error(
				array(
					'message'   => sprintf(
						// translators: %d is the number of results
						_n(
							'Warning: export aborted prematurely (%d result)',
							'Warning: export aborted prematurely (%d results)',
							$num_results,
							'wprus'
						),
						$num_results
					),
					'file_name' => esc_html( $file_info['filename'] ),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'message'   => sprintf(
						// translators: %d is the number of results
						_n(
							'Success: exported %d result',
							'Success: exported %d results',
							$num_results,
							'wprus'
						),
						$num_results
					),
					'file_name' => esc_html( $file_info['filename'] ),
				)
			);
		}
	}

	public function download( $file_name = '', $nonce = '' ) {

		if ( ! wp_verify_nonce( $nonce, 'wprus_import_export_nonce' ) ) {
			wp_die( esc_html__( 'Error: unauthorized access - please reload the previous page and try again.', 'wprus' ) );
		}

		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			wp_die( esc_html__( 'Error: WordPress File System experienced an unknown problem.', 'wprus' ) );
		}

		$file = trailingslashit( $wp_filesystem->wp_content_dir() ) . 'wprus/' . $file_name;

		if ( ! $wp_filesystem->is_file( $file ) ) {
			wp_die( esc_html__( 'Error: the file was not found on the server. Please try exporting again.', 'wprus' ) );
		}

		$this->send_file( $file );

		$wp_filesystem->delete( $file );

		exit();
	}

	public function import() {
		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );

		if ( ! wp_verify_nonce( $nonce, 'wprus_import_export_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error: unauthorized access - please reload the page and try again.', 'wprus' ),
				)
			);
		}

		if ( isset( $_FILES['file'] ) ) {
			$file_info = filter_var( $_FILES['file'], FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );
		} else {
			$file_info = false;
		}

		$valid       = (bool) $file_info;
		$num_results = 0;
		$error_text  = '';
		$errors      = array();

		if ( ! $valid ) {
			$error_text = __( 'Something very wrong happened.', 'wprus' );
		}

		if ( $valid && 'application/octet-stream' !== $file_info['type'] ) {
			$valid      = false;
			$error_text = __( 'Make sure the uploaded file is a zip archive.', 'wprus' );
		}

		if ( $valid && 0 !== absint( $file_info['error'] ) ) {
			$valid = false;

			switch ( $file_info['error'] ) {
				case UPLOAD_ERR_INI_SIZE:
					$error_text = __( 'The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'wprus' );
					break;

				case UPLOAD_ERR_FORM_SIZE:
					$error_text = __( 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.', 'wprus' );
					break;

				case UPLOAD_ERR_PARTIAL:
					$error_text = __( 'The uploaded file was only partially uploaded.', 'wprus' );
					break;

				case UPLOAD_ERR_NO_FILE:
					$error_text = __( 'No file was uploaded.', 'wprus' );
					break;

				case UPLOAD_ERR_NO_TMP_DIR:
					$error_text = __( 'Missing a temporary folder.', 'wprus' );
					break;

				case UPLOAD_ERR_CANT_WRITE:
					$error_text = __( 'Failed to write file to disk.', 'wprus' );
					break;

				case UPLOAD_ERR_EXTENSION:
					$error_text = __( 'A PHP extension stopped the file upload. PHP does not provide a way to ascertain which extension caused the file upload to stop; examining the list of loaded extensions with phpinfo() may help.', 'wprus' );
					break;
			}
		}

		if ( $valid && 0 >= absint( $file_info['size'] ) ) {
			$valid      = false;
			$error_text = __( 'Make sure the uploaded file is not empty.', 'wprus' );
		}

		if ( ! $valid ) {
			wp_send_json_error(
				array(
					'message' => $error_text,
				)
			);
		}

		$num_results = $this->read_from_imported_file( $file_info['tmp_name'], $errors );
		$message     = sprintf(
			// translators: %d is the number of results
			_n(
				'Imported %d user',
				'Imported %d users',
				$num_results,
				'wprus'
			),
			$num_results
		);

		if ( ! empty( $errors ) ) {
			$message .= sprintf(
				// translators: %d is the number of errors
				_n(
					' - %d error:',
					' - %d errors:',
					count( $errors ),
					'wprus'
				),
				count( $errors )
			);
		}

		wp_send_json_success(
			array(
				'message' => $message,
				'errors'  => $errors,
			)
		);
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected function get_export_file() {
		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {

			return false;
		}

		$ready    = $this->maybe_create_data_dir();
		$dir_path = trailingslashit( $wp_filesystem->wp_content_dir() ) . 'wprus';

		if ( ! $ready || ! $wp_filesystem->is_writable( $dir_path ) ) {

			return false;
		}

		$time     = time();
		$filename = 'wprus-user-export-' . gmdate( 'Y-m-d-H-i-s', $time ) . '.dat';
		$filepath = $dir_path . DIRECTORY_SEPARATOR . $filename;
		$file     = $wp_filesystem->touch( $filepath, $time, $time );

		if ( ! $file ) {

			return false;
		}

		return array(
			'path'     => $filepath,
			'url'      => trailingslashit( content_url() ) . 'wprus/' . $filename,
			'filename' => $filename,
		);
	}

	protected function read_from_imported_file( $file, &$errors ) {
		$fp = @fopen( $file, 'r' ); // @codingStandardsIgnoreLine

		if ( ! $fp ) {

			return __( 'Failed to open the file.', 'wprus' );
		} else {
			$line        = 0;
			$num_results = 0;

			while ( ! feof( $fp )  ) { // @codingStandardsIgnoreLine
				$line ++;

				$line_content = fgets( $fp );

				if ( ! empty( $line_content ) ) {
					$user_data = json_decode( $line_content, true ); // @codingStandardsIgnoreLine
					$result    = $this->process_user_data( $user_data, $line );

					if ( true !== $result ) {
						$errors[] = $result;
					} else {
						$num_results ++;
					}
				}
			}

			fclose( $fp ); // @codingStandardsIgnoreLine
		}

		@unlink( $file ); // @codingStandardsIgnoreLine

		return $num_results;
	}

	protected function write_to_export_file( $filepath, $users_data ) {

		if ( is_array( $users_data ) && ! empty( $users_data ) ) {

			foreach ( $users_data as $data ) {
				$data   = wp_json_encode( $data );
				$result = $this->put_contents( $filepath, $data . PHP_EOL, 0644 );

				if ( ! $result ) {

					return false;
				}
			}

			return true;
		}

		return true;
	}

	protected function process_user_data( $user_data, $line ) {

		if ( ! is_array( $user_data ) || ! isset( $user_data['user_login'] ) ) {

			// translators: %s is the line number
			return sprintf( __( 'Found invalid data on line %s' ), $line );
		}

		$metas = $user_data['metadata'];
		$roles = $user_data['roles'];

		unset( $user_data['metadata'] );
		unset( $user_data['roles'] );

		$user_data['user_pass'] = wp_generate_password( 16 );
		$maybe_user_id          = wp_insert_user( $user_data );

		if ( is_wp_error( $maybe_user_id ) ) {

			return sprintf(
				// translators: %1$s is the username, %2$s is the line , %3$s is the error
				__( 'Error importing user "%1$s" on line %2$s - %3$s', 'wprus' ),
				$user_data['user_login'],
				$line,
				$maybe_user_id->get_error_message()
			);
		}

		$user = get_user_by( 'ID', $maybe_user_id );

		if ( ! empty( $roles ) ) {
			$wp_roles      = wp_roles();
			$main_assigned = false;

			foreach ( $roles as $role ) {

				if ( $wp_roles->is_role( $role ) ) {

					if ( $main_assigned ) {
						$user->add_role( $role );
					} else {
						$main_assigned = true;

						$user->set_role( $role );
					}
				}
			}
		}

		if ( ! empty( $metas ) ) {

			foreach ( $metas as $meta_key => $meta_values ) {

				if ( 1 === count( $meta_values ) ) {
					update_user_meta( $user->ID, $meta_key, reset( $meta_values ) );
				} else {

					foreach ( $meta_values as $value ) {
						add_user_meta( $user->ID, $meta_key, $value );
					}
				}
			}
		}

		return true;
	}

	protected function get_users_data( $offset = 0, $limit = -1, $keep_role = true, $user_roles = array(), $meta_keys = array() ) {
		$users_data = array();
		$args       = array(
			'fields' => 'all_with_meta',
			'number' => $limit,
			'offset' => $offset,
		);

		if ( is_array( $user_roles ) && ! empty( $user_roles ) ) {
			$args['role__in'] = $user_roles;
		}

		$users = get_users( $args );

		if ( empty( $users ) ) {

			return $users_data;
		}

		foreach ( $users as $user ) {
			$users_data[ $user->user_login ] = array(
				'user_login'           => $user->user_login,
				'user_nicename'        => $user->user_nicename,
				'user_url'             => $user->user_url,
				'user_email'           => $user->user_email,
				'display_name'         => $user->display_name,
				'nickname'             => $user->nickname,
				'first_name'           => $user->first_name,
				'last_name'            => $user->last_name,
				'description'          => $user->description,
				'rich_editing'         => $user->rich_editing,
				'syntax_highlighting'  => $user->syntax_highlighting,
				'comment_shortcuts'    => get_user_meta( $user->ID, 'comment_shortcuts', true ),
				'admin_color'          => get_user_meta( $user->ID, 'admin_color', true ),
				'use_ssl'              => get_user_meta( $user->ID, 'use_ssl', true ),
				'user_registered'      => $user->user_registered,
				'show_admin_bar_front' => get_user_meta( $user->ID, 'show_admin_bar_front', true ),
				'locale'               => $user->locale,
				'roles'                => ( $keep_role ) ? $user->roles : array(),
				'metadata'             => array(),
			);

			if ( is_array( $meta_keys ) && ! empty( $meta_keys ) ) {
				$metas = array();

				foreach ( $meta_keys as $meta_key ) {

					if ( ! isset( $users_data[ $user->user_login ][ $meta_key ] ) ) {
						$metas[ $meta_key ] = get_user_meta( $user->ID, $meta_key );
					}
				}

				if ( ! empty( $metas ) ) {
					$users_data[ $user->user_login ]['metadata'] = $metas;
				}
			}
		}

		return $users_data;
	}

	protected function maybe_create_data_dir() {
		global $wp_filesystem;

		$path = trailingslashit( $wp_filesystem->wp_content_dir() ) . 'wprus';

		if ( ! $wp_filesystem->is_dir( $path ) ) {
			$result = $wp_filesystem->mkdir( $path );

			if ( $result ) {
				$result = $this->generate_restricted_htaccess( $path . DIRECTORY_SEPARATOR . '.htaccess' );
			}
		} elseif ( ! $wp_filesystem->is_file( $path . DIRECTORY_SEPARATOR . '.htaccess' ) ) {
			$result = $this->generate_restricted_htaccess( $path . DIRECTORY_SEPARATOR . '.htaccess' );
		} else {
			$result = true;
		}

		return $result;
	}

	protected function generate_restricted_htaccess( $path ) {
		global $wp_filesystem;

		$contents = "Order deny,allow\nDeny from all";

		$wp_filesystem->touch( $path );

		return $wp_filesystem->put_contents( $path, $contents, 0644 );
	}

	protected function put_contents( $file, $contents, $mode = false ) {
		global $wp_filesystem;

		$fp = @fopen( $file, 'ab' ); // @codingStandardsIgnoreLine

		if ( ! $fp ) {

			return false;
		}

		mbstring_binary_safe_encoding();

		$data_length   = strlen( $contents );
		$bytes_written = fwrite( $fp, $contents ); // @codingStandardsIgnoreLine

		reset_mbstring_encoding();

		fclose( $fp ); // @codingStandardsIgnoreLine

		if ( $data_length !== $bytes_written ) {

			return false;
		}

		$wp_filesystem->chmod( $file, $mode );

		return true;
	}

	protected function send_file( $file ) {
		global $wp_filesystem;

		$last_modified = gmdate( 'D, d M Y H:i:s', $wp_filesystem->mtime( $file ) );

		if ( false === strpos( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS' ) ) {
			header( 'Content-Length: ' . $wp_filesystem->size( $file ) );
		}

		header( 'Pragma: public' );
		header( 'Last-Modified: ' . $last_modified . ' GMT' );
		header( 'ETag: "' . md5( $last_modified ) . '"' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0, private' );
		header( 'Content-Disposition: attachment; filename="' . basename( $file ) . '";' );
		header( 'Content-Encoding: UTF-8' );
		header( 'Content-type: application/octet-stream; charset=UTF-8' );
		status_header( 200 );

		$file_handle   = fopen( $file, 'r' ); // @codingStandardsIgnoreLine
		$output_handle = fopen( 'php://output', 'w' ); // @codingStandardsIgnoreLine

		stream_copy_to_stream( $file_handle, $output_handle );
		fclose( $file_handle ); // @codingStandardsIgnoreLine
		fclose( $output_handle ); // @codingStandardsIgnoreLine
	}
}
