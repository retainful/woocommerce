<?php

namespace Rnoc\App\Storage;
class Cookie extends Base {
	/**
	 * check the cookie has the value
	 *
	 * @param $key
	 *
	 * @return bool
	 */
	function hasKey( $key ) {
		return ( isset( $_COOKIE[ $key ] ) );
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
		$this->setCookieValue( $key, $value, 0 );

		return true;
	}

	/**
	 * set the cookie value
	 *
	 * @param $key
	 * @param $value
	 * @param $expires
	 */
	function setCookieValue( $key, $value, $expires ) {
		if ( function_exists( 'wc_setcookie' ) ) {
			wc_setcookie( $key, $value, $expires );
		} else {
			if ( ! headers_sent() ) {
				setcookie( $key, $value, array(
					'expires'  => $expires,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN,
					'samesite' => 'None',
					'secure'   => false,
					'httponly' => false,
				) );
			}
		}
	}

	/**
	 * get the value from the session
	 *
	 * @param $key
	 *
	 * @return mixed|null
	 */
	function getValue( $key ) {
		if ( empty( $key ) ) {
			return null;
		}

		return isset( $_COOKIE[ $key ] ) ? $_COOKIE[ $key ] : null;

	}

	/**
	 * remove the value from the session
	 *
	 * @param $key
	 *
	 * @return bool
	 */
	function removeValue( $key ) {
		if ( empty( $key ) || empty( $_COOKIE[ $key ] ) ) {
			return false;
		}
		unset( $_COOKIE[ $key ] );
		$this->setCookieValue( $key, null, - 1 );

		return true;
	}
}