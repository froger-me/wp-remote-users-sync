<?php
/**
 * Exception raised when WPRUS cannot safely handle user creation.
 *
 * @package Wprus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Represent a user creation rejected by WPRUS. */
class Wprus_Identity_Creation_Exception extends RuntimeException {}
