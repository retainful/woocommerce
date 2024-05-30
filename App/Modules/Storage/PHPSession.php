<?php

namespace RNOC\App\Modules\Storage;

use RNOC\App\Helpers\Util;

defined( 'ABSPATH' ) || exit;

class PHPSession extends Base {
	/**
	 * Construct
	 */
	function __construct() {
		if ( ! session_id() && ! headers_sent() && ! is_admin() ) {
			session_start();
		}
	}

	/**
	 * Set session.
	 *
	 * @param string $key Session key.
	 * @param mixed $value session value.
	 *
	 * @return void
	 */
	function set( $key, $value = '' ) {
		if ( empty( $key ) ) {
			return;
		}
		$_SESSION[ $key ] = $value;
	}

	/**
	 * Get session.
	 *
	 * @param string $key Session key.
	 * @param mixed $default Session default value.
	 *
	 * @return mixed
	 */
	function get( $key, $default = '' ) {
		if ( empty( $key ) ) {
			return $default;
		}
		if ( isset( $_SESSION[ $key ] ) ) {
			return $_SESSION[ $key ];
		}

		return $default;
	}

	/**
	 * Remove session key.
	 *
	 * @param string $key Session key.
	 *
	 * @return void
	 */
	function remove( $key ) {
		if ( empty( $key ) ) {
			return;
		}
		if ( isset( $_SESSION[ $key ] ) ) {
			unset( $_SESSION[ $key ] );
		}
	}

	/**
	 * Has session key.
	 *
	 * @param string $key Session key.
	 *
	 * @return bool
	 */
	function has( $key ) {
		if ( empty( $key ) ) {
			return false;
		}

		return isset( $_SESSION[ $key ] );
	}
}