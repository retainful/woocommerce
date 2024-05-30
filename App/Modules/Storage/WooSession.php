<?php

namespace RNOC\App\Modules\Storage;

use RNOC\App\Helpers\Settings;
use RNOC\App\Helpers\Util;
use RNOC\App\Helpers\WC;

defined( 'ABSPATH' ) || exit;

class WooSession extends Base {
	/**
	 * Construct
	 */
	function __construct() {
		if ( function_exists( 'WC' ) && is_object( WC() ) && isset( WC()->session ) && Util::isMethodExists( WC()->session, 'has_session' ) ) {
			if ( ! WC()->session->has_session() && ! defined( 'DOING_CRON' ) ) {
				if ( Util::isMethodExists( WC()->session, 'set_customer_session_cookie' ) ) {
					$varnish_check = Settings::get( 'rnoc_varnish_check', 'no' );
					if ( $varnish_check === 'no' ) {
						WC()->session->set_customer_session_cookie( true );
					}
				}
			}
		}
	}

	/**
	 * Set Woocommerce session.
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
		if ( function_exists( 'WC' ) && Util::isMethodExists( WC()->session, 'set' ) ) {
			WC()->session->set( $key, $value );
		}
	}

	/**
	 * Get Woocommerce session.
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
		if ( function_exists( 'WC' ) && Util::isMethodExists( WC()->session, 'get' ) ) {
			return WC()->session->get( $key, $default );
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
		if ( function_exists( 'WC' ) && Util::isMethodExists( WC()->session, '__unset' ) ) {
			WC()->session->__unset( $key );
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
		$session = [];
		if ( function_exists( 'WC' ) && Util::isMethodExists( WC()->session, 'get_session_data' ) ) {
			$session = WC()->session->get_session_data();
		}

		return array_key_exists( $key, $session );
	}
}