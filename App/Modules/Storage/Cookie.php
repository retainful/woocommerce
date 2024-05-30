<?php

namespace RNOC\App\Modules\Storage;

use RNOC\App\Helpers\Util;

defined( 'ABSPATH' ) || exit;

class Cookie extends Base {

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
		$this->setCookieValue( $key, $value, 0 );
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
		if ( isset( $_COOKIE[ $key ] ) ) {
			return $_COOKIE[ $key ];
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
		$this->setCookieValue( $key, null, - 1 );
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

		return ( isset( $_COOKIE[ $key ] ) );
	}

	/**
	 * Set cookie value.
	 *
	 * @param string $key Session key.
	 * @param mixed $value Session value.
	 * @param int $expires Session expiry in seconds.
	 *
	 * @return void
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
}