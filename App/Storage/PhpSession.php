<?php

namespace Rnoc\App\Storage;
class PhpSession extends Base {
	function __construct() {
		if ( ! session_id() && ! headers_sent() && ! is_admin() ) {
			session_start();
		}
	}

	/**
	 * check the cookie has the value
	 *
	 * @param $key
	 *
	 * @return bool
	 */
	function hasKey( $key ) {
		return ( isset( $_SESSION[ $key ] ) );
	}

	/**
	 * Set the value for the PHP session
	 *
	 * @param $key
	 * @param $value
	 *
	 * @return bool
	 */
	function setValue( $key, $value ) {
		if ( empty( $key ) ) {
			return false;
		}
		$_SESSION[ $key ] = $value;

		return true;
	}

	/**
	 * get the value from the session
	 *
	 * @param $key
	 *
	 * @return mixed|null
	 */
	function getValue( $key, $default = '' ) {
		if ( empty( $key ) ) {
			return null;
		}

		return isset( $_SESSION[ $key ] ) ? $_SESSION[ $key ] : null;

	}

	/**
	 * remove the value from the session
	 *
	 * @param $key
	 *
	 * @return bool
	 */
	function removeValue( $key ) {
		if ( empty( $key ) || empty( $_SESSION[ $key ] ) ) {
			return false;
		}
		unset( $_SESSION[ $key ] );

		return true;
	}
}